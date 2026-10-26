<?php
/**
 * FXRate text pass — what a bonus article (jos_bonuses.additional_infos,
 * the "infos" field, Decision Notes v13 skeleton) states about the welcome
 * package it belongs to. Pure text, no DB, no Joomla, PHP 5.6-safe.
 *
 * Install: components/com_bonuses/helpers/fec_fxrate_text.php
 * Version: 0.1.5 (2026-10-19)
 *   0.1.5  the spins' own wagering, on leg rows and on the headline: "50 fs no
 *          wagering", "50 wager-free spins", "100 free spins (40x winnings)",
 *          "spins wagering 35x" → spins_wr 0 / N; null when the row states
 *          none. Read only when the row states spins, and a bare "no
 *          wagering" only when it follows the spins mention (before it, it
 *          may be the match's); nothing guessed either way
 *   0.1.4  leg rows also state a maximum cashout: "max cashout €500" (abs),
 *          "max cashout 5x the deposit" (x_dep), "5x bonus" (x_bonus); a bare
 *          "x5" with no base is kept as the number with no unit (the site's
 *          "unclassified" reading) — never guessed into one
 *   0.1.3  a "1st deposit / 2nd deposit…" row is a stated leg whether or not it
 *          carries "up to": percentage, cap and spins are each optional (null =
 *          not stated), the row still counts for the deposit count and the
 *          package reading; deposits from the highest leg row when the prose
 *          states no count; "deposit 2" / "second leg" keys accepted
 *   0.1.2  track names with a line break inside are normalised
 *   0.1.1  connector-word allowlist between "%" and "up to"; own value also from H3 and
 *          from the DB row; a candidate with the row's own percentage and no known cap is "own"
 *   0.1.0  first cut
 *
 * Discipline (same as the blog desk, helpers/blogtext.php): a value is taken
 * only when the text states exactly one; several different values → null and
 * an 'ambiguous_*' flag; nothing is inferred from category norms. Sources, in
 * order of trust: the kvlist rows (<span class="k">…</span><span class="v">…</span>),
 * then the overview paragraph, then the whole text.
 *
 * fec_fxrate_text_parse($html, $opts) returns ($opts['own'] = array('pct'=>,'cap'=>) of the row itself, optional):
 *   covers_all_legs bool  the article is about the whole package (H2 says "Welcome Package"), not one leg
 *   package      bool   the text places this bonus inside a multi-deposit welcome package
 *   leg_no       int|null  stated position ("first leg", "leg two", "opening leg")
 *   deposits     int|null  stated number of deposits ("three-deposit", "across the first three deposits")
 *   headline     array|null  array('pct'=>, 'cap'=>, 'sym'=>, 'spins'=>, 'raw'=>) — the advertised package line
 *   track        string|null  a named variant ("Highroller Welcome Pack", "Japan Package")
 *   legs_stated  array   position => array('pct'=>, 'cap'=>, 'sym'=>, 'spins'=>, 'spins_wr'=>, 'max_cashout'=>, 'max_cashout_kind'=>)
 *                       from "1st deposit / 2nd deposit…" rows; pct and cap are null when the row does not
 *                       state them ("50% match", "100 free spins"), the row still counts as a leg;
 *                       spins_wr: the wagering on the spins' winnings, 0 = none stated as none, N = N×,
 *                       null = not stated (headline carries one too, for its spins);
 *                       max_cashout null unless stated, kind 'abs'|'x_dep'|'x_bonus'|'' (unit not stated)
 *   standalone   bool   the text says the bonus is NOT part of a package
 *   sources      array  where each value came from (kvlist|overview|text)
 *   flags        array
 */

if (!defined('FEC_FXRATE_TEXT_VERSION')) {
	define('FEC_FXRATE_TEXT_VERSION', '0.1.5');
}

