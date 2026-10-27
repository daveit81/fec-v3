<?php
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * FEC Welcome Package Index — read-only model (V2.7.74).
 *
 * One row per welcome package shown on the site, priced the way the public
 * band prices it: the calculation core components/com_bonuses/helpers/
 * fec_fxrate.php (loaded guarded) over the package tables
 * (#__fec_bonus_packages + #__fec_bonus_package_legs), real-row legs read
 * through #__bonuses, virtual legs from the side table.
 *
 * Counted exactly like the render and the methodology stats: status active,
 * opener row active (or none), at least two deposits, casino not closed,
 * blacklisted or suspended (jReviews flags, the rule of the listings and of
 * fec_should_noindex_casino()). A casino under review stays listed, tagged. "Priced" = every match
 * and cap stated, so an effective rate exists; unpriced packages are listed
 * apart with "not stated", never estimated.
 *
 * Nothing here ranks: rows come out sorted by effective match for the first
 * paint and the page lets the reader re-sort. No score, no stars.
 *
 * PHP 5.6 web runtime: no return types, no scalar hints, no ??, no <=>.
 */
class AuthorsModelPackageindex extends JModel
{
    /** Public path of the index (menu item under bonuses). */
    const INDEX_PATH = '/bonuses/welcome-packages';

    /** Path of the method page every number links to. */
    const METHOD_PATH = '/methodology/fxrate';

    /**
     * Everything the page needs.
     *
     * Returns:
     *   live        bool   core loaded and query ran
     *   priced      array  rows with an effective rate, effective desc
     *   unpriced    array  rows awaiting terms, brand asc
     *   stats       array  packages_total, packages_priced, casinos, legs_total,
     *                      median_advertised, median_effective, median_ratio,
     *                      below_100, below_50, first_beats, max_ratio
     *   data_as_of  string YYYY-MM-DD, latest package/leg change
     *
     * Row shape (both lists):
     *   pkg_id, cid, brand, title, flagged (bool), geo_blocked (bool, for the
 *   country passed in: the opener row country_ok / country_no lists and the
 *   casino jr_restrictedcountries, the rule of fec_decide_state()),
 *   review_url, url (opener bonus page), deposits,
     *   title_full (the stored title, "Welcome Package" when generic), opener_type,
     *   track_label (title, or the track by opener type when generic and linked, else ''), alt_of, alt_titles
     *   (titles of the packages of the same casino linked as alternatives, either direction),
     *   legs_stated, spins_legs (positions of spins-only legs: counted, never
     *   priced, outside the four numbers), cashout_capped (positions of the legs
     *   that state a maximum cashout: reported, never priced), symbol, advertised_pct, advertised_text, cap (float|null),
     *   spins, deposit, bonus, effective_pct, turnover, first_pct, first_cap,
     *   ratio, priced (bool), flags (array), modified
     */
    public static function data($countrycode = '')
    {
        $cc  = strtolower(trim((string)$countrycode));
        $out = array('live' => false, 'priced' => array(), 'unpriced' => array(), 'stats' => self::emptyStats(), 'data_as_of' => '', 'cc' => $cc);

        if (!self::loadCore()) return $out;

        try {
            $db  = JFactory::getDbo();
            $sql = 'SELECT p.pkg_id, p.cid, p.opener_bid, p.title, p.alt_of, p.headline, p.currency AS pkg_currency, p.deposits, p.spins_total, p.modified AS pmod,'
                 . ' ob.status AS opener_status, ob.brand AS opener_brand, ob.type AS opener_type, ob.country_ok, ob.country_no,'
                 . ' c.title AS casino_title, c.alias AS casino_alias,'
                 . ' jc.jr_closed, jc.jr_blacklisted, jc.jr_suspended, jc.jr_flag, jc.jr_restrictedcountries,'
                 . ' l.position, l.bid, l.spins,'
                 . ' CASE WHEN l.bid IS NULL THEN l.pct     ELSE b.bonus     END AS pct,'
                 . ' CASE WHEN l.bid IS NULL THEN l.cap     ELSE b.max_bonus END AS cap,'
                 . ' CASE WHEN l.bid IS NULL THEN l.min_dep ELSE b.min_dep   END AS min_dep,'
                 . ' CASE WHEN l.bid IS NULL THEN l.max_dep ELSE b.max_dep   END AS max_dep,'
                 . ' CASE WHEN l.bid IS NULL THEN l.wr      ELSE b.wr        END AS wr,'
                 . ' CASE WHEN l.bid IS NULL THEN l.wr_meth ELSE b.wr_meth   END AS wr_meth,'
                 . ' CASE WHEN l.bid IS NULL THEN l.max_cashout      ELSE b.max_cashout      END AS max_cashout,'
                 . ' CASE WHEN l.bid IS NULL THEN l.max_cashout_kind ELSE b.max_cashout_kind END AS max_cashout_kind,'
                 . ' b.type AS row_type, b.currency AS row_currency, b.modified AS bmod'
                 . ' FROM ' . $db->quoteName('#__fec_bonus_packages') . ' p'
                 . ' INNER JOIN ' . $db->quoteName('#__fec_bonus_package_legs') . ' l ON l.pkg_id = p.pkg_id'
                 . ' LEFT JOIN ' . $db->quoteName('#__bonuses') . ' b ON b.bid = l.bid'
                 . ' LEFT JOIN ' . $db->quoteName('#__bonuses') . ' ob ON ob.bid = p.opener_bid'
                 . ' LEFT JOIN ' . $db->quoteName('#__content') . ' c ON c.id = p.cid'
                 . ' LEFT JOIN ' . $db->quoteName('#__jreviews_content') . ' jc ON jc.contentid = p.cid'
                 . ' WHERE p.status = ' . $db->quote('active')
                 . ' ORDER BY p.pkg_id ASC, l.position ASC, l.leg_id ASC';
            $db->setQuery($sql);
            $rows = $db->loadAssocList();
            if ($db->getErrorNum()) {
                error_log('AuthorsModelPackageindex::data: ' . $db->getErrorMsg());
                return $out;
            }
        } catch (Exception $e) {
            return $out;
        }

        $cmap = self::currencyMap();
        $groups = array();
        foreach ((array)$rows as $r) $groups[(int)$r['pkg_id']][] = $r;

        $priced = array(); $unpriced = array(); $asOf = '';
        $adv = array(); $eff = array(); $ratio = array(); $cids = array();
        $st = self::emptyStats();

        foreach ($groups as $pkgId => $legsRaw) {
            $f = $legsRaw[0];
            $ost = strtolower(trim((string)$f['opener_status']));
            if ($ost !== '' && $ost !== 'active') continue;
            if (self::casinoExcluded($f['jr_closed'], $f['jr_blacklisted'], $f['jr_suspended'])) continue;

            $legs = array(); $spins = 0; $stated = 0; $mod = (string)$f['pmod'];
            foreach ($legsRaw as $r) {
                $isReal = ($r['bid'] !== null && (int)$r['bid'] > 0 && $r['row_type'] !== null);
                $pos    = (int)$r['position'];
                $leg = fec_fxrate_leg(array(
                    'bid' => $isReal ? (int)$r['bid'] : 0, 'cid' => (int)$f['cid'],
                    'type' => $isReal ? (string)$r['row_type'] : ($pos === 1 ? 'su' : $pos . 'd'),
                    'bonus' => $r['pct'], 'max_bonus' => $r['cap'], 'min_dep' => $r['min_dep'], 'max_dep' => $r['max_dep'],
                    'wr' => $r['wr'], 'wr_meth' => $r['wr_meth'],
                    'currency' => ($isReal && $r['row_currency'] !== null) ? (string)$r['row_currency'] : (string)$f['pkg_currency'],
                    'code' => '', 'spins' => (int)$r['spins'],
                    'max_cashout' => isset($r['max_cashout']) ? $r['max_cashout'] : null,
                    'max_cashout_kind' => isset($r['max_cashout_kind']) ? (string)$r['max_cashout_kind'] : '',
                ), $cmap);
                if ($leg['position'] === 0) $leg['position'] = $pos;
                // a stated leg: a match rate WITH its cap (a rate alone cannot be priced), or a spins leg
                if (($leg['pct'] !== null && $leg['cap'] !== null) || (isset($leg['kind']) && $leg['kind'] === 'spins')) $stated++;
                $legs[] = $leg;
                $spins += (int)$r['spins'];
                if ($isReal && (string)$r['bmod'] > $mod) $mod = (string)$r['bmod'];
            }
            $fx = fec_fxrate_package($legs, array('spins' => max((int)$f['spins_total'], $spins)));
            $deposits = max((int)$f['deposits'], $fx['n_legs']);
            if ($fx['n_legs'] > 0) $deposits = max($deposits, (int)$fx['legs'][$fx['n_legs'] - 1]['position']);
            if ($deposits < 2) continue;

            $isPriced = ($fx['effective_pct'] !== null && $fx['advertised_pct'] !== null && $fx['effective_pct'] > 0);
            $sym   = ($fx['symbol'] === 'MIXED') ? '' : $fx['symbol'];
            $brand = trim((string)$f['opener_brand']) !== '' ? trim((string)$f['opener_brand']) : trim((string)$f['casino_title']);
            $alias = trim((string)$f['casino_alias']);
            $first = $fx['first_leg'];

            $advText = fec_fxrate_pct($fx['advertised_pct']);
            if ($fx['advertised_cap'] !== null && $sym !== '') $advText .= ' up to ' . fec_fxrate_money($fx['advertised_cap'], $sym);
            if ($fx['spins'] > 0) $advText .= ' + ' . number_format($fx['spins']) . ' FS';

            $row = array(
                'pkg_id'          => $pkgId,
                'cid'             => (int)$f['cid'],
                'brand'           => $brand,
                'title'           => self::trackTitle((string)$f['title']),
                'title_full'      => (trim((string)$f['title']) !== '') ? trim((string)$f['title']) : 'Welcome Package',
                'opener_type'     => strtolower(trim((string)$f['opener_type'])),
                'track_label'     => self::trackTitle((string)$f['title']),
                'alt_of'          => isset($f['alt_of']) ? (int)$f['alt_of'] : 0,
                'alt_titles'      => array(),
                'flagged'         => (strpos((string)$f['jr_flag'], '*flagged*') !== false),
                'geo_blocked'     => ($cc !== '' && self::geoBlocked($cc, $f['jr_restrictedcountries'], $f['country_ok'], $f['country_no'])),
                'review_url'      => $alias !== '' ? JUri::base(true) . '/online-casinos/' . $alias : '',
                'url'             => self::bonusUrl((int)$f['opener_bid'], (string)$f['opener_type'], (int)$f['cid']),
                'deposits'        => $deposits,
                'legs_stated'     => $stated,
                'spins_legs'      => isset($fx['spins_legs']) ? array_values($fx['spins_legs']) : array(),
                'cashout_capped'  => isset($fx['cashout_capped_legs']) ? array_values($fx['cashout_capped_legs']) : array(),
                // 2.7.90: legs priced at the deposit maximum because the cap
                // needs more than it (core flag cap_unreachable) - the rows
                // where "cap / deposit" is not the effective match, marked in
                // the You deposit column so the reader does not redo the sum.
                'deposit_capped'  => self::depositCappedLegs($fx),
                'symbol'          => $sym,
                'advertised_pct'  => $fx['advertised_pct'],
                'advertised_text' => $advText,
                'cap'             => $fx['advertised_cap'],
                'spins'           => (int)$fx['spins'],
                'deposit'         => $fx['deposit'],
                'bonus'           => $fx['bonus'],
                'effective_pct'   => $fx['effective_pct'],
                'turnover'        => $fx['turnover'],
                'first_pct'       => $first ? $first['pct'] : null,
                'first_cap'       => $first ? $first['cap'] : null,
                'ratio'           => $isPriced ? $fx['advertised_pct'] / $fx['effective_pct'] : null,
                'priced'          => $isPriced,
                'flags'           => $fx['flags'],
                'modified'        => $mod,
            );
            if ($mod !== '' && substr($mod, 0, 10) > $asOf) $asOf = substr($mod, 0, 10);

            $st['packages_total']++;
            $st['legs_total'] += $fx['n_legs'];
            $cids[(int)$f['cid']] = true;

            if ($isPriced) {
                $priced[] = $row;
                $st['packages_priced']++;
                $adv[] = (float)$fx['advertised_pct']; $eff[] = (float)$fx['effective_pct']; $ratio[] = $row['ratio'];
                if ($fx['effective_pct'] < 100) $st['below_100']++;
                if ($fx['effective_pct'] <= 50) $st['below_50']++;
                if ($first && $first['pct'] !== null && $first['pct'] > $fx['effective_pct'] + 0.5) $st['first_beats']++;
            } else {
                $unpriced[] = $row;
            }
        }

        // Alternative tracks: packages linked by alt_of, either direction, both
        // listed here (closed casinos and inactive packages never got this far).
        // A package with the generic title is named by its opener's type when it
        // has to be told apart from another one (High Roller / Crypto / Standard
        // Package); a stored title always wins.
        $linked = array();
        foreach (array($priced, $unpriced) as $list) {
            foreach ($list as $r) {
                if ($r['alt_of'] > 0) { $linked[(int)$r['pkg_id']] = true; $linked[(int)$r['alt_of']] = true; }
            }
        }
        $labels = array();
        foreach (array('priced', 'unpriced') as $which) {
            $list = ($which === 'priced') ? $priced : $unpriced;
            foreach ($list as $i => $r) {
                $label = $r['track_label'];
                if ($label === '' && isset($linked[(int)$r['pkg_id']])) $label = self::trackByOpener($r['opener_type']);
                $labels[(int)$r['pkg_id']] = ($label !== '') ? $label : $r['title_full'];
                if ($which === 'priced') $priced[$i]['track_label'] = $label; else $unpriced[$i]['track_label'] = $label;
            }
        }
        foreach (array('priced', 'unpriced') as $which) {
            $list = ($which === 'priced') ? $priced : $unpriced;
            foreach ($list as $i => $r) {
                $alts = array();
                if ($r['alt_of'] > 0 && isset($labels[$r['alt_of']])) $alts[$r['alt_of']] = $labels[$r['alt_of']];
                foreach (array($priced, $unpriced) as $other) {
                    foreach ($other as $o) {
                        if ((int)$o['alt_of'] === (int)$r['pkg_id'] && (int)$o['pkg_id'] !== (int)$r['pkg_id']) $alts[(int)$o['pkg_id']] = $labels[(int)$o['pkg_id']];
                    }
                }
                if ($alts) {
                    if ($which === 'priced') $priced[$i]['alt_titles'] = array_values($alts);
                    else $unpriced[$i]['alt_titles'] = array_values($alts);
                }
            }
        }

        fec_php5_usort($priced, array('AuthorsModelPackageindex', 'sortEffectiveDesc'));
        fec_php5_usort($unpriced, array('AuthorsModelPackageindex', 'sortBrandAsc'));

        $st['casinos'] = count($cids);
        if ($adv) {
            $st['median_advertised'] = self::median($adv);
            $st['median_effective']  = self::median($eff);
            $st['median_ratio']      = self::median($ratio);
            $st['max_ratio']         = max($ratio);
        }

        $out['live']       = true;
        $out['priced']     = $priced;
        $out['unpriced']   = $unpriced;
        $out['stats']      = $st;
        $out['data_as_of'] = $asOf;
        return $out;
    }

    // ---------------------------------------------------------------------

    protected static function emptyStats()
    {
        return array(
            'packages_total' => 0, 'packages_priced' => 0, 'casinos' => 0, 'legs_total' => 0,
            'median_advertised' => null, 'median_effective' => null, 'median_ratio' => null,
            'below_100' => 0, 'below_50' => 0, 'first_beats' => 0, 'max_ratio' => null,
        );
    }

    /**
     * Casino-level exclusion from the jReviews flags: the core's rule (0.5.1),
     * which is the site listings' rule — any non-empty jr_closed /
     * jr_suspended / jr_blacklisted excludes; a bare "no" or "0" does not.
     * Same rule inline for an older core.
     */
    public static function casinoExcluded($closed, $blacklisted, $suspended)
    {
        if (function_exists('fec_fxrate_casino_excluded')) return fec_fxrate_casino_excluded($closed, $blacklisted, $suspended);
        foreach (array($closed, $suspended, $blacklisted) as $v) {
            $v = strtolower(trim((string)$v));
            if ($v === '') continue;
            $bare = trim($v, "* \t");
            if ($bare === '' || $bare === 'no' || $bare === '0' || $bare === 'none') continue;
            return true;
        }
        return false;
    }

    /**
     * Geo rule of fec_decide_state() in the site lib, for one visitor country
     * (lowercase ISO code) and one opener row: blocked when the casino lists
     * the country as restricted, the bonus lists it in country_no, or the bonus
     * has a country_ok list that omits it. A casino marked "no-restrictions"
     * is never blocked.
     */
    public static function geoBlocked($cc, $restricted, $countryOk, $countryNo)
    {
        $cc = strtolower(trim((string)$cc));
        if ($cc === '') return false;
        $restricted = (string)$restricted; $countryOk = (string)$countryOk; $countryNo = (string)$countryNo;
        if (strpos($restricted, 'no-restrictions') !== false) return false;
        $in = function ($list, $code) { return $list !== '' && strpos($list, '*' . $code . '*') !== false; };
        if ($in($restricted, $cc)) return true;
        if ($in($countryNo, $cc)) return true;
        if ($countryOk !== '' && !$in($countryOk, $cc)) return true;
        return false;
    }

    /** Loads the calculation core once. False = the page shows its empty state. */
    protected static function loadCore()
    {
        if (!function_exists('fec_fxrate_package')) {
            $core = JPATH_SITE . '/components/com_bonuses/helpers/fec_fxrate.php';
            if (is_readable($core)) require_once $core;
        }
        return function_exists('fec_fxrate_package') && function_exists('fec_fxrate_leg') && function_exists('fec_fxrate_money');
    }

    /** The site currency map, decoded to glyphs (same source as the band). */
    protected static function currencyMap()
    {
        static $map = null;
        if ($map !== null) return $map;
        $map = array();
        $src = array();
        if (isset($GLOBALS['currency']) && is_array($GLOBALS['currency'])) {
            $src = $GLOBALS['currency'];
        } else {
            $file = JPATH_SITE . '/components/com_bonuses/helpers/currenciesarray.php';
            if (is_readable($file)) {
                $currency = array();
                include $file;
                if (isset($currency) && is_array($currency)) $src = $currency;
            }
        }
        foreach ($src as $k => $v) $map[strtolower(trim((string)$k))] = html_entity_decode((string)$v, ENT_QUOTES, 'UTF-8');
        return $map;
    }

    /** Details page of the opener row (same route the hub cards use). */
    protected static function bonusUrl($bid, $type, $cid)
    {
        if ($bid <= 0) return '';
        $type = trim($type) !== '' ? $type : 'su';
        try {
            return JRoute::_('index.php?option=com_bonuses&view=details&type=' . rawurlencode($type) . '&bid=' . (int)$bid . '&cid=' . (int)$cid);
        } catch (Exception $e) {
            return '';
        }
    }

    /** Track name worth showing ("Casino Offer"); the generic one is dropped. */
    /** Track name from the opener row's type, for a package with the generic title. */
    protected static function trackByOpener($type)
    {
        $type = strtolower(trim((string)$type));
        if ($type === 'hr') return 'High Roller Package';
        if ($type === 'crypto') return 'Crypto Package';
        return 'Standard Package';
    }

    protected static function trackTitle($t)
    {
        $t = trim($t);
        if ($t === '' || strcasecmp($t, 'welcome package') === 0) return '';
        return $t;
    }

    public static function sortEffectiveDesc($a, $b)
    {
        if ($a['effective_pct'] == $b['effective_pct']) return strcasecmp($a['brand'], $b['brand']);
        return ($a['effective_pct'] < $b['effective_pct']) ? 1 : -1;
    }

    public static function sortBrandAsc($a, $b)
    {
        return strcasecmp($a['brand'], $b['brand']);
    }

    protected static function median(array $v)
    {
        sort($v);
        $n = count($v);
        if ($n === 0) return null;
        $m = (int)floor($n / 2);
        return ($n % 2) ? $v[$m] : ($v[$m - 1] + $v[$m]) / 2;
    }

    /** "1,019" style money without the symbol decision (template adds it). */
    public static function fmtMoney($n, $sym, $ceil = false)
    {
        if ($n === null) return 'not stated';
        return fec_fxrate_money($n, $sym, $ceil);
    }

    public static function fmtPct($n)
    {
        if ($n === null) return 'not stated';
        return number_format(round($n)) . '%';
    }

    /** 2.7.90: positions of the legs the core priced at the deposit maximum. */
    public static function depositCappedLegs($fx)
    {
        $out = array();
        if (!is_array($fx) || empty($fx['legs']) || !is_array($fx['legs'])) return $out;
        foreach ($fx['legs'] as $leg) {
            if (!is_array($leg) || empty($leg['flags']) || !is_array($leg['flags'])) continue;
            if (in_array('cap_unreachable', $leg['flags'], true)) {
                $out[] = isset($leg['position']) ? (int)$leg['position'] : count($out) + 1;
            }
        }
        return $out;
    }

    public static function fmtDate($ymd, $long = false)
    {
        $ymd = trim((string)$ymd);
        if ($ymd === '' || $ymd === '0000-00-00') return '';
        $ts = strtotime(substr($ymd, 0, 10));
        if (!$ts) return $ymd;
        return date($long ? 'j F Y' : 'j M Y', $ts);
    }
}
