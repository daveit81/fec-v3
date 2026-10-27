<?php
/**
 * FXRate — public rendering of welcome packages (the "advertised vs effective"
 * line) on the bonus page, the casino review page and the hub bonus cards.
 *
 * Install: components/com_bonuses/helpers/fec_fxrate_render.php
 * Needs:   fec_fxrate.php (same folder, calculation core 0.2.0+),
 *          /css/fec_fxrate.css (loaded here, versioned by FEC_FXRATE_RENDER_VERSION),
 *          tables jos_fec_bonus_packages / jos_fec_bonus_package_legs.
 * Version: 1.4.3 (2026-10-19)
 *   1.4.3  homepage copy: the sub-line carries both counts in the index's
 *          words — "56 multi-deposit packages listed, 37 priced from the
 *          operator terms: …" — so the "All 56 packages" link lands on a
 *          number the reader has already seen ("all priced" when the two
 *          coincide). The tiles were geo-filtered without saying so while
 *          the numbers are catalogue-wide and the index lists restricted
 *          packages too: with opts['cc'] set the sub-line now adds "The
 *          three below are among those available in Italy." — the country
 *          name from the new opts['country'] (the one the big board
 *          prints), "your country" when the caller passes none. Nothing
 *          else changes: same query, same cache key, same tiles.
 *   1.4.2  casino exclusion delegated to the core's fec_fxrate_casino_excluded()
 *          (core 0.5.1): any non-empty jr_closed / jr_suspended /
 *          jr_blacklisted excludes, the rule of the site's listings. Before,
 *          a casino suspended with a wording other than "susp" (or
 *          blacklisted with one other than "bl"/"yes") kept its band, block,
 *          homepage tile and index row while every listing hid it. The
 *          homepage cache key moves to fxrate_home_v2 so the fix is immediate.
 *   1.4.1  the card pill ("Package · effective 34%") becomes a link to the
 *          casino block (#fxrate) on the pages that render it before the
 *          cards (the casino review and the casino Bonuses page): the block
 *          raises a request flag when it prints, and the pill reads it. On
 *          every other page the pill stays a span. fec_fxrate_render_casino_block
 *          takes opts['wrap'] = false for callers already inside a .wrap
 *          (the casino Bonuses page).
 *   1.4.0  homepage section: fec_fxrate_render_home_section($db, $opts) —
 *          "Welcome packages, priced." in the home's sec-head grammar, the
 *          catalogue numbers of the index (packages priced, median banner
 *          against median effective match, how many come in below 100%) and
 *          three tiles that are sorts, not picks: the latest package priced,
 *          the widest gap between banner and effective match, and the
 *          newest package whose effective match is 100% or more (the bonus
 *          at least matches the money in; else the narrowest gap). Closed / blacklisted / suspended casinos, casinos
 *          under review and operators that do not accept the visitor's
 *          country stay out of the tiles; the numbers are catalogue-wide.
 *          One query (the index's), computed with the core, cached 20 min
 *          through the lib's file cache when it is loaded. Never a "best".
 *   1.3.0  spins wagering (core 0.5.0, schema 3): the loader selects
 *          l.spins_wr (the leg's, real and virtual alike: a bonus row has one
 *          wagering term, the match's) and every leg with spins prints a
 *          second wagering line in the ladder — "spins: no wagering" /
 *          "spins: 40× winnings" / "spins: WR not stated" — under the
 *          match's "35× D+B"; a spins-only leg, whose cell was empty, prints
 *          it alone. The spins-leg note words it too. Reported, never priced.
 *   1.2.1  track labels: a package with the generic title is named by its
 *          opener's type when it has to be told apart from another one of the
 *          same casino — "High Roller Package" (hr), "Crypto Package"
 *          (crypto), "Standard Package" (su) — in the "an alternative to"
 *          line and in the casino-block eyebrows. A stored title always wins.
 *   1.2.0  alternative tracks: packages linked by alt_of (either direction,
 *          same casino, active) are named on each other's card — "An
 *          alternative to the Welcome Package (325% up to EUR 3,000 over 4
 *          deposits): a player takes one or the other." — with a link to the
 *          other opener when it has an active page. Numbers unchanged.
 *   1.1.0  maximum cashout on legs (core 0.4.0, schema 2): the loader selects
 *          max_cashout / max_cashout_kind (row's own for real legs, the leg's
 *          for virtual ones), the ladder terms end with "max cashout 5x
 *          deposit" and a note per capped leg gives the cap in money at the
 *          deposit the leg is priced at. Reported, never priced.
 *   1.0.5  entry line (core 0.3.2): under the four numbers, "Minimum deposits
 *          add up to X. Collecting every cap takes Y, N× the minimum." when
 *          every summed leg states a minimum; "The minimum deposits already
 *          collect every cap." when the ratio is 1; nothing otherwise.
 *   1.0.4  spins legs (core 0.3.0): a leg with no percentage, no cap and spins
 *          ("3rd deposit: 100 free spins") is counted and never priced. Its
 *          ladder line reads "100 free spins · min deposit $20 / counted, not
 *          priced", a note says it sits outside the four numbers, and it no
 *          longer turns the package into "not stated". Leg spins now reach the
 *          core through the row.
 *   1.0.3  casino-level exclusion, same rule as the listings and
 *          fec_should_noindex_casino(): a closed, blacklisted or suspended
 *          casino (jReviews jr_closed / jr_blacklisted / jr_suspended) gets no
 *          band, no block and no pill. Link to the Welcome Package Index.
 *   1.0.2  "How FXRate works" link to the methodology page (/methodology/fxrate,
 *          com_authors 2.7.73) in the band footer and the casino block footer.
 *   1.0.1  a stored headline equal to the first leg ("200% up to $2,000" on a
 *          3 × 200% package) is per-deposit wording, not the package claim:
 *          Advertised shows the sum and the sub-line quotes the casino.
 *   1.0.0  first cut: band, casino block, card pill.
 *
 * Standalone helper in the fec_blog.php / fec_cmp_widget.php pattern: the
 * templates include it guarded (is_readable + function_exists) and every
 * entry point returns '' when the helper, the tables or the package are
 * missing. The core library is not touched except for the one-line card
 * hook (fec_render_v2_list_card_html → 'pkgTag').
 *
 * Entry points
 *   fec_fxrate_render_band($db, $bid, $opts)          bonus page: band under the hero
 *   fec_fxrate_render_casino_block($db, $cid, $opts)  casino page: block above Active bonuses
 *   fec_fxrate_card_tag($bid)                         hub card: "Package · effective 34%" tag
 *
 * Reading rules (same as the calculation core): nothing is estimated. A
 * missing input prints "not stated"; a leg without a stated cap is shown
 * at its minimum deposit but never summed; free spins are counted in the
 * headline, never priced; mixed currencies give percentages only.
 *
 * PHP 5.6-safe: no scalar type hints, no ??, no <=>, no return types.
 */

defined('_JEXEC') or die('Restricted access');

if (!defined('FEC_FXRATE_RENDER_VERSION')) {
	define('FEC_FXRATE_RENDER_VERSION', '1.4.3');
}
if (!defined('FEC_FXRATE_CSS')) {
	define('FEC_FXRATE_CSS', '/css/fec_fxrate.css');
}
if (!defined('FEC_FXRATE_METHOD_URL')) {
	define('FEC_FXRATE_METHOD_URL', '/methodology/fxrate');
}
if (!defined('FEC_FXRATE_INDEX_URL')) {
	define('FEC_FXRATE_INDEX_URL', '/bonuses/welcome-packages');
}

/* ------------------------------------------------------------------ */
/* Bootstrap                                                           */
/* ------------------------------------------------------------------ */

if (!function_exists('fec_fxrate_render_ready')) {
	/** Loads the calculation core from this folder once. False = render nothing. */
	function fec_fxrate_render_ready() {
		static $ready = null;
		if ($ready !== null) return $ready;
		$ready = false;
		if (!function_exists('fec_fxrate_package')) {
			$core = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'fec_fxrate.php';
			if (is_readable($core)) {
				require_once $core;
			}
		}
		$ready = function_exists('fec_fxrate_package') && function_exists('fec_fxrate_leg');
		return $ready;
	}
}

if (!function_exists('fec_fxrate_add_assets')) {
	/** Adds the stylesheet to the document once per request. */
	function fec_fxrate_add_assets() {
		static $done = false;
		if ($done) return;
		$done = true;
		if (!class_exists('JFactory')) return;
		try {
			$doc = JFactory::getDocument();
			if ($doc && method_exists($doc, 'addStyleSheet')) {
				$root = class_exists('JUri') ? JUri::root(true) : '';
				$doc->addStyleSheet($root . FEC_FXRATE_CSS . '?v=' . FEC_FXRATE_RENDER_VERSION);
			}
		} catch (Exception $e) {
			// no stylesheet is better than no page
		}
	}
}

if (!function_exists('fec_fxrate_currency_map')) {
	/**
	 * The site currency map (helpers/currenciesarray.php, $currency), values
	 * decoded to plain glyphs so they can be escaped once on output. Uses the
	 * copy the lib promoted to $GLOBALS when present.
	 */
	function fec_fxrate_currency_map() {
		static $map = null;
		if ($map !== null) return $map;
		$map = array();
		$src = array();
		if (isset($GLOBALS['currency']) && is_array($GLOBALS['currency'])) {
			$src = $GLOBALS['currency'];
		} else {
			$file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'currenciesarray.php';
			if (is_readable($file)) {
				$currency = array();
				include $file;
				if (isset($currency) && is_array($currency)) $src = $currency;
			}
		}
		foreach ($src as $k => $v) {
			$map[strtolower(trim((string)$k))] = html_entity_decode((string)$v, ENT_QUOTES, 'UTF-8');
		}
		return $map;
	}
}