/** Decode entities and collapse whitespace; keeps the text, drops the tags. */
function fec_fxrate_text_plain($html) {
	$s = (string)$html;
	$s = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', ' ', $s);
	$s = preg_replace('~</(p|li|h[1-6]|div|section|tr|summary|details)>~i', "$0\n", $s);
	$s = strip_tags($s);
	$s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$s = str_replace(array("\xc2\xa0", "\xe2\x80\x93", "\xe2\x80\x94"), array(' ', '-', '-'), $s); // nbsp, en/em dash
	$s = str_replace(array("\xe2\x80\x98", "\xe2\x80\x99"), "'", $s);
	$s = preg_replace('~[ \t]+~', ' ', $s);
	$s = preg_replace('~\s*\n\s*~', "\n", $s);
	return trim($s);
}

/** kvlist rows: array of array('k' => plain key, 'v' => plain value). */
function fec_fxrate_text_kvlist($html) {
	$rows = array();
	if (preg_match_all('~<span class="k">(.*?)</span>\s*<span class="v">(.*?)</span>~is', (string)$html, $m, PREG_SET_ORDER)) {
		foreach ($m as $r) {
			$rows[] = array('k' => fec_fxrate_text_plain($r[1]), 'v' => fec_fxrate_text_plain($r[2]));
		}
	}
	return $rows;
}

/** Word or digit → int ("three" → 3, "3" → 3, "3rd" → 3). */
function fec_fxrate_text_number($w) {
	$w = strtolower(trim((string)$w));
	$w = preg_replace('~^(\d+)(?:st|nd|rd|th)$~', '$1', $w); // "3rd" → "3", never "second" → "seco"
	if (ctype_digit($w)) return (int)$w;
	$words = array('one' => 1, 'first' => 1, 'opening' => 1, 'two' => 2, 'second' => 2, 'three' => 3, 'third' => 3,
		'four' => 4, 'fourth' => 4, 'five' => 5, 'fifth' => 5, 'six' => 6, 'sixth' => 6, 'seven' => 7, 'seventh' => 7,
		'eight' => 8, 'eighth' => 8, 'nine' => 9, 'ninth' => 9, 'ten' => 10, 'tenth' => 10);
	return isset($words[$w]) ? $words[$w] : null;
}

/**
 * The wagering stated for the spins of a string, when the string mentions spins:
 * 0 for "no wagering" / "wager-free" / "no WR" (the phrase after the spins, or as
 * their adjective: before the spins it may be the match's), N for "40x winnings",
 * "40x wagering on the spins", "spins wagering 40x", "FS WR 40x"; null when
 * nothing is stated for the spins. The match's own "35x wagering" is not read.
 */
