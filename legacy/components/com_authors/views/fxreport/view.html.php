<?php
/**
 * AuthorsViewFxreport — FXShield Verification Report (public, print-ready).
 *
 * A sober, institutional one-page record attesting that FreeExtraChips
 * editorial staff verified a casino first-party: the real score (high or low),
 * tier, confidence, licence cap, payout-speed (PRG), and the recorded ledger
 * events INCLUDING the negatives. Not an approval seal — a verification
 * attestation. Doubles as the source of the downloadable PDF (browser print).
 *
 * Hard rule: ONLY first-party verified casinos get a report. A desk-only
 * (confidence D, nothing verified) casino has had no verification, so issuing
 * a "verification report" for it would be a contradiction. The view refuses.
 *
 * URL (non-SEF, deliberate): index.php?option=com_authors&view=fxreport&casino_id=ID
 * Public (no auth gate) — it is a shareable record. Reads only committed,
 * public-safe data.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/models/verification.php';

class AuthorsViewFxreport extends JView
{
    public function display($tpl = null)
    {
        $casinoId = (int) JRequest::getVar('casino_id', 0, '', 'int');
        if ($casinoId <= 0) {
            throw new Exception('No casino specified', 404);
        }

        $data = AuthorsModelVerification::factSheetData($casinoId);
        if ($data === null) {
            throw new Exception('No FXShield record for this casino', 404);
        }

        // First-party gate: at least one baseline field verified first-party.
        $fpCount = 0; $totalFields = 0;
        foreach ($data['baseline'] as $bf) {
            $totalFields++;
            if ((int)$bf['verified_first_party'] === 1) $fpCount++;
        }
        if ($fpCount === 0) {
            throw new Exception('This casino has not been first-party verified; no report is available.', 404);
        }

        $this->data        = $data;
        $this->fpCount     = $fpCount;
        $this->totalFields = $totalFields;

        // SEO / document head. Even under tmpl=component the JDocument head is
        // emitted, so set title + meta here. The report is indexable on purpose
        // (unique, verifiable content), so NO noindex. The <title> also becomes
        // the default filename when the user prints the page to PDF.
        $doc   = JFactory::getDocument();
        $name  = $data['title'];
        $score = $data['score'];
        $si    = ($score['si_score'] !== null) ? (int)$score['si_score'] : null;
        $tier  = (string)$score['si_tier'];

        $doc->setTitle($name . ' — FXShield Verification Report | FreeExtraChips');

        $descBits = array('First-party safety verification of ' . $name . ' by FreeExtraChips.');
        if ($si !== null)   $descBits[] = 'FXShield Safety Index ' . $si . '/100' . ($tier !== '' ? ' (' . $tier . ')' : '') . '.';
        $descBits[] = 'Verified score, payout-speed test and recorded events.';
        $doc->setDescription(implode(' ', $descBits));

        // Explicit index,follow (overrides any site-wide default) + canonical.
        if (method_exists($doc, 'setMetaData')) {
            $doc->setMetaData('robots', 'index, follow');
        }
        $canonical = JUri::base() . 'index.php?option=com_authors&view=fxreport&casino_id=' . (int)$data['casino_id'];
        if (method_exists($doc, 'addHeadLink')) {
            $doc->addHeadLink($canonical, 'canonical');
        }

        // Strip the site chrome — this page is a standalone document.
        JRequest::setVar('tmpl', 'component');

        parent::display($tpl);
    }
}
