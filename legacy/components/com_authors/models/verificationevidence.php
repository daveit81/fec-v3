<?php
/**
 * AuthorsModelVerificationEvidence — FXShield evidence files (B4.1).
 *
 * Secure upload + catalog for verification screenshots. The wizard collects
 * an image/PDF per step; this model validates it by REAL content (not
 * extension), routes it to the public or private area by FIELD (the system
 * decides, never the user — a withdrawal shot can't be made public by
 * mistake), stores it under a random name, and records a row in
 * #__fec_verification_evidence.
 *
 * Storage areas (preflighted on the server):
 *   PRIVATE  /home/freeextr/fxshield-evidence            (outside webroot + deny)
 *   PUBLIC   /home/freeextr/public_html/images/fxshield  (served to readers)
 *
 * Public fields: footer/licence, RG dashboard, ADR, T&C — pages of the casino,
 * nothing personal. Private fields: the test-withdrawal and any KYC shot —
 * they carry the tester's name/account/amounts, so they never go public; the
 * public PRG number stands in for the image.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

class AuthorsModelVerificationEvidence extends JModel
{
    /** Absolute storage roots (server-verified). */
    const DIR_PRIVATE = '/home/freeextr/fxshield-evidence';
    const DIR_PUBLIC  = '/home/freeextr/public_html/images/fxshield';
    /** Public URL base for served evidence. */
    const URL_PUBLIC  = '/images/fxshield';

    const MAX_BYTES = 8388608; // 8 MB per file

    /** Allowed types: real MIME (from finfo) => canonical extension. */
    public static function allowedTypes()
    {
        return array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'application/pdf' => 'pdf',
        );
    }

    /**
     * Which evidence fields are publishable, and which are private-only.
     * The KEY is the wizard evidence field; routing is by this map ALONE.
     */
    public static function fieldVisibility()
    {
        return array(
            'ev_footer' => 'public',   // footer, licence, registry, seal
            'ev_rg'     => 'public',   // RG dashboard
            'ev_adr'    => 'public',   // ADR / dispute portal
            'ev_tc'     => 'public',   // T&C clauses
            'ev_payout' => 'private',  // withdrawal confirmation — personal data
            'ev_kyc'    => 'private',  // any KYC shot — personal data
        );
    }

    /** Map an evidence field to the wizard step (for cataloging + display). */
    public static function fieldStep($fieldCode)
    {
        $m = array('ev_footer'=>1,'ev_rg'=>2,'ev_adr'=>3,'ev_tc'=>4,'ev_payout'=>5,'ev_kyc'=>5);
        return isset($m[$fieldCode]) ? $m[$fieldCode] : 0;
    }

    /**
     * Handle one uploaded file for a verification + field.
     * $file is a single entry from $_FILES (name,type,tmp_name,error,size).
     * Returns array('ok'=>bool, 'message'=>, 'id'=>?).
     */
    public function store($verificationId, $casinoId, $fieldCode, array $file, $userId)
    {
        $vis = self::fieldVisibility();
        if (!isset($vis[$fieldCode])) {
            return array('ok'=>false, 'message'=>'Unknown evidence field.');
        }
        $visibility = $vis[$fieldCode];

        // Basic upload sanity
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return array('ok'=>false, 'message'=>'Upload failed (error code '
                . (isset($file['error']) ? $file['error'] : '?') . ').');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return array('ok'=>false, 'message'=>'Not a valid uploaded file.');
        }
        if ($file['size'] <= 0 || $file['size'] > self::MAX_BYTES) {
            return array('ok'=>false, 'message'=>'File too large (max 8 MB) or empty.');
        }

        // Validate by REAL content, not the client-sent type or the extension.
        $mime = $this->detectMime($file['tmp_name']);
        $allowed = self::allowedTypes();
        if (!isset($allowed[$mime])) {
            return array('ok'=>false, 'message'=>'Only JPG, PNG, WEBP, GIF or PDF are accepted (detected: '
                . htmlspecialchars($mime) . ').');
        }
        $ext = $allowed[$mime];

        // Random, unguessable stored name. No part of the original name (which
        // could carry path traversal) goes into the path.
        $stored = 'fx' . $verificationId . '_' . bin2hex(self::randomBytes(12)) . '.' . $ext;
        $dir    = ($visibility === 'public') ? self::DIR_PUBLIC : self::DIR_PRIVATE;
        $dest   = $dir . '/' . $stored;

        if (!is_dir($dir) || !is_writable($dir)) {
            return array('ok'=>false, 'message'=>'Storage area not writable on the server.');
        }
        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            return array('ok'=>false, 'message'=>'Could not save the file.');
        }
        @chmod($dest, 0644);

        // Catalog it
        $db  = JFactory::getDbo();
        $now = JFactory::getDate()->toSql();
        $origName = $this->sanitizeLabel($file['name']);

        $sql = "INSERT INTO #__fec_verification_evidence
                (verification_id, casino_id, step, field_code, visibility,
                 original_name, stored_name, mime_type, byte_size, uploaded_by, uploaded_at)
                VALUES (" . (int)$verificationId . ", " . (int)$casinoId . ", "
                . (int)self::fieldStep($fieldCode) . ", " . $db->quote($fieldCode) . ", "
                . $db->quote($visibility) . ", " . $db->quote($origName) . ", "
                . $db->quote($stored) . ", " . $db->quote($mime) . ", "
                . (int)$file['size'] . ", " . (int)$userId . ", " . $db->quote($now) . ")";
        try {
            $db->setQuery($sql);
            $db->execute();
            $id = (int)$db->insertid();
        } catch (Exception $e) {
            @unlink($dest); // don't leave an orphan file if the row fails
            return array('ok'=>false, 'message'=>'Saved file but could not catalog it; rolled back.');
        }

        return array('ok'=>true, 'id'=>$id, 'visibility'=>$visibility,
            'message'=>'Uploaded ' . $origName . '.');
    }

    /**
     * Public evidence for a casino, for the public FXShield page. Only files
     * that are (a) visibility=public AND (b) attached to a COMMITTED
     * verification — drafts and private files never surface publicly.
     */
    public function listPublicForCasino($casinoId)
    {
        $db = JFactory::getDbo();
        $db->setQuery(
            "SELECT e.* FROM #__fec_verification_evidence e
               INNER JOIN #__fec_verifications v ON v.id = e.verification_id
              WHERE e.casino_id = " . (int)$casinoId . "
                AND e.visibility = 'public'
                AND v.status = 'committed'
              ORDER BY e.step, e.id"
        );
        $rows = $db->loadAssocList();
        return is_array($rows) ? $rows : array();
    }

    /** All evidence rows for a verification (for wizard lists + review gallery). */
    public function listForVerification($verificationId)
    {
        $db = JFactory::getDbo();
        $db->setQuery(
            "SELECT * FROM #__fec_verification_evidence
              WHERE verification_id = " . (int)$verificationId . "
              ORDER BY step, id"
        );
        $rows = $db->loadAssocList();
        return is_array($rows) ? $rows : array();
    }

    /** Delete one evidence file (only before commit; controller enforces that). */
    public function delete($id)
    {
        $db = JFactory::getDbo();
        $db->setQuery("SELECT * FROM #__fec_verification_evidence WHERE id = " . (int)$id);
        $row = $db->loadAssoc();
        if (!$row) return false;
        $dir = ($row['visibility'] === 'public') ? self::DIR_PUBLIC : self::DIR_PRIVATE;
        @unlink($dir . '/' . $row['stored_name']);
        $db->setQuery("DELETE FROM #__fec_verification_evidence WHERE id = " . (int)$id);
        $db->execute();
        return true;
    }

    /** Public URL for a served file, or null for private ones. */
    public static function publicUrl($row)
    {
        if ($row['visibility'] !== 'public') return null;
        return self::URL_PUBLIC . '/' . $row['stored_name'];
    }

    // --- helpers ---

    protected function detectMime($path)
    {
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            $m = finfo_file($f, $path);
            finfo_close($f);
            if ($m) return $m;
        }
        // Fallback: getimagesize for images (still content-based, not extension)
        $info = @getimagesize($path);
        if ($info && isset($info['mime'])) return $info['mime'];
        // PDF magic bytes
        $fh = @fopen($path, 'rb'); $head = $fh ? fread($fh, 5) : ''; if ($fh) fclose($fh);
        if ($head === '%PDF-') return 'application/pdf';
        return 'application/octet-stream';
    }

    protected function sanitizeLabel($name)
    {
        $name = preg_replace('/[\x00-\x1f\x7f]/', '', (string)$name);
        $name = str_replace(array('"', "'", '\\', '/'), '', $name);
        return JString::substr($name, 0, 200);
    }

    protected static function randomBytes($n)
    {
        if (function_exists('random_bytes')) return random_bytes($n);
        if (function_exists('openssl_random_pseudo_bytes')) return openssl_random_pseudo_bytes($n);
        $s = ''; for ($i=0;$i<$n;$i++) $s .= chr(mt_rand(0,255)); return $s;
    }
}