function fec_fxrate_text_spins_wr_in($s) {
	$s = (string)$s;
	$spinsRe = '~\b(?:\d{1,4}\s+)?(?:(?:free|bonus|extra)\s+spins|spins|fs)\b~i';
	if (!preg_match($spinsRe, $s, $sm, PREG_OFFSET_CAPTURE)) return null;
	$after = substr($s, $sm[0][1] + strlen($sm[0][0]));
	$none  = '(?:no|zero|without|free\s+of|0x)\s*(?:-\s*)?(?:wagering|wager|wr|playthrough|rollover|turnover)(?:\s+requirements?)?|wager(?:ing)?[- ]free|playthrough[- ]free';
	// "50 wager-free spins", "no-wagering free spins": the adjective form, anywhere
	if (preg_match('~\b(?:' . $none . ')\s+(?:free\s+|bonus\s+|extra\s+)?spins\b~i', $s)) return 0.0;
	// "40x winnings", "40x on the spins' winnings", "winnings wagered 40x": any position
	if (preg_match('~(\d{1,3}(?:\.\d+)?)\s*(?:x|×)\s*(?:wagering\s+|wr\s+|playthrough\s+)?(?:on\s+|of\s+)?(?:the\s+|their\s+|any\s+)?(?:(?:free\s+)?spins?\s*\'?s?\s+|fs\s+)?winnings?\b~iu', $s, $m)) return (float)$m[1];
	if (preg_match('~\bwinnings?\s+(?:are\s+|to\s+be\s+|must\s+be\s+)?(?:wagered|rolled\s+over|played\s+through)\s*(\d{1,3}(?:\.\d+)?)\s*(?:x|×)~iu', $s, $m)) return (float)$m[1];
	// "spins wagering 40x", "FS WR: x40", "wagering on the free spins 40x"
	if (preg_match('~\b(?:(?:free\s+)?spins?|fs)\s*(?:winnings\s+)?(?:wr|wagering|playthrough|rollover)\s*(?:requirement\s*)?(?:of\s+|is\s+|:\s*)?(?:x|×)?\s*(\d{1,3}(?:\.\d+)?)\s*(?:x|×)?~iu', $s, $m)) return (float)$m[1];
	if (preg_match('~(\d{1,3}(?:\.\d+)?)\s*(?:x|×)\s*(?:wr|wagering|playthrough|rollover)\s+(?:on|for)\s+(?:the\s+)?(?:(?:free\s+)?spins?|fs)\b~iu', $s, $m)) return (float)$m[1];
	if (preg_match('~\b(?:wr|wagering|playthrough|rollover)\s+(?:on|for)\s+(?:the\s+)?(?:(?:free\s+)?spins?|fs)(?:\s+winnings)?\s*(?:of\s+|is\s+|:\s*)?(?:x|×)?\s*(\d{1,3}(?:\.\d+)?)\s*(?:x|×)?~iu', $s, $m)) return (float)$m[1];
	// a bare "no wagering" after the spins: "50 fs no wagering", "100 free spins (wager-free)"
	if (preg_match('~^\W*(?:\([^)]*\)\s*)?(?:with\s+|,\s*|-\s*|and\s+)?(?:' . $none . ')\b~i', $after)) return 0.0;
	if (preg_match('~\((?:' . $none . ')\)~i', $after)) return 0.0;
	return null;
}

/**
 * Parse one "N% up to SYM AMOUNT (+ N free spins)" headline out of a string.
 * Returns array('pct','cap','sym','spins','raw') or null. "€/$2,500" keeps the first symbol.
 */
function fec_fxrate_text_headline_in($s) {
	$sym = '(?:A\$|C\$|NZ\$|€/\$|\$/€|€|\$|£|R\$|R|kr|mBTC|BTC|USDT)';
	// Up to six connector words may sit between the percentage and "up to"
	// ("125% Highroller First Deposit Bonus up to", "375% welcome package worth up to"),
	// but only words from this list: "300% reads as three redemptions of up to" is not a headline.
	$gap = '(?:welcome|package|pack|bundle|bonus|bonuses|match|deposit|deposits|first|second|third|1st|2nd|3rd|offer|worth|totalling|total|totals|highroller|high|roller|crypto|bitcoin|vip|standard|regular|casino|sports|slots|reload|extra|free|spins|plus|and|of|the|a|an|in|on|to|your|its|their|is|are|that|which|-)';
	$re = '~(\d{1,4}(?:\.\d+)?)\s*%(?:\s+' . $gap . '){0,6}?\s+up\s+to\s*(' . $sym . ')?\s*([\d][\d,]*(?:\.\d+)?)\s*(' . $sym . ')?(?:\s*(?:\+|plus|and)\s*(\d{1,4})\s*(?:free\s+spins|bonus\s+spins|extra\s+spins|spins|fs)\b)?~iu';
	if (!preg_match($re, $s, $m, PREG_OFFSET_CAPTURE)) return null;
	$symbol = '';
	if (!empty($m[2][0])) $symbol = $m[2][0]; elseif (!empty($m[4][0])) $symbol = $m[4][0];
	if (strpos($symbol, '/') !== false) $symbol = substr($symbol, 0, strpos($symbol, '/'));
	$spins = (isset($m[5]) && $m[5][0] !== '') ? (int)$m[5][0] : 0;
	if ($spins === 0) {
		// "…, plus 100 free spins on Cherry Pop": one spin count elsewhere in the same string
		if (preg_match_all('~\b(\d{1,4})\s+(?:free|bonus|extra)\s+spins\b~i', $s, $sp) && count(array_unique($sp[1])) === 1) {
			$spins = (int)$sp[1][0];
		}
	}
	return array(
		'pct'    => (float)$m[1][0],
		'cap'    => (float)str_replace(',', '', $m[3][0]),
		'sym'    => $symbol,
		'spins'  => $spins,
		'spins_wr' => ($spins > 0) ? fec_fxrate_text_spins_wr_in($s) : null,
		'raw'    => trim($m[0][0]),
		'offset' => (int)$m[0][1],
	);
}

