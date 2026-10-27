<?php
/**
 * AuthorsControllerDashboard
 *
 * AJAX endpoint for all writes from the editorial dashboard.
 *
 * Tasks (every one returns JSON):
 *   fetchSlot         — slot metadata + RTP row + observations + casino picker
 *   saveRtp           — upsert RTP data for a gid
 *   saveObservation   — insert a new per-casino observation
 *   deleteObservation — remove an observation (also unlinks screenshot file)
 *   archiveWayback    — kick Wayback save + poll for snapshot URL
 *   uploadScreenshot  — POST multipart, resize, save under /images/rtp/<gid>/
 *
 * Every task hard-checks AuthorsModelDashboard::isAuthorized() first.
 * Every task that mutates state requires a valid Joomla form token.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

require_once JPATH_COMPONENT . '/models/dashboard.php';

class AuthorsControllerDashboard extends JController
{
    /** Max megapixels we'll accept after JS-side resize. Defense in depth. */
    const MAX_IMAGE_BYTES = 3 * 1024 * 1024; // 3 MB after JS resize

    /** JPEG quality for re-encoded screenshots. */
    const JPEG_QUALITY = 82;

    /** Final long-edge cap (px) — JS aims for 1600, this is the server safety net. */
    const MAX_LONG_EDGE = 1800;

    /**
     * Common preamble for all tasks: auth + JSON header.
     * Calls jexit() on auth failure so the rest of the method can assume access.
     */
    protected function gateAndJson($checkToken = true)
    {
        // Force JSON output
        while (ob_get_level() > (defined('FEC_OB_BASE') ? FEC_OB_BASE : 0)) { @ob_end_clean(); } // PORT: keep the runner's buffer
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, must-revalidate');

        if (!AuthorsModelDashboard::isAuthorized()) {
            echo json_encode(array('ok' => false, 'error' => 'forbidden'));
            JFactory::getApplication()->close();
        }
        if ($checkToken) {
            if (!JRequest::checkToken('request') && !JRequest::checkToken('post')) {
                echo json_encode(array('ok' => false, 'error' => 'invalid_token'));
                JFactory::getApplication()->close();
            }
        }
    }

    /** Look up #__casino_authors.id for the current Joomla user (or 0). */
    protected function currentAuthorId()
    {
        $user = JFactory::getUser();
        if (!$user || $user->guest) return 0;
        $db = JFactory::getDbo();
        $db->setQuery("SELECT id FROM #__casino_authors WHERE joomla_user_id = " . (int)$user->id . " LIMIT 1");
        return (int)$db->loadResult();
    }

    // -----------------------------------------------------------------------
    // Tasks
    // -----------------------------------------------------------------------

    /**
     * Search #__games for slots not yet in the RTP database.
     * Feeds the "+ Add slot" modal autocomplete.
     */
    public function searchGames()
    {
        $this->gateAndJson(false);
        $q     = JRequest::getString('q', '');
        $model = new AuthorsModelDashboard();
        $results = $model->searchGamesNotDocumented($q);
        echo json_encode(array('ok' => true, 'results' => $results));
        JFactory::getApplication()->close();
    }

    /**
     * Create a stub #__slot_rtp record for a given gid.
     * Returns the newly-created (or already-existing) slot data so the
     * client can open it directly in the edit panel.
     */
    public function addSlot()
    {
        $this->gateAndJson(true); // POST-style write, CSRF required
        $gid = JRequest::getInt('gid', 0);
        $model = new AuthorsModelDashboard();
        $result = $model->createSlotStub($gid);
        if (!$result['ok']) {
            echo json_encode($result);
            JFactory::getApplication()->close();
        }
        // Return the fetched slot so client can open it immediately
        $slot = $model->getSlot($gid);
        echo json_encode(array(
            'ok'   => true,
            'gid'  => $gid,
            'slot' => $slot,
        ));
        JFactory::getApplication()->close();
    }

    /**
     * v2.7.10 — List distinct providers for the "create new game" form.
     */
    public function listProviders()
    {
        $this->gateAndJson(false); // GET read
        $model = new AuthorsModelDashboard();
        $providers = $model->getProviders();
        echo json_encode(array('ok' => true, 'providers' => $providers));
        JFactory::getApplication()->close();
    }

    /**
     * v2.7.10 — Insert a new game into #__games and immediately create a
     * slot_rtp stub for it, returning the slot record so the client can open
     * the editor without an extra round-trip.
     */
    public function createGame()
    {
        $this->gateAndJson(true); // POST write, CSRF required
        $data = array(
            'game_name'      => JRequest::getString('game_name', ''),
            'type'           => JRequest::getString('type', 'slot'),
            'provider_value' => JRequest::getString('provider_value', ''),
            'provider_name'  => JRequest::getString('provider_name', ''),
        );
        $model = new AuthorsModelDashboard();
        $result = $model->createGameAndStub($data);
        if (empty($result['ok'])) {
            echo json_encode($result);
            JFactory::getApplication()->close();
        }
        $gid = (int)$result['gid'];
        $slot = $model->getSlot($gid);
        echo json_encode(array(
            'ok'   => true,
            'gid'  => $gid,
            'name' => isset($result['name']) ? $result['name'] : '',
            'slot' => $slot,
        ));
        JFactory::getApplication()->close();
    }

    public function fetchSlot()
    {
        $this->gateAndJson(false); // GET-style read, no token required
        $gid   = JRequest::getInt('gid', 0);
        $model = new AuthorsModelDashboard();
        $slot  = $model->getSlot($gid);
        if (!$slot) {
            echo json_encode(array('ok' => false, 'error' => 'not_found'));
            JFactory::getApplication()->close();
        }
        $observations = $model->getObservations($gid);
        // Decorate observations with the public screenshot URL
        $imageBase = JURI::root(true) . '/images/rtp/';
        foreach ($observations as $o) {
            $o->screenshot_url = $o->screenshot_path ? ($imageBase . ltrim($o->screenshot_path, '/')) : null;
        }
        $casinos = $model->getCasinos();

        echo json_encode(array(
            'ok'           => true,
            'slot'         => $slot,
            'observations' => $observations,
            'casinos'      => $casinos,
        ));
        JFactory::getApplication()->close();
    }

    public function saveRtp()
    {
        $this->gateAndJson(true);
        $data = array(
            'gid'                          => JRequest::getInt('gid', 0),
            'slug'                         => JRequest::getString('slug', ''),
            'published'                    => JRequest::getInt('published', 0),
            'retired'                      => JRequest::getInt('retired', 0),
            'retired_reason'               => JRequest::getString('retired_reason', ''),
            'rtp_theoretical_max'          => JRequest::getString('rtp_theoretical_max', ''),
            'rtp_theoretical_min'          => JRequest::getString('rtp_theoretical_min', ''),
            'rtp_variants_json'            => JRequest::getString('rtp_variants_json', ''),
            'max_win_x'                    => JRequest::getString('max_win_x', ''),
            'volatility'                   => JRequest::getString('volatility', ''),
            'paylines'                     => JRequest::getString('paylines', ''),
            'reels'                        => JRequest::getString('reels', ''),
            'release_year'                 => JRequest::getString('release_year', ''),
            'primary_source_url'           => JRequest::getString('primary_source_url', ''),
            'primary_source_archived_url'  => JRequest::getString('primary_source_archived_url', ''),
            'certificate_url'              => JRequest::getString('certificate_url', ''),
            'last_verified_date'           => JRequest::getString('last_verified_date', ''),
            'notes'                        => JRequest::getString('notes', '', 'post', JREQUEST_ALLOWRAW),
        );
        // Verified-by author: prefer the explicit select value from the form,
        // fall back to the logged-in user only if the form didn't provide one.
        // (Form select was added in v2.6.4 but the controller still ignored
        // it through v2.6.6 — saves silently fell back to currentAuthorId.)
        $formAid = JRequest::getInt('verified_by_author_id', 0);
        if ($formAid > 0) {
            $data['verified_by_author_id'] = $formAid;
        } else {
            $aid = $this->currentAuthorId();
            if ($aid) $data['verified_by_author_id'] = $aid;
        }
        // Auto-set last_verified_date to today if blank but RTP filled
        if ($data['last_verified_date'] === '' && $data['rtp_theoretical_max'] !== '') {
            $data['last_verified_date'] = date('Y-m-d');
        }
        // Validate JSON variants if provided — accept comma-separated as fallback
        if ($data['rtp_variants_json'] !== '') {
            $raw = trim($data['rtp_variants_json']);
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                // Try comma split
                $parts = array_filter(array_map('trim', explode(',', $raw)), 'strlen');
                if ($parts) {
                    $data['rtp_variants_json'] = json_encode(array_values($parts));
                } else {
                    $data['rtp_variants_json'] = '';
                }
            }
        }

        $model = new AuthorsModelDashboard();
        $id = $model->saveSlotRtp($data);
        if (!$id) {
            echo json_encode(array('ok' => false, 'error' => 'save_failed'));
            JFactory::getApplication()->close();
        }
        echo json_encode(array('ok' => true, 'id' => $id, 'last_verified_date' => $data['last_verified_date']));
        JFactory::getApplication()->close();
    }

    public function saveObservation()
    {
        $this->gateAndJson(true);
        $data = array(
            'gid'             => JRequest::getInt('gid', 0),
            'cid'             => JRequest::getInt('cid', 0),
            'rtp_observed'    => JRequest::getString('rtp_observed', ''),
            'observed_date'   => JRequest::getString('observed_date', date('Y-m-d')),
            'evidence_type'   => JRequest::getCmd('evidence_type', 'paytable_screenshot'),
            'screenshot_path' => JRequest::getString('screenshot_path', ''),
            'notes'           => JRequest::getString('notes', '', 'post', JREQUEST_ALLOWRAW),
        );
        $aid = $this->currentAuthorId();
        if ($aid) $data['verified_by_author_id'] = $aid;

        $model = new AuthorsModelDashboard();
        $id = $model->saveObservation($data);
        if (!$id) {
            echo json_encode(array('ok' => false, 'error' => 'save_failed'));
            JFactory::getApplication()->close();
        }
        echo json_encode(array('ok' => true, 'id' => $id));
        JFactory::getApplication()->close();
    }

    public function deleteObservation()
    {
        $this->gateAndJson(true);
        $id = JRequest::getInt('id', 0);
        $model = new AuthorsModelDashboard();
        $res = $model->deleteObservation($id);
        if (!$res) {
            echo json_encode(array('ok' => false, 'error' => 'delete_failed'));
            JFactory::getApplication()->close();
        }
        // Unlink screenshot file if it exists and is under the safe path.
        if (!empty($res['screenshot_path'])) {
            $full = JPATH_ROOT . '/images/rtp/' . ltrim($res['screenshot_path'], '/');
            $safe = realpath(JPATH_ROOT . '/images/rtp/');
            $realFull = realpath($full);
            if ($safe && $realFull && strpos($realFull, $safe) === 0 && is_file($realFull)) {
                @unlink($realFull);
            }
        }
        echo json_encode(array('ok' => true));
        JFactory::getApplication()->close();
    }

    public function archiveWayback()
    {
        $this->gateAndJson(true);
        $url = trim(JRequest::getString('url', ''));
        $model = new AuthorsModelDashboard();
        $res = $model->archiveWayback($url);
        echo json_encode($res);
        JFactory::getApplication()->close();
    }

    /**
     * Multipart upload — POST 'gid' + 'screenshot' file.
     *
     * Saves to /images/rtp/<gid>/<gid>-<timestamp>.jpg (or .png if PNG).
     * If JS already pre-named the file with a casino id ('cid'), we use it
     * for the filename instead of <gid>: <cid>-<timestamp>.jpg.
     *
     * Server-side guards: file size cap, MIME sniff, long-edge re-cap via GD.
     */
    public function uploadScreenshot()
    {
        $this->gateAndJson(true);
        $gid = JRequest::getInt('gid', 0);
        $cid = JRequest::getInt('cid', 0);
        if (!$gid) {
            echo json_encode(array('ok' => false, 'error' => 'missing_gid'));
            JFactory::getApplication()->close();
        }

        if (empty($_FILES['screenshot']) || empty($_FILES['screenshot']['tmp_name'])) {
            echo json_encode(array('ok' => false, 'error' => 'no_file'));
            JFactory::getApplication()->close();
        }
        $file = $_FILES['screenshot'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array('ok' => false, 'error' => 'upload_error_' . (int)$file['error']));
            JFactory::getApplication()->close();
        }
        if ($file['size'] > self::MAX_IMAGE_BYTES) {
            echo json_encode(array('ok' => false, 'error' => 'too_big', 'max_bytes' => self::MAX_IMAGE_BYTES));
            JFactory::getApplication()->close();
        }

        // MIME sniff
        $info = @getimagesize($file['tmp_name']);
        if (!$info || empty($info['mime'])) {
            echo json_encode(array('ok' => false, 'error' => 'not_an_image'));
            JFactory::getApplication()->close();
        }
        $mime = $info['mime'];
        $allowed = array('image/jpeg', 'image/png', 'image/webp');
        if (!in_array($mime, $allowed, true)) {
            echo json_encode(array('ok' => false, 'error' => 'unsupported_mime', 'mime' => $mime));
            JFactory::getApplication()->close();
        }

        // Build save folder
        $relFolder = $gid . '/';
        $absFolder = JPATH_ROOT . '/images/rtp/' . $relFolder;
        if (!is_dir($absFolder)) {
            if (!@mkdir($absFolder, 0755, true)) {
                echo json_encode(array('ok' => false, 'error' => 'mkdir_failed', 'path' => $absFolder));
                JFactory::getApplication()->close();
            }
        }

        // Build filename: <cid>-<ts>.jpg (or .png), or <gid>-<ts>.jpg if no cid
        $ts = date('YmdHis');
        $stub = ($cid > 0 ? $cid : ('g' . $gid)) . '-' . $ts;
        // Decide output format: JPEG by default (smaller). PNG only if alpha
        // channel matters — for paytable screenshots JPEG is fine.
        $ext = '.jpg';
        $outPath = $absFolder . $stub . $ext;

        // Re-encode through GD: enforces long-edge cap + strips EXIF +
        // normalizes to JPEG.
        $ok = $this->reencodeAndSave($file['tmp_name'], $mime, $outPath);
        if (!$ok) {
            echo json_encode(array('ok' => false, 'error' => 'reencode_failed'));
            JFactory::getApplication()->close();
        }

        $relPath = $relFolder . $stub . $ext;
        $publicUrl = JURI::root(true) . '/images/rtp/' . $relPath;

        echo json_encode(array(
            'ok'              => true,
            'screenshot_path' => $relPath,
            'url'             => $publicUrl,
        ));
        JFactory::getApplication()->close();
    }

    /** Helper: GD-based re-encode with long-edge cap. */
    protected function reencodeAndSave($srcPath, $mime, $destPath)
    {
        if (!function_exists('imagecreatefromjpeg')) {
            // GD not available — fall back to a raw move (less safe but works)
            return @move_uploaded_file($srcPath, $destPath);
        }
        switch ($mime) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($srcPath); break;
            case 'image/png':  $src = @imagecreatefrompng($srcPath);  break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($srcPath);
                else $src = null;
                break;
            default: $src = null;
        }
        if (!$src) return false;
        $w = imagesx($src);
        $h = imagesy($src);
        $long = max($w, $h);
        if ($long > self::MAX_LONG_EDGE) {
            $ratio = self::MAX_LONG_EDGE / $long;
            $nw = (int)round($w * $ratio);
            $nh = (int)round($h * $ratio);
            $dst = imagecreatetruecolor($nw, $nh);
            // Preserve a white background (we're saving JPEG)
            $bg = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $nw, $nh, $bg);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }
        $ok = imagejpeg($src, $destPath, self::JPEG_QUALITY);
        imagedestroy($src);
        if ($ok) @chmod($destPath, 0644);
        return $ok;
    }
}
