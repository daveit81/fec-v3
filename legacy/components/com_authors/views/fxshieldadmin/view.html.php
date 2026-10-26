<?php
/**
 * AuthorsViewFxshieldadmin — FXShield admin cockpit (Phase A, read-only).
 *
 * At-a-glance status of every casino with an FXShield footprint: score, tier,
 * confidence, baseline completeness, active events, last first-party check.
 * Surfaces the work queue — which casinos sit at confidence D and need a
 * hands-on verification, which have incomplete baselines, which were never
 * touched.
 *
 * Read-only by design. Writing (the verification workflow with its progress
 * bar, and generic event entry) is Phase B/C — this view deliberately holds
 * no forms, so there's zero risk to the ledger from shipping it.
 *
 * URL: /fxshield-admin  (router)  →  view=fxshieldadmin
 * Gated to DASHBOARD_ALLOWED_USERS, same as methodologyadmin.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT . '/models/methodology.php';
require_once JPATH_COMPONENT . '/models/dashboard.php';

class AuthorsViewFxshieldadmin extends JView
{
    public function display($tpl = null)
    {
        // Auth gate — identical to the editorial dashboard / methodology admin
        if (!AuthorsModelDashboard::isAuthorized()) {
            throw new Exception('Forbidden', 403);
        }

        $model = new AuthorsModelMethodology();

        $this->rows  = $model->getFxshieldDashboard();
        $this->stats = $model->getFxshieldStats();

        // Derive summary counters for the header strip (one pass over rows)
        $summary = array(
            'total'       => 0,  // casinos with any FXShield footprint
            'scored'      => 0,  // have a non-NULL si_score
            'unscored'    => 0,  // footprint but no score yet (baseline/events only)
            'conf'        => array('A' => 0, 'B' => 0, 'C' => 0, 'D' => 0),
            'never_fp'    => 0,  // zero first-party-verified baseline fields
            'incomplete'  => 0,  // fewer than 9 baseline fields present
            'need_verify' => 0,  // scored AND confidence D — the work queue
        );
        foreach ($this->rows as $r) {
            $summary['total']++;
            $hasScore = ($r['si_score'] !== null && $r['si_score'] !== '');
            if ($hasScore) {
                $summary['scored']++;
                $c = isset($r['si_confidence']) ? strtoupper((string)$r['si_confidence']) : '';
                if (isset($summary['conf'][$c])) $summary['conf'][$c]++;
                if ($c === 'D') $summary['need_verify']++;
            } else {
                $summary['unscored']++;
            }
            if ((int)$r['baseline_verified'] === 0) $summary['never_fp']++;
            if ((int)$r['baseline_fields'] < 9)     $summary['incomplete']++;
        }
        $this->summary = $summary;

        $doc = JFactory::getDocument();
        $doc->setTitle('FXShield status · Admin · FreeExtraChips');
        $doc->setMetaData('robots', 'noindex,nofollow');

        $doc->addStyleSheet(
            JUri::root(true) . '/components/com_authors/assets/methodology.css'
            . '?v=' . (@filemtime(JPATH_SITE . '/components/com_authors/assets/methodology.css') ?: 1)
        );
        $doc->addStyleSheet(
            JUri::root(true) . '/components/com_authors/assets/dashboard.css'
            . '?v=' . (@filemtime(JPATH_SITE . '/components/com_authors/assets/dashboard.css') ?: 1)
        );

        parent::display($tpl);
    }
}