/**
 * Parse one leg row value ("1st deposit → …"). Every part is optional and null
 * when the row does not state it: "100% match up to €1,000", "50% match" (no
 * cap stated), "up to $500" (no percentage), "100 free spins" (no match).
 * Returns array('pct','cap','sym','spins','raw'); never null — the row itself
 * states that the deposit exists.
 */
function fec_fxrate_text_leg_in($s) {
	$s = trim((string)$s);
	$sym = '(?:A\$|C\$|NZ\$|€/\$|\$/€|€|\$|£|R\$|R|kr|mBTC|BTC|USDT)';
	$out = array('pct' => null, 'cap' => null, 'sym' => '', 'spins' => 0, 'spins_wr' => null, 'max_cashout' => null, 'max_cashout_kind' => '', 'raw' => $s);
	if ($s === '') return $out;

	// maximum cashout, read first and cut out so its number is not mistaken for a cap or a rate:
	// "max cashout €500", "max. withdrawal of 5x the deposit", "max cashout x5 for cash" (no unit)
	$mcRe = '~\bmax(?:imum)?\.?\s*(?:cash-?out|cashable|withdraw(?:al|able)?|win(?:nings)?|conversion)(?:\s+(?:amount|limit|of|is|:))*\s*'
		. '(?:(' . $sym . ')\s*([\d][\d,]*(?:\.\d+)?)|([\d][\d,]*(?:\.\d+)?)\s*(' . $sym . ')|(?:x|×)\s*(\d{1,3}(?:\.\d+)?)|(\d{1,3}(?:\.\d+)?)\s*(?:x|×))'
		. '(?:\s*(?:the\s+|of\s+(?:the\s+)?)?(deposit|bonus(?:\s+amount)?))?~iu';
	if (preg_match($mcRe, $s, $mc)) {
		if (!empty($mc[2])) { $out['max_cashout'] = (float)str_replace(',', '', $mc[2]); $out['max_cashout_kind'] = 'abs'; }
		elseif (!empty($mc[3])) { $out['max_cashout'] = (float)str_replace(',', '', $mc[3]); $out['max_cashout_kind'] = 'abs'; }
		else {
			$mult = !empty($mc[5]) ? $mc[5] : (!empty($mc[6]) ? $mc[6] : '');
			if ($mult !== '') {
				$out['max_cashout'] = (float)$mult;
				$base = isset($mc[7]) ? strtolower($mc[7]) : '';
				$out['max_cashout_kind'] = ($base === 'deposit') ? 'x_dep' : ((strpos($base, 'bonus') === 0) ? 'x_bonus' : '');
			}
		}
		if ($out['max_cashout'] !== null && $out['max_cashout'] <= 0) { $out['max_cashout'] = null; $out['max_cashout_kind'] = ''; }
		$s = trim(str_replace($mc[0], ' ', $s));
	}

	// percentage: the first "N%" that is a match rate, not a game weighting or an RTP figure
	if (preg_match_all('~(\d{1,4}(?:\.\d+)?)\s*%(?!\s*(?:weighting|contribut|of\s+(?:slots|games|the)|rtp|return))~iu', $s, $pm)) {
		$out['pct'] = (float)$pm[1][0];
	}

	// cap: "up to SYM AMOUNT", "up to AMOUNT SYM", "capped at …", "maximum bonus of …"
	$re = '~(?:up\s+to|capped\s+at|max(?:imum)?\s+bonus(?:\s+of)?)\s*(' . $sym . ')?\s*([\d][\d,]*(?:\.\d+)?)\s*(' . $sym . ')?~iu';
	if (preg_match($re, $s, $cm)) {
		$symbol = '';
		if (!empty($cm[1])) $symbol = $cm[1]; elseif (!empty($cm[3])) $symbol = $cm[3];
		if (strpos($symbol, '/') !== false) $symbol = substr($symbol, 0, strpos($symbol, '/'));
		$out['cap'] = (float)str_replace(',', '', $cm[2]);
		$out['sym'] = $symbol;
	}

	// spins bundled with the leg: one count stated ("70 free spins", "70 fs")
	if (preg_match_all('~\b(\d{1,4})\s+(?:(?:free|bonus|extra)\s+spins|fs)\b~i', $s, $sp) && count(array_unique($sp[1])) === 1) {
		$out['spins'] = (int)$sp[1][0];
		// the spins' own wagering (0.1.5): stated for the spins, or null
		$out['spins_wr'] = fec_fxrate_text_spins_wr_in($s);
	}
	return $out;
}

