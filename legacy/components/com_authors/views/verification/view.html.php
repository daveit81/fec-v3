<?php
/**
 * AuthorsViewVerification — FXShield verification wizard (B2).
 *
 * Per-page, step-by-step wizard backed by AuthorsModelVerification drafts.
 * Reads ?id=<draft>&step=<n>; with no id it shows the "start a verification"
 * launcher (pick a casino). Gated to DASHBOARD_ALLOWED_USERS.
 *
 * This view only reads/writes the draft (through the controller's saveStep).
 * No engine-table writes happen here — that is B3's commit.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/models/verification.php';
require_once JPATH_COMPONENT . '/models/verificationevidence.php';
require_once JPATH_COMPONENT . '/models/methodology.php';
require_once JPATH_COMPONENT . '/models/dashboard.php';

class AuthorsViewVerification extends JView
{
    /** Step 6 is the read-only commit preview (wizard data steps are 1..5). */
    const PREVIEW_STEP = 6;

    public function display($tpl = null)
    {
        if (!AuthorsModelDashboard::isAuthorized()) {
            throw new Exception('Forbidden', 403);
        }

        $model = new AuthorsModelVerification();

        $id   = (int) JRequest::getVar('id', 0);
        $step = (int) JRequest::getVar('step', 1);

        if ($id > 0) {
            $v = $model->getById($id);
            if (!$v) {
                throw new Exception('Verification not found', 404);
            }
            $this->vid     = $id;
            $this->row     = $v;
            $this->fields  = $v['fields'];
            $this->total   = AuthorsModelVerification::TOTAL_STEPS;
            $this->casino  = $this->casinoTitle((int)$v['casino_id']);
            $this->committed = ($v['status'] === 'committed');

            // Step 6 = read-only commit preview (B3.1). Steps 1..5 are the wizard.
            $reqStep = max(1, min(self::PREVIEW_STEP, $step));
            $this->step = $reqStep;

            // Evidence files attached so far, grouped by field_code.
            $evModel = new AuthorsModelVerificationEvidence();
            $evRows = $evModel->listForVerification($id);
            $byField = array();
            foreach ($evRows as $er) {
                $byField[$er['field_code']][] = $er;
            }
            $this->evidence = $byField;

            if ($reqStep === self::PREVIEW_STEP) {
                $this->preview = $model->computeCommitPreview($id);
                $tpl = 'preview';
            } else {
                $tpl = 'step';
            }
        } else {
            // Launcher: list scored casinos to start a verification against,
            // plus any open drafts to resume.
            $this->scored    = $this->scoredCasinos();
            $this->openDrafts = $model->listAll();
            $tpl = null; // default.php
        }

        $this->error = trim((string) JRequest::getVar('error', ''));

        $doc = JFactory::getDocument();
        $doc->setTitle('FXShield verification · Admin · FreeExtraChips');
        $doc->setMetaData('robots', 'noindex,nofollow');
        $doc->addStyleSheet(
            JUri::root(true) . '/components/com_authors/assets/dashboard.css'
            . '?v=' . (@filemtime(JPATH_SITE . '/components/com_authors/assets/dashboard.css') ?: 1)
        );

        parent::display($tpl);
    }

    protected function casinoTitle($cid)
    {
        $db = JFactory::getDbo();
        $db->setQuery("SELECT title FROM #__content WHERE id = " . (int)$cid);
        $t = $db->loadResult();
        return ($t !== null && $t !== '') ? $t : ('#' . $cid);
    }

    /** Casinos that have an FXShield score, for the launcher dropdown. */
    protected function scoredCasinos()
    {
        $db = JFactory::getDbo();
        $db->setQuery(
            "SELECT s.casino_id, c.title, s.si_score, s.si_confidence
               FROM #__fec_safety_scores s
               LEFT JOIN #__content c ON c.id = s.casino_id
              WHERE s.si_score IS NOT NULL
              ORDER BY s.si_score DESC"
        );
        $rows = $db->loadAssocList();
        return is_array($rows) ? $rows : array();
    }
}
