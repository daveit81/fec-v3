<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * Licence Watch model — quarterly register of casino licences.
 * Page topic 'licence-watch' (kind='page'), rendered by
 * views/page/tmpl/default_licence-watch.php. Desk tab in the review dashboard
 * (fec_dash_api.php, lw_* actions). Added in com_authors 2.7.61.
 *
 * Two layers, never merged:
 *   declared  — the jr_jurisdiction field of every LIVE listing
 *               (#__jreviews_content, *slug*slug* list), one count per cited
 *               regulator; multi-licence brands count once per regulator.
 *   verified  — the Operator Atlas: current operator link (#__atlas_brand_links,
 *               relation operator, date_to NULL) → entity → licence records
 *               (#__atlas_licences), grouped by regulator family and by the
 *               confidence the desk holds the record at.
 *
 * LIVE population (the same rule the dashboard state toggle writes):
 *   #__content.state = 1 AND jr_closed = '' AND jr_suspended = ''  (catid 8)
 * Blacklisted brands stay in while they operate.
 *
 * An edition is a frozen payload + diff + three desk-written sections
 * (overview, quarter, bottom_line) stored in #__fec_licence_watch_editions.
 * Drafts are visible only to the desk (user 42) via ?preview=1.
 *
 * PHP 5.6 web runtime: no return types, no scalar hints, no ??, no <=>.
 */
class AuthorsModelLicencewatch extends JModel
{
    const INDEX_PATH     = '/online-casinos/licence-watch';
    const CASINO_CATID   = 8;
    const EDITIONS_CONFIG = '/home/freeextr/conf/fec_payout_index.php'; // shares FEC_PI_CRON_KEY / FEC_PI_NOTIFY_TO
    const COMMON_LIB     = '/components/com_bonuses/helpers/fec_common_lib.php';
    const DESK_USER_ID   = 42;

    /** jr_jurisdiction slug → display label. Unknown slugs are title-cased. */
    public static function regulatorLabels()
    {
        return array(
            'curacao'             => 'Cura&ccedil;ao',
            'unitedkingdom'       => 'United Kingdom',
            'malta'               => 'Malta',
            'not-regulated'       => 'No regulator declared',
            'kahnawake'           => 'Kahnawake',
            'governmentofanjuan'  => 'Anjouan',
            'sweden'              => 'Sweden',
            'tobique'             => 'Tobique',
            'costarica'           => 'Costa Rica',
            'gibraltar'           => 'Gibraltar',
            'denmark'             => 'Denmark',
            'netherlandsantilles' => 'Netherlands Antilles (dissolved 2010)',
            'cyprus'              => 'Cyprus',
            'alderney'            => 'Alderney',
            'antiguabarbuda'      => 'Antigua and Barbuda',
            'mwali'               => 'Mwali',
            'panama'              => 'Panama',
            'italy'               => 'Italy',
            'ontario'             => 'Ontario',
            'estonia'             => 'Estonia',
            'alandislands'        => '&Aring;land Islands',
            'spain'               => 'Spain',
            'isleofman'           => 'Isle of Man',
            'belize'              => 'Belize',
            'greece'              => 'Greece',
            'montenegro'          => 'Montenegro',
        );
    }

    /** Atlas regulator string → family key + label. */
    public static function regulatorFamily($regulator)
    {
        $r = strtolower(trim((string)$regulator));
        if ($r === '') return array('none', 'No licence on record');
        if (strpos($r, 'antillephone') !== false || strpos($r, 'egaming') !== false || strpos($r, 'historic') !== false || strpos($r, 'sub-lic') !== false) {
            return array('curacao-historic', 'Cura&ccedil;ao sub-licence (historic regime)');
        }
        if (strpos($r, 'curacao') !== false || strpos($r, 'cura') !== false) return array('curacao', 'Cura&ccedil;ao Gaming Authority (OGL)');
        if (strpos($r, 'anjouan') !== false || strpos($r, 'alsi') !== false)  return array('anjouan', 'Anjouan');
        if (strpos($r, 'tobique') !== false)   return array('tobique', 'Tobique Gaming Commission');
        if (strpos($r, 'kahnawake') !== false) return array('kahnawake', 'Kahnawake Gaming Commission');
        if (strpos($r, 'malta') !== false || strpos($r, 'mga') !== false) return array('malta', 'Malta Gaming Authority');
        if (strpos($r, 'uk') === 0 || strpos($r, 'ukgc') !== false || strpos($r, 'kingdom') !== false) return array('unitedkingdom', 'UK Gambling Commission');
        if (strpos($r, 'sweden') !== false)  return array('sweden', 'Sweden');
        if (strpos($r, 'denmark') !== false) return array('denmark', 'Denmark');
        if (strpos($r, 'estonia') !== false) return array('estonia', 'Estonia');
        if (strpos($r, 'gibraltar') !== false) return array('gibraltar', 'Gibraltar');
        if (strpos($r, 'isle') !== false) return array('isleofman', 'Isle of Man');
        if (strpos($r, 'mwali') !== false || strpos($r, 'comor') !== false) return array('mwali', 'Mwali');
        return array(preg_replace('/[^a-z0-9]+/', '-', $r), htmlspecialchars(trim((string)$regulator), ENT_QUOTES, 'UTF-8'));
    }

    /** Licence row → confidence bucket: confirmed | pending | expired. */
    public static function licenceBucket($status, $confidence)
    {
        $s = strtolower((string)$status); $c = strtolower((string)$confidence);
        if ($s === 'expired' || $s === 'revoked' || $s === 'ghost') return 'expired';
        if ($s === 'active' && $c === 'confirmed') return 'confirmed';
        return 'pending';
    }

    public static function editionPath($ym)
    {
        return self::INDEX_PATH . '/' . preg_replace('/[^0-9-]/', '', (string)$ym);
    }

    public static function editionMonthLabel($ym)
    {
        $ts = strtotime(substr((string)$ym, 0, 7) . '-01');
        return $ts ? date('F Y', $ts) : (string)$ym;
    }

    public static function editionNumberLabel($number)
    {
        return 'Edition ' . str_pad((string)(int)$number, 2, '0', STR_PAD_LEFT);
    }

    // =====================================================================
    // Payload
    // =====================================================================

    protected static function liveWhere($alias = 'j')
    {
        return "c.state = 1 AND c.catid = " . (int)self::CASINO_CATID
             . " AND (" . $alias . ".jr_closed = '' OR " . $alias . ".jr_closed IS NULL)"
             . " AND (" . $alias . ".jr_suspended = '' OR " . $alias . ".jr_suspended IS NULL)";
    }

    /** Every listing with its status fields; the base for both layers. */
    protected static function listings()
    {
        $db = JFactory::getDbo();
        $db->setQuery(
            "SELECT j.contentid, c.title, c.alias, c.state, j.jr_jurisdiction, j.jr_owner,
                    j.jr_closed, j.jr_suspended, j.jr_blacklisted
               FROM #__jreviews_content j
               LEFT JOIN #__content c ON c.id = j.contentid
              WHERE c.catid = " . (int)self::CASINO_CATID
        );
        $rows = $db->loadAssocList();
        return is_array($rows) ? $rows : array();
    }

    protected static function isLive($r)
    {
        return (int)$r['state'] === 1
            && (string)$r['jr_closed'] === ''
            && (string)$r['jr_suspended'] === '';
    }

    protected static function splitStar($v)
    {
        $out = array();
        foreach (explode('*', (string)$v) as $p) { $p = trim($p); if ($p !== '') $out[] = $p; }
        return $out;
    }

    /**
     * Build the full payload from the live database. Pure read.
     */
    public static function buildPayload()
    {
        $db = JFactory::getDbo();
        $labels = self::regulatorLabels();
        $rows = self::listings();

        // ---- totals + declared map ----------------------------------------
        $tot = array('listed' => 0, 'live' => 0, 'closed' => 0, 'suspended' => 0, 'unpublished' => 0,
                     'blacklisted_live' => 0, 'multi_licence' => 0, 'blank' => 0);
        $declared = array(); $declaredEver = array();
        $live = array();   // contentid => row
        $pairs = array('uk_malta' => 0, 'malta_curacao' => 0, 'uk_only' => 0);
        $blacklistedByReg = array();
        foreach ($rows as $r) {
            $tot['listed']++;
            $parts = self::splitStar($r['jr_jurisdiction']);
            foreach ($parts as $p) $declaredEver[$p] = isset($declaredEver[$p]) ? $declaredEver[$p] + 1 : 1;
            if ((string)$r['jr_closed'] !== '') { $tot['closed']++; continue; }
            if ((string)$r['jr_suspended'] !== '') { $tot['suspended']++; continue; }
            if ((int)$r['state'] !== 1) { $tot['unpublished']++; continue; }
            $tot['live']++;
            $live[(int)$r['contentid']] = $r;
            if ((string)$r['jr_blacklisted'] !== '') {
                $tot['blacklisted_live']++;
                foreach ($parts as $p) $blacklistedByReg[$p] = isset($blacklistedByReg[$p]) ? $blacklistedByReg[$p] + 1 : 1;
            }
            if (empty($parts)) { $tot['blank']++; continue; }
            if (count($parts) > 1) $tot['multi_licence']++;
            foreach ($parts as $p) $declared[$p] = isset($declared[$p]) ? $declared[$p] + 1 : 1;
            $hasUk = in_array('unitedkingdom', $parts, true); $hasMt = in_array('malta', $parts, true); $hasCw = in_array('curacao', $parts, true);
            if ($hasUk && $hasMt) $pairs['uk_malta']++;
            if ($hasMt && $hasCw) $pairs['malta_curacao']++;
            if ($hasUk && count($parts) === 1) $pairs['uk_only']++;
        }
        fec_php5_arsort($declared);
        $declaredList = array();
        foreach ($declared as $k => $n) {
            $declaredList[] = array('key' => $k, 'label' => isset($labels[$k]) ? $labels[$k] : ucwords(str_replace('-', ' ', $k)),
                                    'count' => (int)$n, 'ever' => isset($declaredEver[$k]) ? (int)$declaredEver[$k] : (int)$n);
        }

        // ---- verified layer (Atlas) --------------------------------------
        $links = array();
        try {
            $db->setQuery(
                "SELECT bl.contentid, bl.entity_id, bl.confidence AS link_conf, e.name AS entity, e.jurisdiction AS ej, e.status AS estatus
                   FROM #__atlas_brand_links bl
                   JOIN #__atlas_entities e ON e.id = bl.entity_id
                  WHERE bl.relation_type = 'operator' AND bl.date_to IS NULL"
            );
            $x = $db->loadAssocList(); if (is_array($x)) $links = $x;
        } catch (Exception $e) { $links = array(); }
        $lic = array();
        try {
            $db->setQuery("SELECT id, entity_id, regulator, licence_number, licence_type, status, confidence, valid_from FROM #__atlas_licences");
            $x = $db->loadAssocList(); if (is_array($x)) $lic = $x;
        } catch (Exception $e) { $lic = array(); }
        $licByEntity = array();
        foreach ($lic as $l) { $licByEntity[(int)$l['entity_id']][] = $l; }

        $verifiedBrands = array();   // contentid => array(entity, ej, licences[])
        foreach ($links as $l) {
            $cid = (int)$l['contentid'];
            if (!isset($live[$cid])) continue;
            if (isset($verifiedBrands[$cid])) continue; // first current operator link wins
            $verifiedBrands[$cid] = array('entity_id' => (int)$l['entity_id'], 'entity' => (string)$l['entity'],
                                          'ej' => (string)$l['ej'], 'licences' => isset($licByEntity[(int)$l['entity_id']]) ? $licByEntity[(int)$l['entity_id']] : array());
        }

        // group: family + bucket → brands, operators
        $groups = array(); $opsSeen = array(); $licSeen = array();
        $curacao = array('confirmed_brands' => 0, 'confirmed_ops' => array(), 'pending_brands' => 0, 'pending_ops' => array(), 'ghost_records' => 0, 'ghost_entities' => array(), 'grant_min' => '', 'grant_max' => '');
        $mislabelled = array();
        $byFamilyBrands = array(); // family => count of verified live brands with a current (non-expired) licence of that family
        foreach ($verifiedBrands as $cid => $vb) {
            $opsSeen[$vb['entity_id']] = true;
            $declaredParts = self::splitStar($live[$cid]['jr_jurisdiction']);
            if (empty($vb['licences'])) {
                $k = 'none|none';
                if (!isset($groups[$k])) $groups[$k] = array('family' => 'none', 'label' => 'Operator identified, no licence on record', 'bucket' => 'none', 'brands' => 0, 'ops' => array());
                $groups[$k]['brands']++; $groups[$k]['ops'][$vb['entity_id']] = $vb['entity'];
                continue;
            }
            $familiesHere = array();
            foreach ($vb['licences'] as $l) {
                $licSeen[(int)$l['id']] = true;
                list($fam, $flabel) = self::regulatorFamily($l['regulator']);
                $bucket = self::licenceBucket($l['status'], $l['confidence']);
                $k = $fam . '|' . $bucket;
                if (!isset($groups[$k])) $groups[$k] = array('family' => $fam, 'label' => $flabel, 'bucket' => $bucket, 'brands' => 0, 'ops' => array());
                $groups[$k]['brands']++; $groups[$k]['ops'][$vb['entity_id']] = $vb['entity'];
                if ($bucket !== 'expired') $familiesHere[$fam] = true;
                if ($fam === 'curacao') {
                    if ($bucket === 'confirmed') { $curacao['confirmed_brands']++; $curacao['confirmed_ops'][$vb['entity_id']] = true; }
                    elseif ($bucket === 'pending') { $curacao['pending_brands']++; $curacao['pending_ops'][$vb['entity_id']] = true; }
                    $vf = isset($l['valid_from']) ? (string)$l['valid_from'] : '';
                    if ($vf !== '' && $vf !== '0000-00-00') {
                        if ($curacao['grant_min'] === '' || strcmp($vf, $curacao['grant_min']) < 0) $curacao['grant_min'] = $vf;
                        if ($curacao['grant_max'] === '' || strcmp($vf, $curacao['grant_max']) > 0) $curacao['grant_max'] = $vf;
                    }
                }
            }
            foreach (array_keys($familiesHere) as $fam) $byFamilyBrands[$fam] = isset($byFamilyBrands[$fam]) ? $byFamilyBrands[$fam] + 1 : 1;
            // mislabelled: verified current family not among declared slugs (family keys match jr slugs for the main regulators)
            $map = array('curacao' => 'curacao', 'anjouan' => 'governmentofanjuan', 'tobique' => 'tobique', 'kahnawake' => 'kahnawake', 'malta' => 'malta', 'unitedkingdom' => 'unitedkingdom');
            foreach (array_keys($familiesHere) as $fam) {
                if (!isset($map[$fam])) continue;
                if (!in_array($map[$fam], $declaredParts, true)) {
                    $mislabelled[] = array('contentid' => $cid, 'title' => (string)$live[$cid]['title'], 'declared' => $declaredParts,
                                           'verified' => $fam, 'entity' => $vb['entity']);
                }
            }
        }
        // ghost sub-licences: expired historic records across ALL entities (not only live-linked)
        foreach ($lic as $l) {
            list($fam, ) = self::regulatorFamily($l['regulator']);
            if ($fam === 'curacao-historic' && self::licenceBucket($l['status'], $l['confidence']) === 'expired') {
                $curacao['ghost_records']++; $curacao['ghost_entities'][(int)$l['entity_id']] = true;
            }
        }
        $groupList = array();
        $order = array('confirmed' => 0, 'pending' => 1, 'expired' => 2, 'none' => 3);
        foreach ($groups as $g) {
            $groupList[] = array('family' => $g['family'], 'label' => $g['label'], 'bucket' => $g['bucket'],
                                 'brands' => (int)$g['brands'], 'operators' => count($g['ops']), 'operator_names' => array_values($g['ops']));
        }
        fec_php5_usort($groupList, function ($a, $b) use ($order) {
            if ($a['brands'] !== $b['brands']) return ($a['brands'] > $b['brands']) ? -1 : 1;
            $oa = isset($order[$a['bucket']]) ? $order[$a['bucket']] : 9; $ob = isset($order[$b['bucket']]) ? $order[$b['bucket']] : 9;
            if ($oa !== $ob) return ($oa < $ob) ? -1 : 1;
            return strcmp($a['label'], $b['label']);
        });

        // ---- geography ---------------------------------------------------
        $geo = array(); $geoOps = array();
        foreach ($verifiedBrands as $cid => $vb) {
            $j = ($vb['ej'] !== '') ? strtolower($vb['ej']) : '?';
            if (!isset($geo[$j])) $geo[$j] = array('jurisdiction' => $j, 'operators' => 0, 'brands' => 0);
            $geo[$j]['brands']++;
            if (!isset($geoOps[$j][$vb['entity_id']])) { $geoOps[$j][$vb['entity_id']] = true; $geo[$j]['operators']++; }
        }
        $geoList = array_values($geo);
        fec_php5_usort($geoList, function ($a, $b) { return ($a['brands'] === $b['brands']) ? 0 : (($a['brands'] > $b['brands']) ? -1 : 1); });
        $shells = array('total' => 0, 'by_jurisdiction' => array());
        try {
            $db->setQuery("SELECT COALESCE(jurisdiction, '?') AS j, COUNT(*) AS n FROM #__atlas_entities WHERE entity_type = 'payment_shell' GROUP BY j");
            $x = $db->loadAssocList();
            if (is_array($x)) foreach ($x as $s) { $shells['total'] += (int)$s['n']; $shells['by_jurisdiction'][strtolower((string)$s['j'])] = (int)$s['n']; }
        } catch (Exception $e) {}

        // ---- no-regulator buckets ----------------------------------------
        $dv = function ($k) use ($declared) { return isset($declared[$k]) ? (int)$declared[$k] : 0; };
        $costaricaOwners = array();
        foreach ($live as $r) {
            if (in_array('costarica', self::splitStar($r['jr_jurisdiction']), true)) {
                $o = trim((string)$r['jr_owner']); $costaricaOwners[$o] = isset($costaricaOwners[$o]) ? $costaricaOwners[$o] + 1 : 1;
            }
        }
        fec_php5_arsort($costaricaOwners);
        $noReg = array(
            'not_regulated'      => $dv('not-regulated'),
            'costarica'          => $dv('costarica'),
            'costarica_top_group'=> !empty($costaricaOwners) ? (int)reset($costaricaOwners) : 0,
            'total'              => $dv('not-regulated') + $dv('costarica'),
            'nl_antilles'        => $dv('netherlandsantilles'),
            'mwali'              => $dv('mwali'),
            'blacklisted_live'   => (int)$tot['blacklisted_live'],
            'blacklisted_curacao'=> isset($blacklistedByReg['curacao']) ? (int)$blacklistedByReg['curacao'] : 0,
        );

        // ---- rails -------------------------------------------------------
        $verifiedFam = function ($fam) use ($byFamilyBrands) { return isset($byFamilyBrands[$fam]) ? (int)$byFamilyBrands[$fam] : 0; };
        $opsByFamBucket = function ($fam, $bucket) use ($groupList) { foreach ($groupList as $g) if ($g['family'] === $fam && $g['bucket'] === $bucket) return (int)$g['operators']; return 0; };
        $misByFam = array();
        foreach ($mislabelled as $m) $misByFam[$m['verified']] = isset($misByFam[$m['verified']]) ? $misByFam[$m['verified']] + 1 : 1;
        $rails = array(
            'tobique' => array('declared' => $dv('tobique'), 'verified' => $verifiedFam('tobique'),
                               'operators' => $opsByFamBucket('tobique', 'confirmed') + $opsByFamBucket('tobique', 'pending')),
            'anjouan' => array('declared' => $dv('governmentofanjuan'), 'verified' => $verifiedFam('anjouan'),
                               'operators_confirmed' => $opsByFamBucket('anjouan', 'confirmed'), 'operators_pending' => $opsByFamBucket('anjouan', 'pending'),
                               'mislabelled' => isset($misByFam['anjouan']) ? (int)$misByFam['anjouan'] : 0),
        );
        $curacaoOut = array(
            'declared'          => $dv('curacao'),
            'verified_confirmed'=> (int)$curacao['confirmed_brands'],
            'verified_pending'  => (int)$curacao['pending_brands'],
            'operators_confirmed' => count($curacao['confirmed_ops']),
            'operators_pending' => count($curacao['pending_ops']),
            'unverified_claims' => max(0, $dv('curacao') - (int)$curacao['confirmed_brands'] - (int)$curacao['pending_brands']),
            'ghost_records'     => (int)$curacao['ghost_records'],
            'ghost_entities'    => count($curacao['ghost_entities']),
            'grant_min'         => $curacao['grant_min'],
            'grant_max'         => $curacao['grant_max'],
            'mislabelled'       => isset($misByFam['curacao']) ? (int)$misByFam['curacao'] : 0,
        );

        return array(
            'as_of'      => gmdate('Y-m-d'),
            'frozen_at'  => gmdate('Y-m-d H:i:s'),
            'totals'     => $tot,
            'declared'   => $declaredList,
            'pairs'      => $pairs,
            'verified'   => array('brands' => count($verifiedBrands), 'operators' => count($opsSeen), 'licences' => count($licSeen), 'groups' => $groupList),
            'curacao'    => $curacaoOut,
            'rails'      => $rails,
            'geography'  => array('operators' => $geoList, 'payment_shells' => $shells),
            'no_regulator' => $noReg,
            'mislabelled'=> $mislabelled,
        );
    }

    // =====================================================================
    // Atlas events (the quarter's movements)
    // =====================================================================

    public static function events($from, $to, $publishedOnly = true)
    {
        $db = JFactory::getDbo();
        try {
            $db->setQuery(
                "SELECT ev.id, ev.event_date, ev.event_type, ev.entity_id, ev.contentid, ev.title, ev.description, ev.confidence, ev.published,
                        e.name AS entity_name, c.title AS brand_title
                   FROM #__atlas_events ev
                   LEFT JOIN #__atlas_entities e ON e.id = ev.entity_id
                   LEFT JOIN #__content c ON c.id = ev.contentid
                  WHERE ev.event_date >= " . $db->quote(substr((string)$from, 0, 10)) . " AND ev.event_date <= " . $db->quote(substr((string)$to, 0, 10))
                  . ($publishedOnly ? " AND ev.published = 1" : "") . "
                  ORDER BY ev.event_date ASC, ev.id ASC"
            );
            $rows = $db->loadAssocList();
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }

    public static function eventTypeLabel($t)
    {
        $m = array('registered' => 'Company registered', 'licence_granted' => 'Licence granted', 'licence_revoked' => 'Licence revoked or lapsed',
                   'licence_migration' => 'Licence migration', 'entity_migration' => 'Operator migration', 'struck_off' => 'Company struck off',
                   'rebrand' => 'Rebrand', 'address_change' => 'Address change', 'ownership_change' => 'Ownership change', 'other' => 'Other');
        return isset($m[$t]) ? $m[$t] : ucfirst(str_replace('_', ' ', (string)$t));
    }

    // =====================================================================
    // Editions
    // =====================================================================

    public static function editions($includeDrafts = false)
    {
        $db = JFactory::getDbo();
        try {
            $db->setQuery(
                "SELECT id, edition, number, status, frozen_at, published_at, data_as_of, live_total, verified_total,
                        curacao_declared, no_regulator_total, author_slug, reviewer_slug, modified_at
                   FROM #__fec_licence_watch_editions"
                 . ($includeDrafts ? "" : " WHERE status = 'published'") . "
                  ORDER BY edition DESC"
            );
            $rows = $db->loadAssocList();
            return is_array($rows) ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }

    public static function edition($ym, $includeDrafts = false)
    {
        $ym = substr(preg_replace('/[^0-9-]/', '', (string)$ym), 0, 7);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) return null;
        $db = JFactory::getDbo();
        try {
            $db->setQuery("SELECT * FROM #__fec_licence_watch_editions WHERE edition = " . $db->quote($ym) . ($includeDrafts ? "" : " AND status = 'published'"), 0, 1);
            $row = $db->loadAssoc();
        } catch (Exception $e) {
            return null;
        }
        if (!$row) return null;
        $row['payload']  = json_decode((string)$row['payload'], true);
        $row['diff']     = ($row['diff'] !== null && $row['diff'] !== '') ? json_decode((string)$row['diff'], true) : null;
        $row['sections'] = ($row['sections'] !== null && $row['sections'] !== '') ? json_decode((string)$row['sections'], true) : array();
        $row['events']   = ($row['events'] !== null && $row['events'] !== '') ? json_decode((string)$row['events'], true) : array();
        if (!is_array($row['payload'])) return null;
        if (!is_array($row['sections'])) $row['sections'] = array();
        if (!is_array($row['events'])) $row['events'] = array();
        return $row;
    }

    public static function latest($includeDrafts = false)
    {
        $all = self::editions($includeDrafts);
        return !empty($all) ? self::edition($all[0]['edition'], $includeDrafts) : null;
    }

    /**
     * Freeze the live register into a DRAFT edition (or refresh an existing
     * draft's payload with $force; published editions are never overwritten).
     */
    public static function snapshot($ym = null, $force = false)
    {
        $db = JFactory::getDbo();
        $ym = ($ym !== null && $ym !== '') ? substr(preg_replace('/[^0-9-]/', '', (string)$ym), 0, 7) : gmdate('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) return array('ok' => false, 'error' => 'bad edition ' . $ym);

        $existing = self::edition($ym, true);
        if ($existing && $existing['status'] === 'published') {
            return array('ok' => false, 'error' => 'edition ' . $ym . ' is published; unpublish it first');
        }
        if ($existing && !$force) {
            return array('ok' => true, 'edition' => $ym, 'number' => (int)$existing['number'], 'created' => false, 'note' => 'draft exists; pass force=1 to refreeze');
        }

        // previous = newest PUBLISHED edition strictly before this one
        $prev = null;
        foreach (self::editions(false) as $e) {
            if (strcmp($e['edition'], $ym) < 0) { $prev = self::edition($e['edition']); break; }
        }
        $number = $existing ? (int)$existing['number'] : ($prev ? (int)$prev['number'] + 1 : 1);

        $payload = self::buildPayload();
        $from = $prev ? substr((string)$prev['frozen_at'], 0, 10) : date('Y-m-d', strtotime($ym . '-01 -3 months'));
        $events = self::events($from, gmdate('Y-m-d'), true);
        $diff = $prev ? self::diffPayloads($prev['payload'], $payload, $events) : null;
        $now = gmdate('Y-m-d H:i:s');

        $sections = $existing ? $existing['sections'] : array('overview' => '', 'quarter' => '', 'bottom_line' => '');
        $cols = array(
            'edition' => $db->quote($ym), 'number' => (int)$number, 'status' => $db->quote('draft'),
            'frozen_at' => $db->quote($now), 'published_at' => 'NULL',
            'data_as_of' => $db->quote($payload['as_of']),
            'live_total' => (int)$payload['totals']['live'], 'verified_total' => (int)$payload['verified']['brands'],
            'curacao_declared' => (int)$payload['curacao']['declared'], 'no_regulator_total' => (int)$payload['no_regulator']['total'],
            'payload' => $db->quote(json_encode($payload)), 'diff' => ($diff !== null) ? $db->quote(json_encode($diff)) : 'NULL',
            'events' => $db->quote(json_encode($events)), 'sections' => $db->quote(json_encode($sections)),
            'author_slug' => $db->quote($existing && !empty($existing['author_slug']) ? $existing['author_slug'] : 'ethan-walker'),
            'reviewer_slug' => $db->quote($existing && !empty($existing['reviewer_slug']) ? $existing['reviewer_slug'] : 'david-romano'),
            'created_at' => $db->quote($existing ? $existing['created_at'] : $now), 'modified_at' => $db->quote($now),
        );
        try {
            if ($existing) {
                $db->setQuery("DELETE FROM #__fec_licence_watch_editions WHERE id = " . (int)$existing['id']); $db->query();
            }
            $db->setQuery("INSERT INTO #__fec_licence_watch_editions (" . implode(', ', array_keys($cols)) . ") VALUES (" . implode(', ', array_values($cols)) . ")");
            $db->query();
        } catch (Exception $e) {
            return array('ok' => false, 'error' => 'insert failed: ' . $e->getMessage());
        }
        return array('ok' => true, 'edition' => $ym, 'number' => $number, 'created' => true, 'refrozen' => (bool)$existing,
                     'live' => (int)$payload['totals']['live'], 'verified' => (int)$payload['verified']['brands'],
                     'events' => count($events), 'diff' => $diff, 'page' => self::editionPath($ym),
                     'sef' => self::registerSef($ym));
    }

    /**
     * 2.7.62: sh404SEF decodes only URLs it knows, and it never anchors ours to
     * the menu item; the edition path is registered by hand at every freeze,
     * publish and (removed at) draft deletion. No-op without sh404SEF.
     */
    public static function registerSef($ym)
    {
        $h = JPATH_SITE . '/components/com_authors/helpers/sef.php';
        if (!class_exists('AuthorsHelperSef') && is_readable($h)) require_once $h;
        if (!class_exists('AuthorsHelperSef')) return 'skipped: helper missing';
        return AuthorsHelperSef::registerEdition(self::editionPath($ym), 'licence-watch', $ym);
    }

    public static function unregisterSef($ym)
    {
        $h = JPATH_SITE . '/components/com_authors/helpers/sef.php';
        if (!class_exists('AuthorsHelperSef') && is_readable($h)) require_once $h;
        if (!class_exists('AuthorsHelperSef')) return 'skipped: helper missing';
        return AuthorsHelperSef::unregister(self::editionPath($ym));
    }

    public static function saveSections($ym, $sections, $authorSlug = null, $reviewerSlug = null)
    {
        $db = JFactory::getDbo();
        $ex = self::edition($ym, true);
        if (!$ex) return array('ok' => false, 'error' => 'not_found');
        $clean = array();
        foreach (array('overview', 'quarter', 'bottom_line', 'title') as $k) {
            $clean[$k] = isset($sections[$k]) ? (string)$sections[$k] : (isset($ex['sections'][$k]) ? (string)$ex['sections'][$k] : '');
        }
        $set = array("sections = " . $db->quote(json_encode($clean)), "modified_at = " . $db->quote(gmdate('Y-m-d H:i:s')));
        if ($authorSlug !== null)   $set[] = "author_slug = " . $db->quote(preg_replace('/[^a-z0-9-]/', '', (string)$authorSlug));
        if ($reviewerSlug !== null) $set[] = "reviewer_slug = " . $db->quote(preg_replace('/[^a-z0-9-]/', '', (string)$reviewerSlug));
        try {
            $db->setQuery("UPDATE #__fec_licence_watch_editions SET " . implode(', ', $set) . " WHERE id = " . (int)$ex['id']);
            $db->query();
        } catch (Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage());
        }
        return array('ok' => true, 'sections' => $clean);
    }

    public static function setStatus($ym, $status)
    {
        $db = JFactory::getDbo();
        $ex = self::edition($ym, true);
        if (!$ex) return array('ok' => false, 'error' => 'not_found');
        $status = ($status === 'published') ? 'published' : 'draft';
        $now = gmdate('Y-m-d H:i:s');
        $set = array("status = " . $db->quote($status), "modified_at = " . $db->quote($now));
        if ($status === 'published') {
            $set[] = "published_at = " . ($ex['published_at'] ? $db->quote($ex['published_at']) : $db->quote($now));
            // the sequence number follows publication order
            $n = 0; foreach (self::editions(false) as $e) { if ($e['edition'] !== $ym && strcmp($e['edition'], $ym) < 0) $n = max($n, (int)$e['number']); }
            $set[] = "number = " . ($n + 1);
        }
        try {
            $db->setQuery("UPDATE #__fec_licence_watch_editions SET " . implode(', ', $set) . " WHERE id = " . (int)$ex['id']);
            $db->query();
        } catch (Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage());
        }
        $out = array('ok' => true, 'status' => $status);
        if ($status === 'published') $out['sef'] = self::registerSef($ym);
        return $out;
    }

    public static function deleteDraft($ym)
    {
        $db = JFactory::getDbo();
        $ex = self::edition($ym, true);
        if (!$ex) return array('ok' => false, 'error' => 'not_found');
        if ($ex['status'] === 'published') return array('ok' => false, 'error' => 'published editions are not deleted; unpublish first');
        try { $db->setQuery("DELETE FROM #__fec_licence_watch_editions WHERE id = " . (int)$ex['id']); $db->query(); }
        catch (Exception $e) { return array('ok' => false, 'error' => $e->getMessage()); }
        return array('ok' => true, 'sef' => self::unregisterSef($ym));
    }

    // =====================================================================
    // Diff
    // =====================================================================

    public static function diffPayloads($prev, $cur, $events)
    {
        $pd = array(); foreach ((array)$prev['declared'] as $d) $pd[$d['key']] = (int)$d['count'];
        $cd = array(); foreach ((array)$cur['declared'] as $d)  $cd[$d['key']] = (int)$d['count'];
        $declared = array();
        foreach (array_unique(array_merge(array_keys($pd), array_keys($cd))) as $k) {
            $a = isset($pd[$k]) ? $pd[$k] : 0; $b = isset($cd[$k]) ? $cd[$k] : 0;
            if ($a !== $b) $declared[] = array('key' => $k, 'from' => $a, 'to' => $b, 'delta' => $b - $a);
        }
        fec_php5_usort($declared, function ($x, $y) { $ax = abs($x['delta']); $ay = abs($y['delta']); return ($ax === $ay) ? 0 : (($ax > $ay) ? -1 : 1); });
        $byType = array();
        foreach ((array)$events as $ev) { $t = (string)$ev['event_type']; $byType[$t] = isset($byType[$t]) ? $byType[$t] + 1 : 1; }
        return array(
            'prev_edition'     => isset($prev['frozen_at']) ? substr($prev['frozen_at'], 0, 10) : '',
            'live_delta'       => (int)$cur['totals']['live'] - (int)$prev['totals']['live'],
            'verified_delta'   => (int)$cur['verified']['brands'] - (int)$prev['verified']['brands'],
            'curacao_confirmed_delta' => (int)$cur['curacao']['verified_confirmed'] - (int)$prev['curacao']['verified_confirmed'],
            'curacao_unverified_delta' => (int)$cur['curacao']['unverified_claims'] - (int)$prev['curacao']['unverified_claims'],
            'no_regulator_delta' => (int)$cur['no_regulator']['total'] - (int)$prev['no_regulator']['total'],
            'declared'         => $declared,
            'events_total'     => count((array)$events),
            'events_by_type'   => $byType,
        );
    }

    // =====================================================================
    // Desk helpers used by the dashboard API
    // =====================================================================

    public static function entitiesList()
    {
        $db = JFactory::getDbo();
        try {
            $db->setQuery("SELECT id, name, entity_type, jurisdiction, status FROM #__atlas_entities ORDER BY name ASC");
            $r = $db->loadAssocList(); return is_array($r) ? $r : array();
        } catch (Exception $e) { return array(); }
    }

    public static function addEvent($in, $userId)
    {
        $db = JFactory::getDbo();
        $types = array('registered', 'licence_granted', 'licence_revoked', 'licence_migration', 'entity_migration', 'struck_off', 'rebrand', 'address_change', 'ownership_change', 'other');
        $type = isset($in['event_type']) ? (string)$in['event_type'] : 'other';
        if (!in_array($type, $types, true)) $type = 'other';
        $date = isset($in['event_date']) ? trim((string)$in['event_date']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return array('ok' => false, 'error' => 'bad_date');
        $title = isset($in['title']) ? trim((string)$in['title']) : '';
        if ($title === '') return array('ok' => false, 'error' => 'title_required');
        $conf = isset($in['confidence']) ? (string)$in['confidence'] : 'single_source';
        if (!in_array($conf, array('confirmed', 'probable', 'single_source'), true)) $conf = 'single_source';
        $entityId = isset($in['entity_id']) ? (int)$in['entity_id'] : 0;
        $cid = isset($in['contentid']) ? (int)$in['contentid'] : 0;
        $desc = isset($in['description']) ? trim((string)$in['description']) : '';
        $pub = !empty($in['published']) ? 1 : 0;
        $now = gmdate('Y-m-d H:i:s');
        try {
            $db->setQuery(
                "INSERT INTO #__atlas_events (event_date, event_type, entity_id, contentid, title, description, confidence, published, created, modified) VALUES ("
                . $db->quote($date) . ", " . $db->quote($type) . ", " . ($entityId > 0 ? $entityId : 'NULL') . ", " . ($cid > 0 ? $cid : 'NULL') . ", "
                . $db->quote($title) . ", " . ($desc !== '' ? $db->quote($desc) : 'NULL') . ", " . $db->quote($conf) . ", " . $pub . ", " . $db->quote($now) . ", " . $db->quote($now) . ")"
            );
            $db->query();
            $id = (int)$db->insertid();
        } catch (Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage());
        }
        return array('ok' => true, 'id' => $id);
    }

    public static function setEventPublished($id, $published)
    {
        $db = JFactory::getDbo();
        try {
            $db->setQuery("UPDATE #__atlas_events SET published = " . ($published ? 1 : 0) . ", modified = " . $db->quote(gmdate('Y-m-d H:i:s')) . " WHERE id = " . (int)$id);
            $db->query();
        } catch (Exception $e) { return array('ok' => false, 'error' => $e->getMessage()); }
        return array('ok' => true);
    }

    public static function deleteEvent($id)
    {
        $db = JFactory::getDbo();
        try { $db->setQuery("DELETE FROM #__atlas_events WHERE id = " . (int)$id); $db->query(); }
        catch (Exception $e) { return array('ok' => false, 'error' => $e->getMessage()); }
        return array('ok' => true);
    }

    // =====================================================================
    // Formatting
    // =====================================================================

    public static function fmtDate($ymd, $long = false)
    {
        $ymd = trim((string)$ymd);
        if ($ymd === '' || $ymd === '0000-00-00') return '';
        $ts = strtotime(substr($ymd, 0, 10));
        if (!$ts) return $ymd;
        return date($long ? 'j F Y' : 'j M Y', $ts);
    }

    public static function pct($part, $whole)
    {
        $whole = (int)$whole; if ($whole <= 0) return '0';
        return (string)(int)round(100.0 * (int)$part / $whole);
    }

    /** Country code → name for the geography paragraph. */
    public static function countryName($cc)
    {
        $m = array('cw' => 'Cura&ccedil;ao', 'cy' => 'Cyprus', 'km' => 'Comoros', 'bz' => 'Belize', 'cr' => 'Costa Rica', 'ee' => 'Estonia', 'mt' => 'Malta',
                   'gb' => 'United Kingdom', 'gi' => 'Gibraltar', 'im' => 'Isle of Man', 'ca' => 'Canada', 'pa' => 'Panama', 'ag' => 'Antigua and Barbuda', '?' => 'unverified');
        $cc = strtolower((string)$cc);
        return isset($m[$cc]) ? $m[$cc] : strtoupper($cc);
    }
}