/** Distinct values of a list of scalars/arrays (arrays compared on pct+cap+spins). */
function fec_fxrate_text_distinct(array $vals) {
	$seen = array(); $out = array();
	foreach ($vals as $v) {
		$key = is_array($v) ? ($v['pct'] . '|' . $v['cap'] . '|' . $v['spins']) : (string)$v;
		if (isset($seen[$key])) continue;
		$seen[$key] = true; $out[] = $v;
	}
	return $out;
}

function fec_fxrate_text_parse($html, array $opts = array()) {
	$res = array(
		'covers_all_legs' => false, 'package' => false, 'leg_no' => null, 'deposits' => null, 'headline' => null, 'track' => null,
		'legs_stated' => array(), 'standalone' => false, 'sources' => array(), 'flags' => array(),
	);
	$html = (string)$html;
	if (trim($html) === '') { $res['flags'][] = 'no_text'; return $res; }

	$kv = fec_fxrate_text_kvlist($html);
	$text = fec_fxrate_text_plain($html);
	$lower = strtolower($text);

	// The bonus's own value (H1/H2 and the "Bonus value" row) and whether the
	// article is about the whole package rather than one leg of it.
	$own = array();
	$h2 = '';
	if (preg_match('~<h[123][^>]*>(.*?)</h[123]>~is', $html, $hm)) {
		$h2 = fec_fxrate_text_plain($hm[1]);
		$oh = fec_fxrate_text_headline_in($h2);
		if ($oh) $own[] = $oh;
	}
	foreach ($kv as $row) {
		if (preg_match('~^bonus\s+value~i', $row['k'])) { $ov = fec_fxrate_text_headline_in($row['v']); if ($ov) $own[] = $ov; }
	}
	if (isset($opts['own']) && is_array($opts['own']) && isset($opts['own']['pct']) && $opts['own']['pct'] !== null) {
		$own[] = array('pct' => (float)$opts['own']['pct'],
			'cap' => (isset($opts['own']['cap']) && $opts['own']['cap'] !== null) ? (float)$opts['own']['cap'] : null, 'spins' => 0);
	}
	$subjectIsPackage = (bool)preg_match('~\b(?:welcome\s+)?(?:package|pack|bundle)\b~i', $h2);
	foreach ($kv as $row) {
		if (preg_match('~^bonus\s+type~i', $row['k']) && preg_match('~\bpackage\b~i', $row['v']) && !preg_match('~\bleg\b~i', $row['v'])) $subjectIsPackage = true;
	}
	$res['covers_all_legs'] = $subjectIsPackage;

	// Overview = the first section (ends at the next kicker/heading or 1200 chars).
	$overview = $text;
	if (preg_match('~OVERVIEW\s*\n(.{40,1500}?)\n(?:HOW THE BONUS WORKS|\S[^\n]{0,60}\n)~s', $text, $m)) $overview = $m[1];
	else $overview = substr($text, 0, 1200);
	$overviewLower = strtolower($overview);

	$pkgWords = '(?:welcome\s+(?:package|pack|bundle|offer|bonus\s+package)|deposit\s+(?:package|pack|bundle|ladder)|(?:match\s+)?package|\bpack\b)';

	// ---- package membership --------------------------------------------
	$depositsStated = array(); $legStated = array();
	$pkgContext = false;

	// "three-deposit welcome package", "4-deposit package", "three-stage", "across the first three deposits"
	if (preg_match_all('~\b(two|three|four|five|six|seven|eight|nine|ten|\d)[- ](?:deposit|stage|step|part|tier)s?\b[^.\n]{0,40}?' . $pkgWords . '~i', $text, $mm)) {
		foreach ($mm[1] as $w) { $n = fec_fxrate_text_number($w); if ($n) $depositsStated[] = $n; }
		$pkgContext = true;
	}
	if (preg_match_all('~(?:across|over|on|spread\s+over|covering)\s+(?:the\s+|your\s+)?first\s+(two|three|four|five|six|seven|eight|nine|ten|\d)\s+deposits~i', $text, $mm)) {
		foreach ($mm[1] as $w) { $n = fec_fxrate_text_number($w); if ($n) $depositsStated[] = $n; }
		$pkgContext = true;
	}
	if (preg_match_all('~\b(two|three|four|five|six|seven|eight|nine|ten|\d)\s+deposits?\s+(?:combined|in\s+total|welcome)~i', $text, $mm)) {
		foreach ($mm[1] as $w) { $n = fec_fxrate_text_number($w); if ($n) $depositsStated[] = $n; }
		$pkgContext = true;
	}

	// "the first leg of", "leg one of a", "opening leg", "second leg", "final leg"
	if (preg_match_all('~\b(first|opening|second|third|fourth|fifth|sixth|1st|2nd|3rd|4th|5th|6th)\s+(?:and\s+(?:final|last)\s+)?leg\b~i', $text, $mm)) {
		foreach ($mm[1] as $w) { $n = fec_fxrate_text_number($w); if ($n) $legStated[] = $n; }
		$pkgContext = true;
	}
	if (preg_match_all('~\bleg\s+(one|two|three|four|five|six|\d)\b~i', $text, $mm)) {
		foreach ($mm[1] as $w) { $n = fec_fxrate_text_number($w); if ($n) $legStated[] = $n; }
		$pkgContext = true;
	}
	if (preg_match('~\b(?:final|last|closing)\s+leg\b~i', $text)) $pkgContext = true;

	// "part of the … welcome package", "welcome package" in overview or kvlist
	foreach ($kv as $row) {
		if (preg_match('~^welcome\s+(?:package|pack|bundle|offer)~i', $row['k'])) $pkgContext = true;
		if (preg_match('~\bpackage\b~i', $row['v']) && preg_match('~^bonus\s+type~i', $row['k'])) $pkgContext = true;
	}
	if (preg_match('~\b(?:part|leg|stage|step|half|opening|first\s+instalment)\s+of\s+(?:the|a|an|its|their|[A-Z][\w\'\.]*\'?s)\b[^.\n]{0,80}?' . $pkgWords . '~i', $text)) $pkgContext = true;

	// explicit "not part of a package" / "standalone"
	if (preg_match('~\b(?:not\s+(?:part\s+of|tied\s+to|bundled\s+(?:in|with))\s+(?:a|any|the)\s+[^.\n]{0,30}?(?:package|pack|bundle)|standalone\s+(?:offer|bonus|match)|a\s+single[- ]deposit\s+(?:offer|bonus))~i', $text)) {
		$res['standalone'] = true;
	}

	$res['package'] = $pkgContext && !$res['standalone'];

	$d = fec_fxrate_text_distinct($depositsStated);
	if (count($d) === 1) { $res['deposits'] = $d[0]; $res['sources']['deposits'] = 'text'; }
	elseif (count($d) > 1) { $res['flags'][] = 'ambiguous_deposits'; }

	$l = fec_fxrate_text_distinct($legStated);
	if ($subjectIsPackage) { /* the article covers every leg: no single position */ }
	elseif (count($l) === 1) { $res['leg_no'] = $l[0]; $res['sources']['leg_no'] = 'text'; }
	elseif (count($l) > 1) { $res['flags'][] = 'ambiguous_leg_no'; }

	// ---- headline --------------------------------------------------------
	$heads = array(); $src = '';
	foreach ($kv as $row) {
		if (preg_match('~^welcome\s+(?:package|pack|bundle|offer)|^package\s+(?:value|total|headline)|^headline\s+value~i', $row['k'])) {
			$h = fec_fxrate_text_headline_in($row['v']);
			if ($h) { $heads[] = $h; $src = 'kvlist'; }
		}
	}
	if (!$heads) {
		// sentences in the overview that mention the package and carry a headline
		foreach (preg_split('~(?<=[.!?])\s+|\n~', $overview) as $sent) {
			if (!preg_match('~' . $pkgWords . '~i', $sent)) continue;
			$h = fec_fxrate_text_headline_in($sent);
			if ($h) { $heads[] = $h; $src = 'overview'; }
		}
	}
	if (!$heads) {
		foreach (preg_split('~(?<=[.!?])\s+|\n~', $text) as $sent) {
			if (!preg_match('~' . $pkgWords . '~i', $sent)) continue;
			if (!preg_match('~\b(?:totalling|total|combined|across|package\s+(?:worth|of)|up\s+to)\b~i', $sent)) continue;
			$h = fec_fxrate_text_headline_in($sent);
			if ($h) { $heads[] = $h; $src = 'text'; }
		}
	}
	if (!$subjectIsPackage && $own) {
		$keep = array();
		foreach ($heads as $h) {
			$isOwn = false;
			foreach ($own as $o) {
				// Same percentage, and the same cap or no cap known on either side: that is the bonus itself.
				if (abs($h['pct'] - $o['pct']) >= 0.01) continue;
				if ($o['cap'] === null || $o['cap'] <= 0 || $h['cap'] <= 0 || abs($h['cap'] - $o['cap']) < 0.01) $isOwn = true;
			}
			if (!$isOwn) $keep[] = $h;
		}
		if ($heads && !$keep) $res['flags'][] = 'headline_is_own_value';
		$heads = $keep;
	}
	$hd = fec_fxrate_text_distinct($heads);
	if (count($hd) === 1) { unset($hd[0]['offset']); $res['headline'] = $hd[0]; $res['sources']['headline'] = $src; }
	elseif (count($hd) > 1) { $res['flags'][] = 'ambiguous_headline'; }

	// ---- named track -----------------------------------------------------
	if (preg_match_all('~\b((?:high[- ]?roller|highroller|vip|crypto|bitcoin|standard|regular|japan|samurai|ninja|sports?|casino|live)\s+(?:welcome\s+)?(?:pack(?:age)?|offer|bundle|track))\b~i', $text, $mm)) {
		$norm = array(); foreach ($mm[1] as $x) $norm[] = strtolower(preg_replace('~\s+~', ' ', $x));
		$t = fec_fxrate_text_distinct($norm);
		if (count($t) === 1) { $res['track'] = ucwords($t[0]); $res['sources']['track'] = 'text'; }
		elseif (count($t) > 1) { $res['flags'][] = 'ambiguous_track'; }
	}

	// ---- per-leg terms stated in the kvlist ("1st deposit → 100% match up to €1,000",
	//      "2nd deposit → 50% match" with no cap stated, "3rd deposit → 100 free spins").
	//      The key names the deposit: "1st deposit", "second deposit", "deposit 2", "leg two",
	//      optionally followed by match/bonus/offer/terms. Other keys that start the same way
	//      ("1st deposit wagering") are other facts and are left alone. Every such row is a
	//      stated leg: what it does not state stays null, the position still counts.
	$legKey = '~^(?:(\d{1,2})(?:st|nd|rd|th)\s+(?:deposit|leg)|(first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth)\s+(?:deposit|leg)|(?:deposit|leg)\s+(\d{1,2}|one|two|three|four|five|six|seven|eight|nine|ten))(?:\s+(?:match|bonus|offer|terms))?\s*:?\s*$~i';
	foreach ($kv as $row) {
		if (!preg_match($legKey, $row['k'], $m)) continue;
		$word = '';
		for ($i = 1; $i <= 3; $i++) { if (isset($m[$i]) && $m[$i] !== '') { $word = $m[$i]; break; } }
		$pos = fec_fxrate_text_number($word);
		if (!$pos) continue;
		if (isset($res['legs_stated'][$pos])) { $res['flags'][] = 'duplicate_leg_row_' . $pos; continue; }
		$leg = fec_fxrate_text_leg_in($row['v']);
		if ($leg['pct'] === null && $leg['cap'] === null && $leg['spins'] === 0) $res['flags'][] = 'leg_row_unparsed_' . $pos;
		$res['legs_stated'][$pos] = $leg;
	}
	if ($res['legs_stated']) {
		ksort($res['legs_stated']);
		$res['sources']['legs_stated'] = 'kvlist';
		$top = max(array_keys($res['legs_stated']));
		// a row for a 2nd deposit (or later) is the article stating a multi-deposit package
		if (!$res['package'] && !$res['standalone'] && $top > 1) $res['package'] = true;
		// the highest leg row is a stated count when the prose gives none
		if ($res['deposits'] === null && $top > 1 && !in_array('ambiguous_deposits', $res['flags'], true)) {
			$res['deposits'] = $top; $res['sources']['deposits'] = 'kvlist';
		} elseif ($res['deposits'] !== null && $res['deposits'] < $top) {
			$res['flags'][] = 'deposits_below_leg_rows';
		}
	}

	if (!$res['package'] && !$res['standalone']) $res['flags'][] = 'no_package_mention';
	return $res;
}

/** One-line human summary of a parse result, for reports. */
function fec_fxrate_text_describe(array $t) {
	if (in_array('no_text', $t['flags'], true)) return 'no article';
	if ($t['standalone']) return 'standalone (text says not part of a package)';
	if (!$t['package']) return 'no package mention';
	$bits = array();
	if (!empty($t['covers_all_legs'])) $bits[] = 'article covers the whole package';
	$bits[] = $t['deposits'] !== null ? $t['deposits'] . ' deposits' : 'deposits not stated';
	if ($t['leg_no'] !== null) $bits[] = 'leg ' . $t['leg_no'];
	if ($t['headline']) $bits[] = '"' . $t['headline']['raw'] . '"';
	if ($t['track']) $bits[] = $t['track'];
	if ($t['legs_stated']) $bits[] = count($t['legs_stated']) . ' leg rows stated';
	if ($t['flags']) $bits[] = '[' . implode(',', $t['flags']) . ']';
	return implode(' · ', $bits);
}