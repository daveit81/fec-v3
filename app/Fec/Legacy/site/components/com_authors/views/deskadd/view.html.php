<?php
/**
 * AuthorsViewDeskadd — quick desk-baseline entry form (FXShield cockpit).
 * Admin-gated. Lists jReviews casino listings so the editor picks one, then
 * fills the 9 baseline fields (desk estimate, confidence D) + optional event.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/models/dashboard.php';

class AuthorsViewDeskadd extends JView
{
    public function display($tpl = null)
    {
        if (!AuthorsModelDashboard::isAuthorized()) {
            throw new Exception('Forbidden', 403);
        }

        // Casino candidates: jReviews casino listings. We surface those WITHOUT
        // a baseline yet first (so the editor adds new ones), but allow all.
        $db = JFactory::getDbo();
        $candidates = array();
        try {
            $db->setQuery(
                "SELECT c.id AS casino_id, c.title,
                        (SELECT COUNT(*) FROM #__fec_safety_baseline b WHERE b.casino_id = c.id) AS has_baseline
                   FROM #__content c
                  INNER JOIN #__jreviews_content jc ON jc.contentid = c.id
                  WHERE c.state = 1
                  ORDER BY has_baseline ASC, c.title ASC"
            );
            $rows = $db->loadAssocList();
            if (is_array($rows)) $candidates = $rows;
        } catch (Exception $e) {
            // Fallback: plain content listing if jReviews join is unavailable.
            try {
                $db->setQuery(
                    "SELECT c.id AS casino_id, c.title,
                            (SELECT COUNT(*) FROM #__fec_safety_baseline b WHERE b.casino_id = c.id) AS has_baseline
                       FROM #__content c WHERE c.state = 1 ORDER BY c.title ASC"
                );
                $rows = $db->loadAssocList();
                if (is_array($rows)) $candidates = $rows;
            } catch (Exception $e2) {
                $candidates = array();
            }
        }

        // --- Edit mode: ?casino_id=N reloads the saved desk baseline + ledger ---
        $editId        = (int) JRequest::getVar('casino_id', 0, 'get', 'int');
        $baseline      = array();
        $events        = array();
        $editTitle     = '';
        $verifiedCodes = array();

        if ($editId > 0) {
            try {
                $db->setQuery(
                    "SELECT field_code, field_value, points, verified_first_party
                       FROM #__fec_safety_baseline
                      WHERE casino_id = " . $editId
                );
                $rows = $db->loadAssocList();
                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        $baseline[$r['field_code']] = $r;
                        if ((int) $r['verified_first_party'] === 1) {
                            $verifiedCodes[] = $r['field_code'];
                        }
                    }
                }
            } catch (Exception $e) {
                $baseline = array();
            }

            try {
                $db->setQuery(
                    "SELECT event_code, event_date, base_weight, evidence_note, status
                       FROM #__fec_safety_events
                      WHERE casino_id = " . $editId . "
                      ORDER BY event_date DESC, created_at DESC"
                );
                $rows = $db->loadAssocList();
                if (is_array($rows)) $events = $rows;
            } catch (Exception $e) {
                $events = array();
            }

            foreach ($candidates as $c) {
                if ((int) $c['casino_id'] === $editId) { $editTitle = $c['title']; break; }
            }
        }

        $this->candidates    = $candidates;
        $this->editId        = $editId;
        $this->baseline      = $baseline;
        $this->events        = $events;
        $this->editTitle     = $editTitle;
        $this->verifiedCodes = $verifiedCodes;
        JRequest::setVar('tmpl', 'component'); // standalone admin form
        parent::display($tpl);
    }
}