if (!function_exists('fec_fxrate_esc')) {
	function fec_fxrate_esc($s) {
		return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('fec_fxrate_ordinal')) {
	/** 1 → "1st", 2 → "2nd", 3 → "3rd", 4 → "4th" … */
	function fec_fxrate_ordinal($n) {
		$n = (int)$n;
		$m = $n % 100;
		if ($m >= 11 && $m <= 13) return $n . 'th';
		switch ($n % 10) {
			case 1: return $n . 'st';
			case 2: return $n . 'nd';
			case 3: return $n . 'rd';
		}
		return $n . 'th';
	}
}

if (!function_exists('fec_fxrate_details_url')) {
	/** Details page of a bonus row (same route the hub cards use). */
	function fec_fxrate_details_url($bid, $type, $cid) {
		$raw = 'index.php?option=com_bonuses&view=details&type=' . rawurlencode((string)$type) . '&bid=' . (int)$bid . '&cid=' . (int)$cid;
		if (class_exists('JRoute')) {
			try { return JRoute::_($raw); } catch (Exception $e) { return $raw; }
		}
		return $raw;
	}
}

if (!function_exists('fec_fxrate_wr_short')) {
	/** "35× D+B" / "35× bonus" / "no wagering" / "WR not stated". */
	function fec_fxrate_wr_short(array $leg) {
		if ($leg['wr'] === null) return 'WR not stated';
		if ($leg['wr'] == 0) return 'no wagering';
		$x = rtrim(rtrim(number_format($leg['wr'], 2, '.', ''), '0'), '.') . '×';
		if ($leg['wr_meth'] === 'bd') return $x . ' D+B';
		if ($leg['wr_meth'] === 'b') return $x . ' bonus';
		return $x . ' (base not stated)';
	}
}

if (!function_exists('fec_fxrate_spins_wr_clause')) {
	/** The spins' wagering as a clause for the notes: stated, zero, or not stated — never in the turnover. */
	function fec_fxrate_spins_wr_clause(array $leg) {
		if (!isset($leg['spins_wr']) || $leg['spins_wr'] === null) return 'the wagering on their winnings is not stated';
		if ($leg['spins_wr'] == 0) return 'the operator states no wagering on their winnings';
		return 'the ' . rtrim(rtrim(number_format($leg['spins_wr'], 2, '.', ''), '0'), '.') . "\xC3\x97" . ' wagering on their winnings is not in the turnover';
	}
}

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */

if (!function_exists('fec_fxrate_db_ok')) {
	/** Request-wide switch: flips to false at the first DB failure (missing tables). */
	function fec_fxrate_db_ok($set = null) {
		static $ok = true;
		if ($set !== null) $ok = (bool)$set;
		return $ok;
	}
}

if (!function_exists('fec_fxrate_casino_excluded_flags')) {
	/**
	 * Casino-level exclusion from the jReviews flags: the core's rule (0.5.1,
	 * the listings' one: any non-empty value excludes, a bare "no"/"0" does
	 * not). The fallback below is the same rule for an older core. Bonus-level
	 * states (expired, pending) are not this function's business.
	 */
	function fec_fxrate_casino_excluded_flags($closed, $blacklisted, $suspended) {
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
}

if (!function_exists('fec_fxrate_casino_open')) {
	/** True when the casino is not closed / blacklisted / suspended. Memoized; unknown casino = open. */
	function fec_fxrate_casino_open($db, $cid) {
		static $cache = array();
		$cid = (int)$cid;
		if ($cid <= 0) return true;
		if (isset($cache[$cid])) return $cache[$cid];
		$cache[$cid] = true;
		try {
			$q = $db->getQuery(true)
				->select(array('jr_closed', 'jr_blacklisted', 'jr_suspended'))
				->from($db->quoteName('#__jreviews_content'))
				->where('contentid = ' . $cid);
			$db->setQuery($q, 0, 1);
			$r = $db->loadAssoc();
			if (!$db->getErrorNum() && is_array($r)) {
				$cache[$cid] = !fec_fxrate_casino_excluded_flags($r['jr_closed'], $r['jr_blacklisted'], $r['jr_suspended']);
			}
		} catch (Exception $e) {
			// unknown = open; the page itself carries the casino state banner
		}
		return $cache[$cid];
	}
}

if (!function_exists('fec_fxrate_load_package')) {
	/**
	 * One package with its legs (real rows read through the row, virtual
	 * legs from the table). Memoized per request. Null when unknown.
	 */
	function fec_fxrate_load_package($db, $pkgId) {
		static $cache = array();
		$pkgId = (int)$pkgId;
		if ($pkgId <= 0 || !fec_fxrate_db_ok()) return null;
		if (array_key_exists($pkgId, $cache)) return $cache[$pkgId];
		$cache[$pkgId] = null;
		try {
			$q = $db->getQuery(true)
				->select('p.*')
				->from($db->quoteName('#__fec_bonus_packages', 'p'))
				->where('p.pkg_id = ' . $pkgId);
			$db->setQuery($q);
			$pkg = $db->loadAssoc();
			if ($db->getErrorNum()) { fec_fxrate_db_ok(false); return null; }
			if (!$pkg) return null;

			$q = $db->getQuery(true)
				->select(array(
					'l.leg_id', 'l.pkg_id', 'l.position', 'l.bid', 'l.spins', 'l.spins_wr', 'l.source',
					'CASE WHEN l.bid IS NULL THEN l.pct     ELSE b.bonus     END AS pct',
					'CASE WHEN l.bid IS NULL THEN l.cap     ELSE b.max_bonus END AS cap',
					'CASE WHEN l.bid IS NULL THEN l.min_dep ELSE b.min_dep   END AS min_dep',
					'CASE WHEN l.bid IS NULL THEN l.max_dep ELSE b.max_dep   END AS max_dep',
					'CASE WHEN l.bid IS NULL THEN l.wr      ELSE b.wr        END AS wr',
					'CASE WHEN l.bid IS NULL THEN l.wr_meth ELSE b.wr_meth   END AS wr_meth',
					'CASE WHEN l.bid IS NULL THEN l.code    ELSE b.code      END AS code',
					// schema 2: the withdrawal cap, the row's own for real legs
					'CASE WHEN l.bid IS NULL THEN l.max_cashout      ELSE b.max_cashout      END AS max_cashout',
					'CASE WHEN l.bid IS NULL THEN l.max_cashout_kind ELSE b.max_cashout_kind END AS max_cashout_kind',
					'b.type AS row_type', 'b.status AS row_status', 'b.currency AS row_currency',
					'b.brand AS row_brand', 'b.cid AS row_cid', 'b.label AS row_label',
				))
				->from($db->quoteName('#__fec_bonus_package_legs', 'l'))
				->join('LEFT', $db->quoteName('#__bonuses', 'b') . ' ON b.bid = l.bid')
				->where('l.pkg_id = ' . $pkgId)
				->order('l.position ASC, l.leg_id ASC');
			$db->setQuery($q);
			$legs = (array)$db->loadAssocList();
			if ($db->getErrorNum()) { fec_fxrate_db_ok(false); return null; }

			$cache[$pkgId] = array('pkg' => $pkg, 'legs' => $legs);
		} catch (Exception $e) {
			fec_fxrate_db_ok(false);
			return null;
		}
		return $cache[$pkgId];
	}
}

if (!function_exists('fec_fxrate_package_id_for_bid')) {
	/** pkg_id of the ACTIVE package this bonus row is a leg of, or 0. */
	function fec_fxrate_package_id_for_bid($db, $bid) {
		$bid = (int)$bid;
		if ($bid <= 0 || !fec_fxrate_db_ok()) return 0;
		try {
			$q = $db->getQuery(true)
				->select('l.pkg_id')
				->from($db->quoteName('#__fec_bonus_package_legs', 'l'))
				->join('INNER', $db->quoteName('#__fec_bonus_packages', 'p') . ' ON p.pkg_id = l.pkg_id')
				->where('l.bid = ' . $bid)
				->where('p.status = ' . $db->quote('active'))
				->order('p.pkg_id DESC');
			$db->setQuery($q, 0, 1);
			$id = (int)$db->loadResult();
			if ($db->getErrorNum()) { fec_fxrate_db_ok(false); return 0; }
			return $id;
		} catch (Exception $e) {
			fec_fxrate_db_ok(false);
			return 0;
		}
	}
}

if (!function_exists('fec_fxrate_package_ids_for_casino')) {
	/**
	 * Active packages of a casino whose opener row (when it has one) is
	 * still active on the site. Oldest first (the main track was entered first).
	 */
	function fec_fxrate_package_ids_for_casino($db, $cid) {
		$cid = (int)$cid;
		if ($cid <= 0 || !fec_fxrate_db_ok()) return array();
		try {
			$q = $db->getQuery(true)
				->select(array('p.pkg_id', 'b.status AS opener_status'))
				->from($db->quoteName('#__fec_bonus_packages', 'p'))
				->join('LEFT', $db->quoteName('#__bonuses', 'b') . ' ON b.bid = p.opener_bid')
				->where('p.cid = ' . $cid)
				->where('p.status = ' . $db->quote('active'))
				->order('p.pkg_id ASC');
			$db->setQuery($q);
			$rows = (array)$db->loadAssocList();
			if ($db->getErrorNum()) { fec_fxrate_db_ok(false); return array(); }
			$ids = array();
			foreach ($rows as $r) {
				$st = isset($r['opener_status']) ? strtolower(trim((string)$r['opener_status'])) : '';
				if ($st !== '' && $st !== 'active') continue;
				$ids[] = (int)$r['pkg_id'];
			}
			return $ids;
		} catch (Exception $e) {
			fec_fxrate_db_ok(false);
			return array();
		}
	}
}

if (!function_exists('fec_fxrate_compute')) {
	/**
	 * Package economics for rendering. Returns null when the package is not
	 * worth a band (fewer than two deposits known or declared).
	 *
	 *   pkg       the jos_fec_bonus_packages row
	 *   fx        fec_fxrate_package() result
	 *   legs      fx legs plus row meta: row_status, row_type, url, is_real, spins
	 *   sym       display symbol ('$' when mixed; percentages only then)
	 *   deposits  deposits declared or implied (max of column, legs, last position)
	 *   brand     brand of the first real row
	 */
	function fec_fxrate_compute($data) {
		if (!is_array($data) || empty($data['pkg']) || !fec_fxrate_render_ready()) return null;
		$pkg  = $data['pkg'];
		$rows = isset($data['legs']) ? $data['legs'] : array();
		$cmap = fec_fxrate_currency_map();
		$cid  = isset($pkg['cid']) ? (int)$pkg['cid'] : 0;

		$legs  = array();
		$meta  = array();
		$brand = '';
		$spins = 0;
		foreach ($rows as $r) {
			$isReal = (isset($r['bid']) && $r['bid'] !== null && (int)$r['bid'] > 0 && $r['row_type'] !== null);
			$cur = ($isReal && $r['row_currency'] !== null) ? (string)$r['row_currency'] : (string)$pkg['currency'];
			$pos = (int)$r['position'];
			$type = $isReal ? (string)$r['row_type'] : ($pos === 1 ? 'su' : $pos . 'd');
			$leg = fec_fxrate_leg(array(
				'bid'       => $isReal ? (int)$r['bid'] : 0,
				'cid'       => $cid,
				'type'      => $type,
				'bonus'     => $r['pct'],
				'max_bonus' => $r['cap'],
				'min_dep'   => $r['min_dep'],
				'max_dep'   => $r['max_dep'],
				'wr'        => $r['wr'],
				'wr_meth'   => $r['wr_meth'],
				'currency'  => $cur,
				'code'      => (string)$r['code'],
				'spins'     => (int)$r['spins'],
				'spins_wr'  => isset($r['spins_wr']) ? $r['spins_wr'] : null,
				'max_cashout'      => isset($r['max_cashout']) ? $r['max_cashout'] : null,
				'max_cashout_kind' => isset($r['max_cashout_kind']) ? $r['max_cashout_kind'] : '',
			), $cmap);
			// A virtual leg keeps the position it was entered at even when the
			// type string is not a ladder type (position comes from the table).
			if ($leg['position'] === 0) $leg['position'] = $pos;
			$leg['is_real']    = $isReal;
			$leg['row_status'] = $isReal ? strtolower(trim((string)$r['row_status'])) : '';
			$leg['spins']      = (int)$r['spins'];
			$leg['url']        = ($isReal && $leg['row_status'] === 'active') ? fec_fxrate_details_url((int)$r['bid'], $type, $cid) : '';
			$legs[] = $leg;
			$spins += (int)$r['spins'];
			if ($brand === '' && $isReal && $r['row_brand'] !== null) $brand = trim((string)$r['row_brand']);
		}

		$declaredSpins = isset($pkg['spins_total']) ? (int)$pkg['spins_total'] : 0;
		$fx = fec_fxrate_package($legs, array('spins' => max($declaredSpins, $spins)));

		$deposits = isset($pkg['deposits']) ? (int)$pkg['deposits'] : 0;
		$deposits = max($deposits, $fx['n_legs']);
		if ($fx['n_legs'] > 0) {
			$last = $fx['legs'][$fx['n_legs'] - 1];
			$deposits = max($deposits, (int)$last['position']);
		}
		if ($deposits < 2) return null;

		return array(
			'pkg'      => $pkg,
			'fx'       => $fx,
			'legs'     => $fx['legs'],
			'sym'      => ($fx['symbol'] === 'MIXED') ? '$' : $fx['symbol'],
			'deposits' => $deposits,
			'brand'    => $brand,
		);
	}
}

/* ------------------------------------------------------------------ */
/* Text pieces                                                         */
/* ------------------------------------------------------------------ */

if (!function_exists('fec_fxrate_headline_parse')) {
	/**
	 * The operator's stored headline ("225% up to $5,000 + 250 Free Spins")
	 * read back into pct / cap text / spins, so the Advertised cell can show
	 * what the casino prints even when the legs are not all entered. Null
	 * when the text has no leading percentage. The cap is kept as written.
	 */
	function fec_fxrate_headline_parse($headline) {
		$t = preg_replace('/\s+/', ' ', trim((string)$headline));
		if ($t === '' || !preg_match('/(\d+(?:[.,]\d+)?)\s*%/u', $t, $m)) return null;
		$out = array('pct' => (float)str_replace(',', '.', $m[1]), 'cap' => '', 'spins' => 0);
		// cap as written: "$5,000", "€1,500", "5,000 EUR" (currency word only in capitals)
		if (preg_match('/[Uu][Pp]\s*[Tt][Oo]\s*([^\s+(]+)(\s[A-Z]{3,4}\b)?/u', $t, $m2)) {
			$cap = trim($m2[1], " .,;:");
			if ($cap !== '' && preg_match('/\d/', $cap)) $out['cap'] = $cap . (isset($m2[2]) ? $m2[2] : '');
		}
		if (preg_match('/(\d[\d,.]*)\s*(?:free\s*spins?|fs|bonus\s*spins?|extra\s*spins?)\b/iu', $t, $m3)) {
			$out['spins'] = (int)str_replace(array(',', '.'), '', $m3[1]);
		}
		return $out;
	}
}

if (!function_exists('fec_fxrate_headline_kind')) {
	/**
	 * What the stored headline describes, same discipline as the text parser:
	 *   'package'  the whole offer ("250% up to $1,500 + 75 FS")           → Advertised cell
	 *   'leg'      the first leg's own terms, on a multi-leg package        → per-deposit wording
	 *   ''         no usable headline
	 * A headline is 'leg' when its percentage equals the first leg's and its
	 * cap is absent or equal to the first leg's cap.
	 */
	function fec_fxrate_headline_kind(array $c, $hp) {
		if ($hp === null) return '';
		$fx = $c['fx'];
		if ($fx['n_legs'] < 2) return 'package';
		$first = null;
		foreach ($c['legs'] as $leg) {
			if ((int)$leg['position'] === 1) { $first = $leg; break; }
		}
		if ($first === null || $first['pct'] === null) return 'package';
		if (abs((float)$hp['pct'] - (float)$first['pct']) > 0.001) return 'package';
		if ($hp['cap'] === '') return 'leg';
		$capNum = fec_fxrate_num(preg_replace('/[^0-9.,]/', '', $hp['cap']));
		if ($capNum === null) return 'leg';
		if ($first['cap'] !== null && abs($capNum - (float)$first['cap']) < 0.001) return 'leg';
		return 'package';
	}
}

if (!function_exists('fec_fxrate_legs_identical')) {
	/** True when every leg has the same percentage and cap (3 × "200% up to $2,000"). */
	function fec_fxrate_legs_identical(array $c) {
		$pct = null; $cap = null; $n = 0;
		foreach ($c['legs'] as $leg) {
			if ($leg['pct'] === null) return false;
			if ($n === 0) { $pct = $leg['pct']; $cap = $leg['cap']; }
			elseif (abs($leg['pct'] - $pct) > 0.001 || ($leg['cap'] === null) !== ($cap === null) || ($cap !== null && abs($leg['cap'] - $cap) > 0.001)) return false;
			$n++;
		}
		return $n > 1;
	}
}

if (!function_exists('fec_fxrate_advertised_text')) {
	/** "250% up to $1,500 + 75 free spins" from the numbers (operator headline wins when stored). */
	function fec_fxrate_advertised_text(array $c, $withSpins = true) {
		$fx = $c['fx'];
		$s = fec_fxrate_pct($fx['advertised_pct']);
		if ($fx['advertised_cap'] !== null && $fx['symbol'] !== 'MIXED') {
			$s .= ' up to ' . fec_fxrate_money($fx['advertised_cap'], $c['sym']);
		}
		if ($withSpins && $fx['spins'] > 0) {
			$s .= ' + ' . number_format($fx['spins']) . ' free spins';
		}
		return $s;
	}
}

if (!function_exists('fec_fxrate_leg_terms_text')) {
	/** "100% up to €300 + 50 FS · code WELCOME" or "terms not stated". */
	function fec_fxrate_leg_terms_text(array $leg, $sym) {
		if (isset($leg['symbol']) && $leg['symbol'] !== '') $sym = $leg['symbol'];
		$sep = ' ' . "\xC2\xB7" . ' ';
		$cash = (isset($leg['max_cashout']) && $leg['max_cashout'] !== null) ? $sep . 'max cashout ' . fec_fxrate_cashout_label($leg, $sym) : '';
		if (isset($leg['kind']) && $leg['kind'] === 'spins') {
			$s = number_format((int)$leg['spins']) . ' free spins';
			$s .= ($leg['min_dep'] !== null) ? $sep . 'min deposit ' . fec_fxrate_money($leg['min_dep'], $sym) : $sep . 'min deposit not stated';
			$s .= $cash;
			if ($leg['code'] !== '') $s .= $sep . 'code ' . $leg['code'];
			return $s;
		}
		if ($leg['pct'] === null) return 'terms not stated';
		$s = fec_fxrate_pct($leg['pct']);
		if ($leg['cap'] !== null) $s .= ' up to ' . fec_fxrate_money($leg['cap'], $sym);
		else $s .= ' (cap not stated)';
		if (!empty($leg['spins'])) $s .= ' + ' . number_format((int)$leg['spins']) . ' FS';
		$s .= $cash;
		if ($leg['code'] !== '') $s .= $sep . 'code ' . $leg['code'];
		return $s;
	}
}

if (!function_exists('fec_fxrate_leg_math_text')) {
	/** "deposit $500 → bonus $500" / "at the $20 minimum → bonus $20, not in the totals". */
	function fec_fxrate_leg_math_text(array $leg, $sym) {
		if (isset($leg['symbol']) && $leg['symbol'] !== '') $sym = $leg['symbol'];
		if (isset($leg['kind']) && $leg['kind'] === 'spins') return 'counted, not priced';
		if ($leg['pct'] === null) return '';
		if ($leg['deposit'] === null) return 'deposit not stated';
		if (in_array('priced_at_min_dep', $leg['flags'], true)) {
			return 'at the ' . fec_fxrate_money($leg['deposit'], $sym, true) . ' minimum → bonus ' . fec_fxrate_money($leg['bonus'], $sym) . ', not in the totals';
		}
		$s = 'deposit ' . fec_fxrate_money($leg['deposit'], $sym, true) . ' → bonus ' . fec_fxrate_money($leg['bonus'], $sym);
		if (in_array('cap_unreachable', $leg['flags'], true)) $s .= ' (max deposit)';
		if (in_array('min_dep_above_cap', $leg['flags'], true)) $s .= ' (min deposit)';
		return $s;
	}
}

if (!function_exists('fec_fxrate_entry_text')) {
	/**
	 * The entry line: the stated minimum deposits against what collecting
	 * every cap takes. '' unless the package is priced and every summed leg
	 * states a minimum (the core leaves cap_vs_min null otherwise).
	 */
	function fec_fxrate_entry_text(array $c) {
		$fx = $c['fx'];
		if (!isset($fx['cap_vs_min']) || $fx['cap_vs_min'] === null || $fx['deposit'] === null || $fx['symbol'] === 'MIXED') return '';
		$sym = $c['sym'];
		$ratio = (float)$fx['cap_vs_min'];
		if ($ratio <= 1.0001) return 'The minimum deposits already collect every cap.';
		$mult = ($ratio >= 10) ? number_format(round($ratio)) : rtrim(rtrim(number_format($ratio, 1, '.', ','), '0'), '.');
		return 'Minimum deposits add up to ' . fec_fxrate_money($fx['min_dep_total'], $sym)
			. '. Collecting every cap takes ' . fec_fxrate_money($fx['deposit'], $sym, true) . ', ' . $mult . "\xC3\x97 the minimum.";
	}
}

if (!function_exists('fec_fxrate_cashout_note')) {
	/**
	 * "2nd deposit: withdrawals from this leg are capped at 5x the deposit —
	 * €2,083 at the €417 the cap needs; not in the four numbers." The money
	 * figure only when the unit and its base are known; a bare number when
	 * the operator states no unit.
	 */
	function fec_fxrate_cashout_note(array $leg, $pos, $sym) {
		if (isset($leg['symbol']) && $leg['symbol'] !== '') $sym = $leg['symbol'];
		$kind = $leg['max_cashout_kind'];
		$n = (float)$leg['max_cashout'];
		$nTxt = (abs($n - round($n)) < 0.00001) ? (string)round($n) : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
		$dash = ' ' . "\xE2\x80\x94" . ' ';
		$s = $pos . ': withdrawals from this leg are capped at ';
		if ($kind === 'x_dep') {
			$s .= $nTxt . "\xC3\x97" . ' the deposit';
			if ($leg['cashout_at_cap'] !== null) $s .= $dash . fec_fxrate_money($leg['cashout_at_cap'], $sym) . ' at the ' . fec_fxrate_money($leg['deposit'], $sym, true) . ' the cap needs';
		} elseif ($kind === 'x_bonus') {
			$s .= $nTxt . "\xC3\x97" . ' the bonus';
			if ($leg['cashout_at_cap'] !== null) $s .= $dash . fec_fxrate_money($leg['cashout_at_cap'], $sym) . ' on the ' . fec_fxrate_money($leg['bonus'], $sym) . ' bonus';
		} elseif ($kind === 'abs') {
			$s .= fec_fxrate_money($n, $sym);
		} else {
			$s .= $nTxt . ' (unit not stated)';
		}
		return $s . '; not in the four numbers.';
	}
}

if (!function_exists('fec_fxrate_notes')) {
	/** Plain sentences for every flag that changes how the numbers read. */
	function fec_fxrate_notes(array $c) {
		$fx = $c['fx'];
		$n = array();
		$known = array();
		$spinsLeg = false;
		foreach ($c['legs'] as $leg) {
			$known[(int)$leg['position']] = true;
			$pos = fec_fxrate_ordinal($leg['position']) . ' deposit';
			if (isset($leg['kind']) && $leg['kind'] === 'spins') {
				$spinsLeg = true;
				$n[] = $pos . ': ' . number_format((int)$leg['spins']) . ' free spins and no match'
					. (($leg['min_dep'] !== null) ? ' on a ' . fec_fxrate_money($leg['min_dep'], isset($leg['symbol']) && $leg['symbol'] !== '' ? $leg['symbol'] : $c['sym']) . ' minimum deposit' : ', minimum deposit not stated')
					. ' — counted in the headline, left out of the four numbers (spins are never priced; ' . fec_fxrate_spins_wr_clause($leg) . ').';
				continue;
			}
			if ($leg['pct'] === null) {
				$n[] = $pos . ': terms not stated.';
				continue;
			}
			if (in_array('cap_not_stated', $leg['flags'], true)) {
				$n[] = in_array('priced_at_min_dep', $leg['flags'], true)
					? $pos . ': no cap stated — shown at the minimum deposit and left out of the totals.'
					: $pos . ': cap not stated — left out of the totals.';
			}
			if (in_array('cap_unreachable', $leg['flags'], true)) {
				$n[] = $pos . ': the cap needs more than the maximum deposit — priced at the maximum.';
			}
			if (in_array('min_dep_above_cap', $leg['flags'], true)) {
				$n[] = $pos . ': the minimum deposit already exceeds what the cap needs — priced at the minimum.';
			}
			if (in_array('wr_not_stated', $leg['flags'], true)) {
				$n[] = $pos . ': wagering not stated — no turnover.';
			} elseif (in_array('wr_meth_not_stated', $leg['flags'], true)) {
				$n[] = $pos . ': wagering base (bonus, or deposit + bonus) not stated — no turnover.';
			} elseif (in_array('wr_zero', $leg['flags'], true)) {
				$n[] = $pos . ': no wagering requirement listed — turnover 0.';
			}
			if ($leg['is_real'] && $leg['row_status'] !== '' && $leg['row_status'] !== 'active') {
				$n[] = $pos . ': listed as ' . $leg['row_status'] . '.';
			}
			if (isset($leg['max_cashout']) && $leg['max_cashout'] !== null) {
				$n[] = fec_fxrate_cashout_note($leg, $pos, $c['sym']);
			}
		}
		$missing = array();
		for ($p = 1; $p <= $c['deposits']; $p++) {
			if (!isset($known[$p])) $missing[] = $p;
		}
		if ($missing) {
			$n[] = (count($missing) === 1 ? 'Deposit ' . $missing[0] : 'Deposits ' . implode(', ', $missing)) . ': terms not stated.';
		}
		if (in_array('currency_mixed', $fx['flags'], true)) {
			$n[] = 'The deposits are in different currencies — percentages only, no totals.';
		}
		if ($fx['spins'] > 0 && !$spinsLeg) {
			$n[] = 'Free spins are counted in the headline and never priced.';
		}
		return $n;
	}
}

/* ------------------------------------------------------------------ */
/* HTML pieces                                                         */
/* ------------------------------------------------------------------ */

if (!function_exists('fec_fxrate_numbers_html')) {
	/** The four cells: Advertised · You deposit · Effective · Turnover. */
	function fec_fxrate_numbers_html(array $c) {
		$E  = 'fec_fxrate_esc';
		$fx = $c['fx'];
		$sym = $c['sym'];
		$mixed = ($fx['symbol'] === 'MIXED');

		// Advertised: the operator's own headline when one is stored (it may
		// promise more than the legs entered so far), else the sum of the legs.
		$hp = fec_fxrate_headline_parse(isset($c['pkg']['headline']) ? $c['pkg']['headline'] : '');
		if ($hp !== null && fec_fxrate_headline_kind($c, $hp) === 'package') {
			$advNa = false;
			$advV = fec_fxrate_pct($hp['pct']);
			$advS = ($hp['cap'] !== '') ? 'up to ' . $hp['cap'] : 'cap not stated';
			$spins = max($hp['spins'], $fx['spins']);
			if ($spins > 0) $advS .= ' + ' . number_format($spins) . ' FS';
		} else {
			$advNa = ($fx['advertised_pct'] === null);
			$advV = fec_fxrate_pct($fx['advertised_pct']);
			if ($fx['advertised_cap'] !== null && !$mixed) $advS = 'up to ' . fec_fxrate_money($fx['advertised_cap'], $sym);
			elseif ($mixed) $advS = 'mixed currencies';
			else $advS = 'caps not fully stated';
			if ($fx['spins'] > 0) $advS .= ' + ' . number_format($fx['spins']) . ' FS';
		}

		// You deposit
		$depNa = ($fx['deposit'] === null);
		$depV = $depNa ? 'not stated' : fec_fxrate_money($fx['deposit'], $sym, true);
		$depS = $depNa ? 'a deposit term is missing' : 'to collect every cap';

		// Effective
		$effNa = ($fx['effective_pct'] === null);
		$effV = $effNa ? 'not stated' : fec_fxrate_pct($fx['effective_pct']);
		$effS = $effNa ? 'needs every cap' : 'bonus on the money you put in';
		if (!$effNa && $fx['bonus'] !== null) $effS = fec_fxrate_money($fx['bonus'], $sym) . ' bonus on ' . fec_fxrate_money($fx['deposit'], $sym, true);

		// Turnover
		$toNa = ($fx['turnover'] === null);
		$toV = $toNa ? 'not stated' : fec_fxrate_money($fx['turnover'], $sym);
		if ($toNa) {
			$toS = 'a wagering term is missing';
		} elseif ($fx['turnover'] == 0) {
			$toS = 'no wagering listed';
		} else {
			$toS = 'stake needed to clear the wagering';
		}

		$cell = function ($k, $v, $s, $na, $key = false) use ($E) {
			return '<div class="fxr-num' . ($key ? ' is-key' : '') . '">'
				. '<span class="k">' . $E($k) . '</span>'
				. '<b class="v' . ($na ? ' is-na' : '') . '">' . $E($v) . '</b>'
				. ($s !== '' ? '<span class="s">' . $E($s) . '</span>' : '')
				. '</div>';
		};

		return '<div class="fxr-numbers">'
			. $cell('Advertised', $advV, $advS, $advNa)
			. $cell('You deposit', $depV, $depS, $depNa)
			. $cell('Effective', $effV, $effS, $effNa, true)
			. $cell('Turnover', $toV, $toS, $toNa)
			. '</div>';
	}
}

if (!function_exists('fec_fxrate_ladder_html')) {
	/**
	 * The legs, one row each. $currentBid highlights the page's own bonus;
	 * $compact drops the math column (casino page).
	 */
	function fec_fxrate_ladder_html(array $c, $currentBid = 0, $compact = false) {
		$E = 'fec_fxrate_esc';
		$sym = $c['sym'];
		$byPos = array();
		foreach ($c['legs'] as $leg) $byPos[(int)$leg['position']][] = $leg;

		$h = '<ol class="fxr-ladder' . ($compact ? ' fxr-ladder--compact' : '') . '">';
		for ($p = 1; $p <= $c['deposits']; $p++) {
			if (empty($byPos[$p])) {
				$h .= '<li class="fxr-leg is-missing">'
					. '<span class="pos">' . $E(fec_fxrate_ordinal($p) . ' deposit') . '</span>'
					. '<span class="terms">terms not stated</span>'
					. '</li>';
				continue;
			}
			foreach ($byPos[$p] as $leg) {
				$isCurrent = ($currentBid > 0 && (int)$leg['bid'] === (int)$currentBid);
				$isSpins   = (isset($leg['kind']) && $leg['kind'] === 'spins');
				$cls = 'fxr-leg';
				if ($isCurrent) $cls .= ' is-current';
				if ($isSpins) $cls .= ' is-spins';
				elseif ($leg['pct'] === null) $cls .= ' is-missing';
				$h .= '<li class="' . $cls . '">';
				$h .= '<span class="pos">' . $E(fec_fxrate_ordinal($p) . ' deposit') . '</span>';
				$h .= '<span class="terms">' . $E(fec_fxrate_leg_terms_text($leg, $sym)) . '</span>';
				if ($leg['pct'] === null && !$isSpins && !$isCurrent && $leg['url'] === '') {
					$h .= '</li>';
					continue;
				}
				if (!$compact) {
					$h .= '<span class="math">' . $E(fec_fxrate_leg_math_text($leg, $sym)) . '</span>';
				}
				$wrTxt = ($leg['pct'] === null) ? '' : $E(fec_fxrate_wr_short($leg));
				if (!empty($leg['spins'])) {
					// the spins' own wagering, never the match's (1.3.0)
					$wrTxt .= '<span class="wr-spins">spins: ' . $E(fec_fxrate_spins_wr_label($leg)) . '</span>';
				}
				$h .= '<span class="wr">' . $wrTxt . '</span>';
				if ($isCurrent) {
					$h .= '<span class="lnk is-here">This page</span>';
				} elseif ($leg['url'] !== '') {
					$h .= '<a class="lnk" href="' . $E($leg['url']) . '">Details</a>';
				} elseif ($leg['is_real'] && $leg['row_status'] !== '' && $leg['row_status'] !== 'active') {
					$h .= '<span class="lnk is-off">' . $E(ucfirst($leg['row_status'])) . '</span>';
				}
				$h .= '</li>';
			}
		}
		$h .= '</ol>';
		return $h;
	}
}

if (!function_exists('fec_fxrate_subline_text')) {
	/** "4 deposits · Casino offer · within 7 days · in order". */
	function fec_fxrate_subline_text(array $c, $noTitle = false) {
		$pkg = $c['pkg'];
		$bits = array($c['deposits'] . ' deposits');
		$title = isset($pkg['title']) ? trim((string)$pkg['title']) : '';
		// The generic track name adds nothing under a "Welcome package" heading.
		if (!$noTitle && $title !== '' && strcasecmp($title, 'welcome package') !== 0) $bits[] = $title;
		$win = isset($pkg['window_days']) ? (int)$pkg['window_days'] : 0;
		if ($win > 0) $bits[] = 'within ' . $win . ' day' . ($win === 1 ? '' : 's');
		if (!empty($pkg['sequential'])) $bits[] = 'in order';
		return implode(' · ', $bits);
	}
}

if (!function_exists('fec_fxrate_card_html')) {
	/**
	 * One package card. $opts: current_bid (int), compact (bool), brand (string),
	 * heading_level ('h2'|'h3'), eyebrow (string: '' = none, 'auto' = FXRate line).
	 */
	function fec_fxrate_card_html(array $c, array $opts = array()) {
		$E = 'fec_fxrate_esc';
		$pkg = $c['pkg'];
		$fx  = $c['fx'];
		$currentBid = isset($opts['current_bid']) ? (int)$opts['current_bid'] : 0;
		$compact    = !empty($opts['compact']);
		$hl         = (isset($opts['heading_level']) && $opts['heading_level'] === 'h3') ? 'h3' : 'h2';
		$brand      = isset($opts['brand']) && trim((string)$opts['brand']) !== '' ? trim((string)$opts['brand']) : $c['brand'];
		$eyebrow    = isset($opts['eyebrow']) ? (string)$opts['eyebrow'] : 'auto';
		if ($eyebrow === 'auto') {
			$eyebrow = 'FXRate · Welcome package' . ($brand !== '' ? ' · ' . $brand : '');
		}

		$headline = isset($pkg['headline']) ? trim((string)$pkg['headline']) : '';
		$computed = fec_fxrate_advertised_text($c);
		$hkind    = fec_fxrate_headline_kind($c, fec_fxrate_headline_parse($headline));
		// Per-deposit wording ("200% up to $2,000" × 3): the title is the sum,
		// the casino's own line is quoted underneath.
		$title = ($headline !== '' && $hkind !== 'leg') ? $headline : $computed;

		// Where the page's own bonus sits in the ladder
		$where = '';
		if ($currentBid > 0) {
			foreach ($c['legs'] as $leg) {
				if ((int)$leg['bid'] === $currentBid) {
					$where = 'This bonus is deposit ' . (int)$leg['position'] . ' of ' . $c['deposits'] . '.';
					break;
				}
			}
		}

		$h  = '<div class="fxr-card' . ($compact ? ' fxr-card--compact' : '') . '">';
		$h .= '<div class="fxr-head">';
		if ($eyebrow !== '') {
			$h .= '<span class="eyebrow">' . $E($eyebrow) . '</span>';
		}
		$h .= '<' . $hl . ' class="fxr-title">' . $E($title) . ' <small>' . $E(fec_fxrate_subline_text($c, !empty($opts['sub_no_title']))) . '</small></' . $hl . '>';
		if ($headline !== '' && $hkind === 'leg') {
			$quote = preg_replace('/\s+/', ' ', $headline);
			$sub = fec_fxrate_legs_identical($c)
				? 'The casino puts it as &ldquo;' . $E($quote) . '&rdquo; on each of the ' . (int)$c['deposits'] . ' deposits.'
				: 'The casino&rsquo;s headline, &ldquo;' . $E($quote) . '&rdquo;, is the first deposit alone.';
			$h .= '<p class="fxr-sub">' . $sub . ($where !== '' ? ' ' . $E($where) : '') . '</p>';
		} elseif ($headline !== '' && strcasecmp(preg_replace('/\s+/', ' ', $headline), $computed) !== 0 && $fx['advertised_pct'] !== null) {
			// The operator's wording and the sum of the legs are both shown;
			// the numbers below come from the legs.
			$h .= '<p class="fxr-sub">The casino&rsquo;s wording. The terms listed below add up to ' . $E($computed) . '.' . ($where !== '' ? ' ' . $E($where) : '') . '</p>';
		} elseif ($where !== '') {
			$h .= '<p class="fxr-sub">' . $E($where) . '</p>';
		}
		if (!empty($c['alts'])) $h .= fec_fxrate_alternatives_html($c['alts']);
		$h .= '</div>';

		$h .= fec_fxrate_numbers_html($c);
		$entry = fec_fxrate_entry_text($c);
		if ($entry !== '') $h .= '<p class="fxr-entry">' . $E($entry) . '</p>';
		$h .= fec_fxrate_ladder_html($c, $currentBid, $compact);

		$notes = fec_fxrate_notes($c);
		if ($notes) {
			$h .= '<ul class="fxr-notes">';
			foreach ($notes as $note) $h .= '<li>' . $E($note) . '</li>';
			$h .= '</ul>';
		}

		if ($compact) {
			$opener = null;
			foreach ($c['legs'] as $leg) {
				if ((int)$leg['position'] === 1 && $leg['url'] !== '') { $opener = $leg; break; }
			}
			if ($opener !== null) {
				$h .= '<div class="fxr-cta"><a class="fec-minirow__link" href="' . $E($opener['url']) . '">First deposit bonus and the full breakdown</a></div>';
			}
		} else {
			$h .= '<p class="fxr-foot">Computed from the operator&rsquo;s own terms as listed on FEC: deposit = cap &divide; match rate per leg &middot; effective = total bonus &divide; total deposit &middot; turnover = wagering &times; its base. Nothing is estimated. <a class="fxr-method" href="' . $E(FEC_FXRATE_METHOD_URL) . '">How FXRate works</a> <a class="fxr-method" href="' . $E(FEC_FXRATE_INDEX_URL) . '">All packages</a></p>';
		}
		$h .= '</div>';
		return $h;
	}
}

/* ------------------------------------------------------------------ */
/* Entry points                                                        */
/* ------------------------------------------------------------------ */

if (!function_exists('fec_fxrate_track_label')) {
	/**
	 * The name a package goes by when it has to be told apart from another one
	 * of the same casino: its stored title, or, when that is the generic
	 * "Welcome Package", a label from the opener row's type — High Roller
	 * Package (hr), Crypto Package (crypto), Standard Package (su).
	 */
	function fec_fxrate_track_label(array $c) {
		$title = isset($c['pkg']['title']) ? trim((string)$c['pkg']['title']) : '';
		if ($title !== '' && strcasecmp($title, 'welcome package') !== 0) return $title;
		$opener = isset($c['pkg']['opener_bid']) ? (int)$c['pkg']['opener_bid'] : 0;
		$type = '';
		foreach ($c['legs'] as $leg) {
			if ($opener > 0 && (int)$leg['bid'] === $opener) { $type = isset($leg['type']) ? (string)$leg['type'] : ''; break; }
		}
		if ($type === '' && !empty($c['legs']) && (int)$c['legs'][0]['position'] === 1) $type = isset($c['legs'][0]['type']) ? (string)$c['legs'][0]['type'] : '';
		if ($type === 'hr') return 'High Roller Package';
		if ($type === 'crypto') return 'Crypto Package';
		return 'Standard Package';
	}
}

if (!function_exists('fec_fxrate_alternatives')) {
	/**
	 * The active packages of the same casino linked to this one by alt_of, in
	 * either direction (this one points at them, or they point at this one).
	 * Each as array(pkg_id, title, advertised, deposits, url) — url is the
	 * other package's opener page when that row is active, else ''.
	 */
	function fec_fxrate_alternatives($db, array $c) {
		$pkg   = $c['pkg'];
		$pkgId = isset($pkg['pkg_id']) ? (int)$pkg['pkg_id'] : 0;
		$cid   = isset($pkg['cid']) ? (int)$pkg['cid'] : 0;
		$altOf = isset($pkg['alt_of']) ? (int)$pkg['alt_of'] : 0;
		if ($pkgId <= 0 || $cid <= 0 || !fec_fxrate_db_ok()) return array();
		$ids = array();
		try {
			$q = $db->getQuery(true)
				->select('p.pkg_id')
				->from($db->quoteName('#__fec_bonus_packages', 'p'))
				->where('p.cid = ' . $cid)
				->where('p.status = ' . $db->quote('active'))
				->where('p.pkg_id <> ' . $pkgId)
				->where('(p.alt_of = ' . $pkgId . ($altOf > 0 ? ' OR p.pkg_id = ' . $altOf : '') . ')')
				->order('p.pkg_id ASC');
			$db->setQuery($q);
			foreach ((array)$db->loadAssocList() as $r) $ids[] = (int)$r['pkg_id'];
			if ($db->getErrorNum()) return array();
		} catch (Exception $e) {
			return array();
		}
		$out = array();
		foreach ($ids as $id) {
			$o = fec_fxrate_compute(fec_fxrate_load_package($db, $id));
			if ($o === null) continue;
			$title = fec_fxrate_track_label($o);
			$url = '';
			$opener = isset($o['pkg']['opener_bid']) ? (int)$o['pkg']['opener_bid'] : 0;
			foreach ($o['legs'] as $leg) {
				if ($opener > 0 && (int)$leg['bid'] === $opener && $leg['url'] !== '') { $url = $leg['url']; break; }
			}
			$out[] = array(
				'pkg_id'     => $id,
				'title'      => $title,
				'advertised' => fec_fxrate_advertised_text($o),
				'deposits'   => (int)$o['deposits'],
				'url'        => $url,
			);
		}
		return $out;
	}
}

if (!function_exists('fec_fxrate_alternatives_html')) {
	/** "An alternative to the Welcome Package (325% up to EUR 3,000 over 4 deposits): a player takes one or the other." */
	function fec_fxrate_alternatives_html(array $alts) {
		if (!$alts) return '';
		$E = 'fec_fxrate_esc';
		$parts = array();
		foreach ($alts as $a) {
			$name = $E($a['title']);
			if ($a['url'] !== '') $name = '<a href="' . $E($a['url']) . '">' . $name . '</a>';
			$parts[] = 'the ' . $name . ' (' . $E($a['advertised']) . ' over ' . (int)$a['deposits'] . ' deposits)';
		}
		$list = (count($parts) === 1) ? $parts[0] : (implode(', ', array_slice($parts, 0, -1)) . ' and ' . $parts[count($parts) - 1]);
		return '<p class="fxr-alt">An alternative to ' . $list . ': a player takes one or the other.</p>';
	}
}

if (!function_exists('fec_fxrate_render_band')) {
	/**
	 * Bonus page: the band under the hero. '' when the bonus is not a leg of
	 * an active package. $opts: brand (string).
	 */
	function fec_fxrate_render_band($db, $bid, $opts = array()) {
		if (!fec_fxrate_render_ready()) return '';
		$pkgId = fec_fxrate_package_id_for_bid($db, (int)$bid);
		if ($pkgId <= 0) return '';
		$c = fec_fxrate_compute(fec_fxrate_load_package($db, $pkgId));
		if ($c === null) return '';
		if (!fec_fxrate_casino_open($db, (int)$c['pkg']['cid'])) return '';
		$c['alts'] = fec_fxrate_alternatives($db, $c);
		fec_fxrate_add_assets();
		$E = 'fec_fxrate_esc';
		$brand = isset($opts['brand']) ? trim((string)$opts['brand']) : '';
		if ($brand === '') $brand = $c['brand'];
		$h  = '<section class="fxr-band" id="fxrate" aria-label="Welcome package value' . ($brand !== '' ? ' at ' . $E($brand) : '') . '">';
		$h .= '<div class="wrap">';
		$h .= fec_fxrate_card_html($c, array('current_bid' => (int)$bid, 'brand' => $brand, 'heading_level' => 'h2'));
		$h .= '</div></section>';
		return $h;
	}
}

if (!function_exists('fec_fxrate_render_casino_block')) {
	/**
	 * Casino review page: "Welcome package" section above Active bonuses,
	 * one compact card per active package (side by side when more than one).
	 * '' when the casino has none. $opts: brand (string).
	 */
	function fec_fxrate_render_casino_block($db, $cid, $opts = array()) {
		if (!fec_fxrate_render_ready()) return '';
		if (!fec_fxrate_casino_open($db, (int)$cid)) return '';
		$ids = fec_fxrate_package_ids_for_casino($db, (int)$cid);
		if (!$ids) return '';
		$cards = array();
		foreach ($ids as $pkgId) {
			$c = fec_fxrate_compute(fec_fxrate_load_package($db, $pkgId));
			if ($c === null) continue;
			$c['alts'] = fec_fxrate_alternatives($db, $c);
			$cards[] = $c;
			if (count($cards) >= 3) break;
		}
		if (!$cards) return '';
		fec_fxrate_add_assets();
		$E = 'fec_fxrate_esc';
		$brand = isset($opts['brand']) ? trim((string)$opts['brand']) : '';
		if ($brand === '') $brand = $cards[0]['brand'];
		$n = count($cards);

		// opts['wrap'] = false when the caller already sits inside a .wrap (the
		// casino Bonuses page): a nested wrap would inset the block by its padding.
		$useWrap = !isset($opts['wrap']) || !empty($opts['wrap']);
		$h  = '<section class="bd-related fxr-casino' . ($useWrap ? '' : ' fxr-casino--inwrap') . '" id="fxrate" aria-label="Welcome package' . ($brand !== '' ? ' at ' . $E($brand) : '') . '">';
		if ($useWrap) $h .= '<div class="wrap">';
		$h .= '<div class="top"><div class="fxr-top-left"><h2>Welcome package' . ($n > 1 ? 's' : '') . '</h2>';
		$h .= '<p class="fxr-top-sub">FXRate: what ' . ($brand !== '' ? $E($brand) : 'the casino') . ' advertises, what you have to deposit to collect all of it, and the real match rate on that money &mdash; from the operator&rsquo;s own terms, nothing estimated.</p>';
		$h .= '</div><a class="alllink" href="' . $E(FEC_FXRATE_INDEX_URL) . '">All welcome packages, priced</a></div>';
		$h .= '<div class="fxr-grid fxr-grid--' . $n . '">';
		$i = 0;
		foreach ($cards as $c) {
			$i++;
			// With several tracks the eyebrow carries the track name
			// (Casino Offer / Sports Offer); with one, the section title says it all.
			$eyebrow = '';
			if ($n > 1) {
				// the stored title, else the track by the opener's type (1.2.1)
				$eyebrow = fec_fxrate_track_label($c);
			}
			$h .= fec_fxrate_card_html($c, array('compact' => true, 'brand' => '', 'heading_level' => 'h3', 'eyebrow' => $eyebrow, 'sub_no_title' => ($n > 1)));
		}
		$h .= '</div>';
		$h .= '<p class="fxr-foot">Deposit = cap &divide; match rate per leg &middot; effective = total bonus &divide; total deposit &middot; turnover = wagering &times; its base. A leg without a stated cap is shown at its minimum deposit and left out of the totals; free spins are counted, never priced. <a class="fxr-method" href="' . $E(FEC_FXRATE_METHOD_URL) . '">How FXRate works</a></p>';
		$h .= ($useWrap ? '</div>' : '') . '</section>';
		// the block is on this page: card pills rendered after it may link to it (1.4.1)
		$GLOBALS['fec_fxrate_block_on_page'] = '#fxrate';
		return $h;
	}
}

if (!function_exists('fec_fxrate_card_tag')) {
	/**
	 * Hub card: one .tag for a bonus that is a leg of an active package.
	 * Opener: "Package · effective 34%" (or "Package · 4 deposits" when the
	 * package cannot be priced); later legs: "Package · deposit 2 of 4".
	 * One small query per request for the bid map, one per package shown.
	 */
	function fec_fxrate_card_tag($bid) {
		static $map = null;
		$bid = (int)$bid;
		if ($bid <= 0 || !fec_fxrate_render_ready() || !class_exists('JFactory')) return '';
		if ($map === null) {
			$map = array();
			if (!fec_fxrate_db_ok()) return '';
			try {
				$db = JFactory::getDbo();
				$q = $db->getQuery(true)
					->select(array('l.bid', 'l.pkg_id', 'l.position', 'p.deposits', 'jc.jr_closed', 'jc.jr_blacklisted', 'jc.jr_suspended'))
					->from($db->quoteName('#__fec_bonus_package_legs', 'l'))
					->join('INNER', $db->quoteName('#__fec_bonus_packages', 'p') . ' ON p.pkg_id = l.pkg_id')
					->join('LEFT', $db->quoteName('#__jreviews_content', 'jc') . ' ON jc.contentid = p.cid')
					->where('p.status = ' . $db->quote('active'))
					->order('l.pkg_id ASC, l.position ASC');
				$db->setQuery($q);
				$rows = (array)$db->loadAssocList();
				if ($db->getErrorNum()) { fec_fxrate_db_ok(false); return ''; }
				$count = array();
				$last  = array();
				$decl  = array();
				foreach ($rows as $r) {
					if (fec_fxrate_casino_excluded_flags(
						isset($r['jr_closed']) ? $r['jr_closed'] : '',
						isset($r['jr_blacklisted']) ? $r['jr_blacklisted'] : '',
						isset($r['jr_suspended']) ? $r['jr_suspended'] : '')) continue;
					$pid = (int)$r['pkg_id'];
					$count[$pid] = isset($count[$pid]) ? $count[$pid] + 1 : 1;
					$last[$pid]  = max(isset($last[$pid]) ? $last[$pid] : 0, (int)$r['position']);
					$decl[$pid]  = (int)$r['deposits'];
				}
				foreach ($rows as $r) {
					if ($r['bid'] === null || (int)$r['bid'] <= 0) continue;
					$pid = (int)$r['pkg_id'];
					if (!isset($count[$pid])) continue; // casino excluded above
					$b = (int)$r['bid'];
					if (isset($map[$b])) continue; // first (oldest) package wins
					$map[$b] = array(
						'pkg_id'   => $pid,
						'position' => (int)$r['position'],
						'deposits' => max($decl[$pid], $count[$pid], $last[$pid]),
					);
				}
			} catch (Exception $e) {
				fec_fxrate_db_ok(false);
				return '';
			}
		}
		if (!isset($map[$bid])) return '';
		$m = $map[$bid];
		if ($m['deposits'] < 2) return '';

		$E = 'fec_fxrate_esc';
		$text = '';
		$tip  = '';
		if ($m['position'] === 1) {
			$c = fec_fxrate_compute(fec_fxrate_load_package(JFactory::getDbo(), $m['pkg_id']));
			if ($c === null) return '';
			if ($c['fx']['effective_pct'] !== null) {
				$text = 'Package &middot; effective ' . $E(fec_fxrate_pct($c['fx']['effective_pct']));
				$tip  = fec_fxrate_summary_line($c['fx'], $c['sym']);
			} else {
				$text = 'Package &middot; ' . (int)$c['deposits'] . ' deposits';
				$tip  = 'Welcome package over ' . (int)$c['deposits'] . ' deposits; a term is missing, so the effective rate is not stated';
			}
		} else {
			$text = 'Package &middot; deposit ' . (int)$m['position'] . ' of ' . (int)$m['deposits'];
			$tip  = 'Part of a welcome package; the first deposit bonus carries the full FXRate breakdown';
		}
		fec_fxrate_add_assets();
		if (!empty($GLOBALS['fec_fxrate_block_on_page'])) {
			return '<a class="tag tag-pkg" href="' . $E($GLOBALS['fec_fxrate_block_on_page']) . '" title="' . $E($tip) . ' ' . "\xE2\x80\x94" . ' the package is on this page">' . $text . '</a>';
		}
		return '<span class="tag tag-pkg" title="' . $E($tip) . '">' . $text . '</span>';
	}
}

/* ------------------------------------------------------------------ */
/* Homepage section (1.4.0)                                            */
/* ------------------------------------------------------------------ */

if (!function_exists('fec_fxrate_home_data')) {
	/**
	 * The catalogue as the Welcome Package Index reads it, reduced to what the
	 * homepage needs: the priced packages (one compact row each) and the index
	 * numbers. Same query, same counting rule as the index (package active,
	 * opener active, at least two deposits, casino not closed / blacklisted /
	 * suspended; priced = effective and advertised both known). No geography
	 * here: the rows carry the country lists and the caller decides, so the
	 * result can be cached for every visitor.
	 *
	 * Returns array('live' => bool, 'rows' => array, 'stats' => array, 'as_of' => 'YYYY-MM-DD').
	 */
	function fec_fxrate_home_data($db, $ttl = 1200) {
		$empty = array('live' => false, 'rows' => array(), 'stats' => array(), 'as_of' => '');
		if (!fec_fxrate_render_ready() || !$db) return $empty;

		$key = 'fxrate_home_v2'; // v2: exclusion rule of 1.4.2
		if ($ttl > 0 && function_exists('fec_file_cache_get')) {
			$hit = fec_file_cache_get($key, (int)$ttl);
			if (is_array($hit) && isset($hit['live']) && isset($hit['rows']) && isset($hit['stats'])) return $hit;
		}

		try {
			$sql = 'SELECT p.pkg_id, p.cid, p.opener_bid, p.title, p.alt_of, p.headline, p.currency AS pkg_currency, p.deposits, p.spins_total, p.modified AS pmod,'
				. ' ob.status AS opener_status, ob.brand AS opener_brand, ob.type AS opener_type, ob.country_ok, ob.country_no,'
				. ' c.title AS casino_title,'
				. ' jc.jr_closed, jc.jr_blacklisted, jc.jr_suspended, jc.jr_flag, jc.jr_restrictedcountries,'
				. ' l.position, l.bid, l.spins, l.spins_wr,'
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
			$raw = $db->loadAssocList();
			if ($db->getErrorNum()) { fec_fxrate_db_ok(false); return $empty; }
		} catch (Exception $e) {
			fec_fxrate_db_ok(false);
			return $empty;
		}

		$cmap = fec_fxrate_currency_map();
		$groups = array();
		foreach ((array)$raw as $r) $groups[(int)$r['pkg_id']][] = $r;

		$rows = array(); $adv = array(); $eff = array(); $cids = array(); $asOf = '';
		$st = array('packages_total' => 0, 'packages_priced' => 0, 'casinos' => 0, 'below_100' => 0, 'below_50' => 0,
			'median_advertised' => null, 'median_effective' => null, 'max_ratio' => null);

		foreach ($groups as $pkgId => $legsRaw) {
			$f = $legsRaw[0];
			$ost = strtolower(trim((string)$f['opener_status']));
			if ($ost !== '' && $ost !== 'active') continue;
			if (fec_fxrate_casino_excluded_flags($f['jr_closed'], $f['jr_blacklisted'], $f['jr_suspended'])) continue;

			$legs = array(); $spins = 0; $mod = (string)$f['pmod'];
			foreach ($legsRaw as $r) {
				$isReal = ($r['bid'] !== null && (int)$r['bid'] > 0 && $r['row_type'] !== null);
				$pos = (int)$r['position'];
				$leg = fec_fxrate_leg(array(
					'bid' => $isReal ? (int)$r['bid'] : 0, 'cid' => (int)$f['cid'],
					'type' => $isReal ? (string)$r['row_type'] : ($pos === 1 ? 'su' : $pos . 'd'),
					'bonus' => $r['pct'], 'max_bonus' => $r['cap'], 'min_dep' => $r['min_dep'], 'max_dep' => $r['max_dep'],
					'wr' => $r['wr'], 'wr_meth' => $r['wr_meth'],
					'currency' => ($isReal && $r['row_currency'] !== null) ? (string)$r['row_currency'] : (string)$f['pkg_currency'],
					'code' => '', 'spins' => (int)$r['spins'],
					'spins_wr' => isset($r['spins_wr']) ? $r['spins_wr'] : null,
					'max_cashout' => isset($r['max_cashout']) ? $r['max_cashout'] : null,
					'max_cashout_kind' => isset($r['max_cashout_kind']) ? (string)$r['max_cashout_kind'] : '',
				), $cmap);
				if ($leg['position'] === 0) $leg['position'] = $pos;
				$legs[] = $leg;
				$spins += (int)$r['spins'];
				if ($isReal && (string)$r['bmod'] > $mod) $mod = (string)$r['bmod'];
			}
			$fx = fec_fxrate_package($legs, array('spins' => max((int)$f['spins_total'], $spins)));
			$deposits = max((int)$f['deposits'], $fx['n_legs']);
			if ($fx['n_legs'] > 0) $deposits = max($deposits, (int)$fx['legs'][$fx['n_legs'] - 1]['position']);
			if ($deposits < 2) continue;

			$st['packages_total']++;
			$cids[(int)$f['cid']] = true;
			if ($mod !== '' && substr($mod, 0, 10) > $asOf) $asOf = substr($mod, 0, 10);

			$isPriced = ($fx['effective_pct'] !== null && $fx['advertised_pct'] !== null && $fx['effective_pct'] > 0);
			if (!$isPriced) continue;

			$sym   = ($fx['symbol'] === 'MIXED') ? '' : $fx['symbol'];
			$brand = trim((string)$f['opener_brand']) !== '' ? trim((string)$f['opener_brand']) : trim((string)$f['casino_title']);
			$title = trim((string)$f['title']);
			$track = ($title !== '' && strcasecmp($title, 'welcome package') !== 0) ? $title : '';
			$ratio = $fx['advertised_pct'] / $fx['effective_pct'];

			$rows[] = array(
				'pkg_id'         => (int)$pkgId,
				'cid'            => (int)$f['cid'],
				'bid'            => (int)$f['opener_bid'],
				'type'           => strtolower(trim((string)$f['opener_type'])),
				'brand'          => $brand,
				'track'          => $track,
				'flagged'        => (strpos((string)$f['jr_flag'], '*flagged*') !== false),
				'restricted'     => (string)$f['jr_restrictedcountries'],
				'country_ok'     => (string)$f['country_ok'],
				'country_no'     => (string)$f['country_no'],
				'deposits'       => $deposits,
				'symbol'         => $sym,
				'advertised_pct' => (float)$fx['advertised_pct'],
				'advertised_cap' => $fx['advertised_cap'],
				'spins'          => (int)$fx['spins'],
				'deposit'        => $fx['deposit'],
				'bonus'          => $fx['bonus'],
				'effective_pct'  => (float)$fx['effective_pct'],
				'turnover'       => $fx['turnover'],
				'ratio'          => $ratio,
				'modified'       => $mod,
			);
			$st['packages_priced']++;
			$adv[] = (float)$fx['advertised_pct']; $eff[] = (float)$fx['effective_pct'];
			if ($fx['effective_pct'] < 100) $st['below_100']++;
			if ($fx['effective_pct'] <= 50) $st['below_50']++;
			if ($st['max_ratio'] === null || $ratio > $st['max_ratio']) $st['max_ratio'] = $ratio;
		}
		$st['casinos'] = count($cids);
		if ($adv) {
			$st['median_advertised'] = fec_fxrate_median($adv);
			$st['median_effective']  = fec_fxrate_median($eff);
		}

		$out = array('live' => true, 'rows' => $rows, 'stats' => $st, 'as_of' => $asOf);
		if ($ttl > 0 && function_exists('fec_file_cache_put')) {
			try { fec_file_cache_put($key, $out); } catch (Exception $e) { /* a cold cache is not a failure */ }
		}
		return $out;
	}
}

if (!function_exists('fec_fxrate_median')) {
	/** Median of a list of numbers (the index's). */
	function fec_fxrate_median(array $v) {
		$v = array_values(array_filter($v, 'is_numeric'));
		if (!$v) return null;
		sort($v);
		$n = count($v);
		return ($n % 2) ? (float)$v[($n - 1) / 2] : ((float)$v[$n / 2 - 1] + (float)$v[$n / 2]) / 2;
	}
}

if (!function_exists('fec_fxrate_home_geo_blocked')) {
	/**
	 * The geo rule of fec_decide_state(), for one visitor country and one
	 * compact row: blocked when the casino lists the country as restricted,
	 * the opener lists it in country_no, or has a country_ok list that omits
	 * it. "no-restrictions" is never blocked. '' = no detection = not blocked.
	 */
	function fec_fxrate_home_geo_blocked(array $row, $cc) {
		$cc = strtolower(trim((string)$cc));
		if ($cc === '' || strlen($cc) !== 2) return false;
		$restricted = (string)$row['restricted']; $ok = (string)$row['country_ok']; $no = (string)$row['country_no'];
		if (strpos($restricted, 'no-restrictions') !== false) return false;
		if ($restricted !== '' && strpos($restricted, '*' . $cc . '*') !== false) return true;
		if ($no !== '' && strpos($no, '*' . $cc . '*') !== false) return true;
		if ($ok !== '' && strpos($ok, '*' . $cc . '*') === false) return true;
		return false;
	}
}

if (!function_exists('fec_fxrate_home_pick')) {
	/**
	 * The three tiles, as sorts of the priced rows the visitor can take
	 * (not under review, not geo-blocked): the latest package priced, the
	 * widest gap between banner and effective match, and the newest whose
	 * effective match is 100% or more (a banner is a sum of rates, so it is
	 * never the effective rate on two deposits or more; "returns at least
	 * the money put in" is the honest third sort) or, when none does, the
	 * narrowest gap. No package appears twice.
	 * Returns a list of array('key', 'label', 'row'); fewer than three when
	 * the catalogue is thin.
	 */
	function fec_fxrate_home_pick(array $rows, $cc) {
		$pool = array();
		foreach ($rows as $r) {
			if (!empty($r['flagged'])) continue;
			if (fec_fxrate_home_geo_blocked($r, $cc)) continue;
			$pool[] = $r;
		}
		if (!$pool) return array();
		$picks = array(); $used = array();

		$byDate = $pool;
		usort($byDate, 'fec_fxrate_home_sort_newest');
		$picks[] = array('key' => 'latest', 'label' => 'Latest priced', 'row' => $byDate[0]);
		$used[(int)$byDate[0]['pkg_id']] = true;

		$byRatio = $pool;
		usort($byRatio, 'fec_fxrate_home_sort_ratio_desc');
		foreach ($byRatio as $r) {
			if (isset($used[(int)$r['pkg_id']]) || $r['ratio'] <= 1.005) continue;
			$picks[] = array('key' => 'widest', 'label' => 'Widest gap', 'row' => $r);
			$used[(int)$r['pkg_id']] = true;
			break;
		}

		$holds = array();
		foreach ($byDate as $r) {
			if (isset($used[(int)$r['pkg_id']])) continue;
			if ($r['effective_pct'] >= 100) $holds[] = $r;
		}
		if ($holds) {
			$picks[] = array('key' => 'holds', 'label' => 'Effective 100% or more', 'row' => $holds[0]);
		} else {
			$asc = array_reverse($byRatio);
			foreach ($asc as $r) {
				if (isset($used[(int)$r['pkg_id']])) continue;
				$picks[] = array('key' => 'narrowest', 'label' => 'Narrowest gap', 'row' => $r);
				break;
			}
		}
		return $picks;
	}
}

if (!function_exists('fec_fxrate_home_sort_newest')) {
	function fec_fxrate_home_sort_newest($a, $b) {
		$c = strcmp((string)$b['modified'], (string)$a['modified']);
		return $c !== 0 ? $c : ((int)$b['pkg_id'] - (int)$a['pkg_id']);
	}
}

if (!function_exists('fec_fxrate_home_sort_ratio_desc')) {
	function fec_fxrate_home_sort_ratio_desc($a, $b) {
		if ($a['ratio'] == $b['ratio']) return strcmp((string)$b['modified'], (string)$a['modified']);
		return ($a['ratio'] < $b['ratio']) ? 1 : -1;
	}
}

if (!function_exists('fec_fxrate_home_tile_html')) {
	/** One tile: kicker, casino, banner, effective match, deposit and turnover. */
	function fec_fxrate_home_tile_html(array $pick) {
		$E = 'fec_fxrate_esc';
		$r = $pick['row'];
		$sym = $r['symbol'];
		$url = fec_fxrate_details_url($r['bid'], $r['type'], $r['cid']);

		$banner = fec_fxrate_pct($r['advertised_pct']);
		if ($r['advertised_cap'] !== null && $sym !== '') $banner .= ' up to ' . fec_fxrate_money($r['advertised_cap'], $sym);
		if ($r['spins'] > 0) $banner .= ' + ' . number_format($r['spins']) . ' FS';

		$effCls = ($r['effective_pct'] >= 100) ? ' is-full' : (($r['effective_pct'] < 50) ? ' is-low' : '');
		$gap = ($r['ratio'] > 1.005)
			? 'the banner says ' . number_format($r['ratio'], 1) . "\xC3\x97" . ' more'
			: 'as the banner says';

		$sub = array();
		if ($r['deposit'] !== null && $sym !== '') $sub[] = 'you deposit ' . fec_fxrate_money($r['deposit'], $sym, true);
		if ($r['turnover'] !== null && $sym !== '') $sub[] = 'turnover ' . fec_fxrate_money($r['turnover'], $sym);
		elseif ($r['turnover'] === null) $sub[] = 'turnover not stated';

		$chip = function_exists('fec_casino_chip') ? fec_casino_chip($r['brand'], 28) : '';
		$name = $E($r['brand']);
		if ($r['track'] !== '') $name .= ' <small>' . $E($r['track']) . '</small>';

		return '<a class="fxr-home-tile fxr-home-tile--' . $E($pick['key']) . '" href="' . $E($url) . '">'
			. '<span class="fxr-home-kicker"><span>' . $E($pick['label']) . '</span><span class="fxr-home-deps">' . (int)$r['deposits'] . ' deposits</span></span>'
			. '<span class="fxr-home-brand">' . $chip . '<span class="fxr-home-name">' . $name . '</span></span>'
			. '<span class="fxr-home-banner">Banner ' . $E($banner) . '</span>'
			. '<span class="fxr-home-eff"><b class="fxr-home-eff-v' . $effCls . '">' . $E(fec_fxrate_pct($r['effective_pct'])) . '</b><span class="fxr-home-eff-k">effective match ' . "\xC2\xB7" . ' ' . $E($gap) . '</span></span>'
			. ($sub ? '<span class="fxr-home-sub">' . $E(implode(' ' . "\xC2\xB7" . ' ', $sub)) . '</span>' : '')
			. '</a>';
	}
}

if (!function_exists('fec_fxrate_render_home_section')) {
	/**
	 * The homepage section. Options: cc (visitor country, lowercase ISO),
	 * country (its display name, the one the big board prints: "Italy"; with
	 * cc set and no name the sub-line says "your country"), ttl (cache
	 * seconds, 1200), min_tiles (2: below that nothing renders — a thin band
	 * is worse than none), index_url, method_url.
	 * Returns '' whenever the tables are missing, the core is absent or the
	 * catalogue is too thin, so the caller can echo it blind.
	 */
	function fec_fxrate_render_home_section($db, $opts = array()) {
		$o = array_merge(array('cc' => '', 'country' => '', 'ttl' => 1200, 'min_tiles' => 2,
			'index_url' => FEC_FXRATE_INDEX_URL, 'method_url' => FEC_FXRATE_METHOD_URL), is_array($opts) ? $opts : array());
		$E = 'fec_fxrate_esc';
		try {
			$d = fec_fxrate_home_data($db, (int)$o['ttl']);
			if (empty($d['live']) || empty($d['rows'])) return '';
			$picks = fec_fxrate_home_pick($d['rows'], $o['cc']);
			if (count($picks) < (int)$o['min_tiles']) return '';
			$st = $d['stats'];

			$root = class_exists('JUri') ? JUri::root(true) : '';
			$indexUrl = $root . $o['index_url'];
			$methodUrl = $root . $o['method_url'];

			// Both counts up front, in the index's words ("56 packages. 37 priced."),
			// so the "All 56 packages" link lands on a number already read (1.4.3).
			$tot = (int)$st['packages_total'];
			$n   = (int)$st['packages_priced'];
			$sub = number_format($tot) . ' multi-deposit package' . ($tot === 1 ? '' : 's') . ' listed, '
				. (($n === $tot) ? (($tot === 1) ? 'priced' : 'all priced') : number_format($n) . ' priced')
				. ' from the operator terms';
			if ($st['median_advertised'] !== null && $st['median_effective'] !== null) {
				$sub .= ': the median banner says <b>' . $E(fec_fxrate_pct($st['median_advertised'])) . '</b>, the median effective match is <b>'
					. $E(fec_fxrate_pct($st['median_effective'])) . '</b>';
				if ($n > 1) $sub .= ', and ' . (int)$st['below_100'] . ' of the ' . $n . ' come in below 100%';
			}
			$sub .= '.';
			// The tiles are geo-filtered (fec_fxrate_home_pick), the numbers are
			// not: say so where the reader can see it, with the big board's words.
			$cc = strtolower(trim((string)$o['cc']));
			if ($cc !== '' && strlen($cc) === 2) {
				$country = trim((string)$o['country']);
				$sub .= ' The three below are among those available in ' . ($country !== '' ? '<b>' . $E($country) . '</b>' : 'your country') . '.';
			}
			$sub .= ' Sorted, never scored.';

			fec_fxrate_add_assets();
			$h  = '<section class="section fxr-home" aria-label="Welcome packages, priced">';
			$h .=   '<div class="wrap">';
			$h .=     '<div class="sec-head">';
			$h .=       '<div class="left">';
			$h .=         '<div class="eyebrow">FXRate ' . "\xC2\xB7" . ' Welcome packages</div>';
			$h .=         '<h2 class="sec-title">Welcome packages, priced.</h2>';
			$h .=         '<p class="sec-sub">' . $sub . '</p>';
			$h .=       '</div>';
			$h .=       '<a class="sec-link" href="' . $E($indexUrl) . '">All ' . (int)$st['packages_total'] . ' packages &rarr;</a>';
			$h .=     '</div>';
			$h .=     '<div class="fxr-home-tiles">';
			foreach ($picks as $p) $h .= fec_fxrate_home_tile_html($p);
			$h .=     '</div>';
			$h .=     '<p class="fxr-foot">Four numbers from the operator&rsquo;s own terms: the banner, the deposit that collects every cap, the effective match on that money, the stake to clear the wagering. Nothing estimated, no score, no &ldquo;best&rdquo;. '
				. '<a class="fxr-method" href="' . $E($methodUrl) . '">How FXRate works</a></p>';
			$h .=   '</div>';
			$h .= '</section>';
			return $h;
		} catch (Exception $e) {
			return '';
		}
	}
}