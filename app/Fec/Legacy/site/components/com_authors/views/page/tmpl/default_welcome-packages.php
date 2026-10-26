<?php
defined('_JEXEC') or die;

/**
 * FEC Welcome Package Index — /bonuses/welcome-packages
 * (Joomla layout suffix: default_welcome-packages.php; kind='page' topic 'welcome-packages')
 *
 * Every multi-deposit welcome package listed on the site, priced by FXRate
 * (FEC Tools No. 05) from the operator's own terms: advertised match, deposit
 * needed to collect every cap, effective match on that money, turnover. Data
 * comes from models/packageindex.php; this file only lays it out and lets the
 * reader re-sort and filter it client side (progressive: the first paint is
 * server-rendered, effective match highest first).
 *
 * Editorial rules baked in:
 *   - A list, not a ranking: no rank numbers, no score, the reader picks the
 *     order. The default order is stated in plain words above the table.
 *   - Facts only: four numbers per package, "not stated" where a term is
 *     missing, unpriced packages in their own table, nothing estimated.
 *   - Country-neutral copy. No competitor names.
 *   - Byline voice: David Romano (bonus maths) — short declaratives, numbers
 *     carry the argument, no first person. Reviewed by Ethan Walker (terms).
 *
 * Layout reuses the .au-* / .rg-* / .pi-* primitives of the trust pages plus
 * the .wp-* additions in assets/page.css (section 16).
 */

require_once JPATH_COMPONENT . '/models/packageindex.php';

$cfg      = is_array($this->config) ? $this->config : array();
$author   = $this->author;
$reviewer = $this->reviewer;

$h   = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$pct = function ($v) { return AuthorsModelPackageindex::fmtPct($v); };
$mon = function ($v, $sym, $ceil = false) { return AuthorsModelPackageindex::fmtMoney($v, $sym, $ceil); };
// The casino chip of the cards and heroes (initial on the brand's own hue, fec_casino_chip):
// the same mark for the same casino everywhere on the site. Local fallback with the same
// algorithm (crc32 of the lowercased name, mod 360) when the lib is not loaded.
$chip = function ($name) {
    if (function_exists('fec_casino_chip')) return fec_casino_chip($name, 28);
    $name = trim((string)$name);
    $initial = ($name !== '') ? (function_exists('mb_substr') ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($name, 0, 1))) : '?';
    $hue = ($name === '') ? 0 : crc32(function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name)) % 360;
    if ($hue < 0) $hue += 360;
    return '<span class="casino-chip" aria-hidden="true" style="width:28px;height:28px;font-size:12.9px;background:hsl(' . $hue . ',46%,90%);color:hsl(' . $hue . ',52%,30%)">'
        . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</span>';
};

// ---- Visitor country (the site's own detector when the lib is loaded: Cloudflare header, then GeoIP;
//      a detector fallback to its default is NOT a known country; unknown = no geo note) ----
$cc = '';
if (function_exists('fec_detect_country')) {
    $det = fec_detect_country('');
    if (is_array($det) && isset($det['src'], $det['cc_up']) && $det['src'] !== 'default') {
        $ccRaw = strtolower(trim((string)$det['cc_up']));
        if (preg_match('/^[a-z]{2}$/', $ccRaw) && $ccRaw !== 'xx') $cc = $ccRaw;
    }
} elseif (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
    $ccRaw = strtolower(trim((string)$_SERVER['HTTP_CF_IPCOUNTRY']));
    if (preg_match('/^[a-z]{2}$/', $ccRaw) && $ccRaw !== 'xx') $cc = $ccRaw;
}
$ccName = '';
if ($cc !== '') {
    $ccName = strtoupper($cc);
    if (function_exists('fec_country_name') && isset($GLOBALS['countries_array']) && is_array($GLOBALS['countries_array'])) {
        $ccName = fec_country_name(strtoupper($cc), $GLOBALS['countries_array'], strtoupper($cc));
    }
}

$data     = AuthorsModelPackageindex::data($cc);
$live     = !empty($data['live']);
$priced   = $data['priced'];
$unpriced = $data['unpriced'];
$st       = $data['stats'];
$dataAsOf = (string)$data['data_as_of'];
$dataAsOfTxt = AuthorsModelPackageindex::fmtDate($dataAsOf, true);

$updated    = isset($cfg['last_updated']) ? (string)$cfg['last_updated'] : '';
$updatedTxt = '';
if ($updated !== '') {
    try { $dt = new DateTime($updated); $updatedTxt = $dt->format('j F Y'); } catch (Exception $e) { $updatedTxt = $updated; }
}

// ---- URLs -----------------------------------------------------------------
$siteRoot = rtrim(JUri::root(), '/');
$abs = function ($u) use ($siteRoot) {
    $u = trim((string)$u);
    if ($u === '') return '';
    if (preg_match('#^https?://#i', $u)) return $u;
    return $siteRoot . '/' . ltrim($u, '/');
};
$authorUrl   = ($author   && !empty($author['slug']))   ? JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($author['slug']))   : '';
$reviewerUrl = ($reviewer && !empty($reviewer['slug'])) ? JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($reviewer['slug'])) : '';
// 2.7.78: the page's own URL is the public path, not the menu route. JRoute builds the
// route from the menu tree (parent alias + item alias) and the Bonuses parent does not
// carry the public /bonuses prefix; every inbound link (bonus band, casino block, hub
// pill, bot, method page) already uses AuthorsModelPackageindex::INDEX_PATH.
$pageRel     = JUri::base(true) . AuthorsModelPackageindex::INDEX_PATH;
$pageAbs     = $abs($pageRel);
$methodUrl   = JRoute::_('index.php?option=com_authors&view=methodology&topic=fxrate');
$fxcheckUrl  = JRoute::_('index.php?option=com_authors&view=methodology&topic=fxcheck');
$discloseUrl = JRoute::_('index.php?option=com_authors&view=page&slug=affiliate-disclosure');
$bonusesUrl  = JUri::base(true) . '/bonuses/';
$casinosUrl  = JUri::base(true) . '/online-casinos';

// ---- Breadcrumb (site lib helpers, when loaded) ---------------------------
$crumbHtml = ''; $crumbJsonLd = '';
if (function_exists('fec_build_breadcrumb_items_from_path') && function_exists('fec_render_breadcrumb_html')) {
    $path       = JUri::getInstance()->getPath();
    $siteBase   = rtrim(JUri::base(true), '/');
    $crumbItems = fec_build_breadcrumb_items_from_path($path, $siteBase);
    if (!empty($crumbItems)) {
        $lastIdx = count($crumbItems) - 1;
        if (isset($crumbItems[$lastIdx]) && is_array($crumbItems[$lastIdx])) {
            foreach (array('name', 'label', 'title', 'text') as $k) {
                if (array_key_exists($k, $crumbItems[$lastIdx])) $crumbItems[$lastIdx][$k] = $cfg['title'];
            }
        }
        $crumbHtml = fec_render_breadcrumb_html($crumbItems);
        if (function_exists('fec_render_breadcrumb_jsonld')) $crumbJsonLd = fec_render_breadcrumb_jsonld($crumbItems);
    }
}

// ---- Stats strip cells ----------------------------------------------------
$cells = array(
    array('num' => $live ? number_format((int)$st['packages_priced']) : '&mdash;', 'lbl' => 'Packages priced'),
    array('num' => $live ? number_format((int)$st['casinos']) : '&mdash;',         'lbl' => 'Casinos'),
    array('num' => $live && $st['median_advertised'] !== null ? $pct($st['median_advertised']) : '&mdash;', 'lbl' => 'Median advertised match'),
    array('num' => $live && $st['median_effective'] !== null ? $pct($st['median_effective']) : '&mdash;',   'lbl' => 'Median effective match'),
);

// ---- Effective pill class: >= 100 teal, 50-99 neutral, < 50 amber ---------
$effClass = function ($e) {
    if ($e === null) return 'pi-delta--ontime';
    if ($e >= 100) return 'pi-delta--faster';
    if ($e < 50)  return 'pi-delta--slower';
    return 'pi-delta--ontime';
};

// ---- FAQ (visible list + FAQPage JSON-LD from the same array) -------------
// 2.7.90: the "banner vs effective" example is the widest gap on this page,
// so the reader can find the row two scrolls up; the fixed "2,000% at 57%"
// matched no listed package.
$gapRow = null;
foreach ($priced as $pr) {
    if (!isset($pr['ratio']) || $pr['ratio'] === null) continue;
    if ($gapRow === null || (float)$pr['ratio'] > (float)$gapRow['ratio']) $gapRow = $pr;
}
$gapAdv = $gapRow ? number_format((float)$gapRow['advertised_pct']) . '%' : '2,000%';
$gapEff = $gapRow ? number_format((float)$gapRow['effective_pct']) . '%' : '57%';
$gapWho = $gapRow ? (string)$gapRow['brand'] : '';
$faq = array(
    array(
        'q' => 'Is this a ranking of the best welcome packages?',
        'a' => 'No. It is every multi-deposit package listed on FreeExtraChips with four numbers computed from its own terms, sorted however the reader chooses. No score is attached, no package is recommended, and commercial relationships have no input into what appears or where.',
    ),
    array(
        'q' => 'How can a ' . $gapAdv . ' banner come out at ' . $gapEff . '?',
        'a' => ($gapWho !== '' ? 'That is ' . $gapWho . ', the widest gap in the index today. ' : '')
             . 'The banner is the sum of the match percentages of every deposit and the effective match is what those deposits return together. A 2,000% match on a one-dollar first deposit adds 2,000 to the banner and twenty dollars to the account; the second deposit then has to carry the cap. The sum is always above the rate; the index shows by how much.',
    ),
    array(
        'q' => 'Why do some packages say "not stated"?',
        'a' => 'A package is priced only when every deposit has a stated match and cap. Where the operator describes a package but does not publish the terms of a deposit, that deposit is shown as not stated and the package is listed apart, unpriced. Nothing is estimated in its place.',
    ),
    array(
        'q' => 'Why is the first deposit sometimes better than the whole package?',
        'a' => 'Most packages open with their strongest leg and carry weaker ones behind it. When the first deposit alone has a higher match than the package average, the later deposits are diluting it; the index marks those rows so a one-deposit player can see it at once.',
    ),
    array(
        'q' => 'How often do the numbers change?',
        'a' => 'Every line is recomputed from the listing on each page view. When an operator changes a term and the listing is updated, the number moves the same day. The date under the title is the latest change to any package on the page.',
    ),
);
$faqEntities = array();
foreach ($faq as $f) {
    $faqEntities[] = array('@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => array('@type' => 'Answer', 'text' => $f['a']));
}
$faqLd = array('@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqEntities);

// ---- Dataset + unordered ItemList JSON-LD (a list, not a ranking) ---------
$listItems = array();
$pos = 0;
foreach ($priced as $r) {
    $pos++;
    $item = array('@type' => 'ListItem', 'position' => $pos, 'name' => $r['brand'] . ' welcome package');
    if ($r['url'] !== '') $item['url'] = $abs($r['url']);
    $listItems[] = $item;
}
$dataLd = array('@context' => 'https://schema.org', '@graph' => array());
if (!empty($listItems)) {
    $dataLd['@graph'][] = array(
        '@type'           => 'ItemList',
        '@id'             => $pageAbs . '#packages',
        'name'            => 'FEC Welcome Package Index — priced packages',
        'itemListOrder'   => 'https://schema.org/ItemListUnordered',
        'numberOfItems'   => count($listItems),
        'itemListElement' => $listItems,
    );
}
$dataset = array(
    '@type'               => 'Dataset',
    '@id'                 => $pageAbs . '#dataset',
    'name'                => 'FEC Welcome Package Index',
    'description'         => 'Multi-deposit casino welcome packages priced from the operator terms listed on FreeExtraChips: advertised match, deposit needed to collect every cap, effective match on that money, wagering turnover. Facts only; nothing estimated.',
    'url'                 => $pageAbs,
    'mainEntityOfPage'    => array('@id' => $pageAbs . '#webpage'),
    'creator'             => array('@type' => 'Organization', '@id' => $siteRoot . '/#organization', 'name' => 'FreeExtraChips'),
    'isAccessibleForFree' => true,
    'variableMeasured'    => array('Advertised match (sum of deposit match percentages)', 'Deposit needed to collect every cap', 'Effective match (total bonus / total deposit)', 'Wagering turnover (multiplier x stated base, summed)'),
);
if ($dataAsOf !== '') $dataset['dateModified'] = $dataAsOf;
$dataLd['@graph'][] = $dataset;

$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
$faqJson  = json_encode($faqLd, $jsonFlags);
$dataJson = json_encode($dataLd, $jsonFlags);

$nPriced = count($priced); $nUnpriced = count($unpriced); $nTotal = $nPriced + $nUnpriced;
?>
<div class="fecx rg-page pi-page wp-page" id="wp-top">

<?php if ($crumbHtml !== ''): ?>
<div class="wrap"><?php echo $crumbHtml; ?></div>
<?php endif; ?>

<?php // -- HERO ---------------------------------------------------------- ?>
<section class="au-hero rg-hero pi-hero">
    <div class="wrap">
        <div class="eyebrow">FXRate &middot; Welcome Package Index</div>
        <h1 class="au-hero-title"><?php echo $h($cfg['title']); ?></h1>
        <p class="au-hero-lede"><?php echo $h($cfg['tagline']); ?></p>

        <div class="rg-hero-meta">
            <?php if ($author): ?>
                <div class="rg-hero-byline">
                    <span class="rg-bl-label">Written by</span>
                    <a href="<?php echo $h($authorUrl); ?>" rel="author"><?php echo $h($author['name']); ?></a>
                    <?php if (!empty($author['role'])): ?><span class="rg-bl-role"><?php echo $h($author['role']); ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($reviewer): ?>
                <div class="rg-hero-byline">
                    <span class="rg-bl-label">Reviewed by</span>
                    <a href="<?php echo $h($reviewerUrl); ?>"><?php echo $h($reviewer['name']); ?></a>
                    <?php if (!empty($reviewer['role'])): ?><span class="rg-bl-role"><?php echo $h($reviewer['role']); ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($dataAsOfTxt !== ''): ?>
                <div class="rg-hero-updated">
                    <span class="rg-bl-label">Index data</span>
                    <time datetime="<?php echo $h($dataAsOf); ?>"><?php echo $h($dataAsOfTxt); ?></time>
                </div>
            <?php endif; ?>
            <?php if ($updatedTxt !== ''): ?>
                <div class="rg-hero-updated">
                    <span class="rg-bl-label">Page reviewed</span>
                    <time datetime="<?php echo $h($updated); ?>"><?php echo $h($updatedTxt); ?></time>
                </div>
            <?php endif; ?>
        </div>

        <div class="au-stats-strip pi-stats">
            <?php foreach ($cells as $c): ?>
            <div class="stat">
                <div class="num"><?php echo $c['num']; ?></div>
                <div class="lbl"><?php echo $h($c['lbl']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="wrap au-main rg-main">

    <div class="rg-content">

        <?php // -- Intro (David Romano: numbers first, no first person) ?>
        <section id="intro" class="rg-section">
            <?php if ($live): ?>
            <p class="rg-lede"><?php echo (int)$nTotal; ?> packages. <?php echo (int)$nPriced; ?> priced. Zero estimates.</p>
            <?php endif; ?>

            <p>A welcome package banner is the sum of its deposits&rsquo; match percentages, with the deposits left out. This index puts them back. For every multi-deposit package listed on FreeExtraChips it shows what the casino advertises, what has to be paid in to collect every cap, the <strong>effective match</strong> on that money and the stake needed to clear the wagering &mdash; four numbers computed from the operator&rsquo;s own terms by <a href="<?php echo $h($methodUrl); ?>">FXRate</a>, the same way on every row.</p>

            <?php if ($live && $nPriced > 0): ?>
            <p>Across the <?php echo (int)$nPriced; ?> priced packages the median banner says <strong><?php echo $h($pct($st['median_advertised'])); ?></strong> and the median effective match is <strong><?php echo $h($pct($st['median_effective'])); ?></strong>. <?php echo (int)$st['below_100']; ?> return less than the money put in; <?php echo (int)$st['below_50']; ?> return half or less. In <?php echo (int)$st['first_beats']; ?> of them the first deposit alone has a higher match than the whole package.</p>
            <?php endif; ?>

            <p>This is a list, not a ranking. No package is scored or recommended; the reader chooses the order. A high effective match is a fact about the offer, and the turnover column says what it costs to release.</p>
        </section>

        <?php // -- The index ?>
        <section id="index" class="rg-section pi-section">
            <h2>The index</h2>
            <?php if (!$live): ?>
                <div class="pi-empty">
                    <h3>The index is not available right now</h3>
                    <p>The package data could not be read. The FXRate lines on the bonus and casino pages are computed independently and are unaffected.</p>
                </div>
            <?php elseif ($nPriced === 0): ?>
                <div class="pi-empty">
                    <h3>No priced package yet</h3>
                    <p>A package is priced once every deposit has a stated match and cap. The first one appears here the day its terms are complete.</p>
                </div>
            <?php else: ?>
            <p class="pi-caption">Sorted by effective match, highest first. Change the order, filter by number of deposits or search a casino; the numbers do not change.</p>

            <form class="wp-controls" id="wp-controls" onsubmit="return false;" aria-label="Sort and filter">
                <label class="wp-ctl">
                    <span>Order</span>
                    <select id="wp-sort">
                        <option value="eff-desc" selected>Effective match, highest first</option>
                        <option value="eff-asc">Effective match, lowest first</option>
                        <option value="gap-desc">Widest gap, banner vs effective</option>
                        <option value="adv-desc">Advertised match, highest first</option>
                        <option value="dep-asc">Deposit needed, lowest first</option>
                        <option value="dep-desc">Deposit needed, highest first</option>
                        <option value="to-asc">Turnover, lowest first</option>
                        <option value="first-desc">First deposit match, highest first</option>
                        <option value="brand-asc">Casino, A to Z</option>
                    </select>
                </label>
                <label class="wp-ctl">
                    <span>Deposits</span>
                    <select id="wp-deposits">
                        <option value="0" selected>Any</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5 or more</option>
                    </select>
                </label>
                <label class="wp-ctl wp-ctl--search">
                    <span>Casino</span>
                    <input type="search" id="wp-search" placeholder="Type a name" autocomplete="off">
                </label>
                <?php if ($cc !== ''): ?>
                <label class="wp-ctl wp-ctl--check">
                    <input type="checkbox" id="wp-geo"> <span class="wp-check-label">Hide packages restricted in <?php echo $h($ccName); ?></span>
                </label>
                <?php endif; ?>
                <label class="wp-ctl wp-ctl--check">
                    <input type="checkbox" id="wp-capped"> <span class="wp-check-label">Hide packages with a max cashout</span>
                </label>
                <span class="wp-count" id="wp-count" aria-live="polite">Showing 1&ndash;<?php echo min(25, (int)$nPriced); ?> of <?php echo (int)$nPriced; ?></span>
            </form>

            <div class="pi-table-wrap">
            <table class="pi-table wp-table" id="wp-table">
                <thead>
                    <tr>
                        <th>Casino</th>
                        <th class="num">Deposits</th>
                        <th>Advertised</th>
                        <th class="num">You deposit</th>
                        <th class="num">Effective</th>
                        <th class="num">1st deposit</th>
                        <th class="num">Turnover</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($priced as $r):
                    $sym = $r['symbol'];
                    $firstBeats = ($r['first_pct'] !== null && $r['first_pct'] > $r['effective_pct'] + 0.5);
                ?>
                    <tr data-brand="<?php echo $h(mb_strtolower($r['brand'], 'UTF-8')); ?>" data-geo="<?php echo !empty($r['geo_blocked']) ? '1' : '0'; ?>" data-capped="<?php echo !empty($r['cashout_capped']) ? '1' : '0'; ?>"
                        data-n="<?php echo (int)$r['deposits']; ?>"
                        data-eff="<?php echo $h(round($r['effective_pct'], 4)); ?>"
                        data-adv="<?php echo $h(round($r['advertised_pct'], 4)); ?>"
                        data-gap="<?php echo $h(round($r['ratio'], 4)); ?>"
                        data-dep="<?php echo $r['deposit'] !== null ? $h(round($r['deposit'], 4)) : ''; ?>"
                        data-to="<?php echo $r['turnover'] !== null ? $h(round($r['turnover'], 4)) : ''; ?>"
                        data-first="<?php echo $r['first_pct'] !== null ? $h(round($r['first_pct'], 4)) : ''; ?>">
                        <td class="pi-casino">
                            <div class="wp-cas"><?php echo $chip($r['brand']); ?><div class="wp-cas-txt">
                            <?php if ($r['url'] !== ''): ?>
                                <a class="pi-casino-name" href="<?php echo $h($r['url']); ?>"><?php echo $h($r['brand']); ?></a>
                            <?php else: ?>
                                <span class="pi-casino-name"><?php echo $h($r['brand']); ?></span>
                            <?php endif; ?>
                            <?php if ($r['track_label'] !== ''): ?><span class="pi-sub"><?php echo $h($r['track_label']); ?></span><?php endif; ?>
                            <?php if (!empty($r['alt_titles'])): ?><span class="pi-sub wp-alt" title="The casino runs this package beside the one named: a player takes one or the other">alternative to the <?php echo $h(implode(' and the ', $r['alt_titles'])); ?></span><?php endif; ?>
                            <?php if (!empty($r['flagged'])): ?><span class="pi-sub wp-flag" title="The casino is under review by the desk">&#9888; Under review</span><?php endif; ?>
                            <?php if (!empty($r['geo_blocked'])): ?><span class="pi-sub wp-geo" title="The operator does not accept players from <?php echo $h($ccName); ?>, by its own terms">Restricted in <?php echo $h($ccName); ?></span><?php endif; ?>
                            <?php if ($r['review_url'] !== ''): ?><a class="pi-casino-cta" href="<?php echo $h($r['review_url']); ?>"><span class="pi-cta-long">Casino review</span><span class="pi-cta-short">Review</span> &rarr;</a><?php endif; ?>
                            </div></div>
                        </td>
                        <td class="num"><?php echo (int)$r['deposits']; ?><?php if (!empty($r['spins_legs'])): ?> <span class="wp-spinsleg" title="Deposit <?php echo $h(implode(', ', $r['spins_legs'])); ?>: free spins only &mdash; counted in the banner, left out of the four numbers">&#8902;</span><?php endif; ?><?php if (!empty($r['cashout_capped'])): ?> <span class="wp-capped" title="Deposit <?php echo $h(implode(', ', $r['cashout_capped'])); ?>: withdrawals capped by the operator &mdash; reported on the bonus page, not priced into these numbers">&#9651;</span><?php endif; ?></td>
                        <td class="wp-adv"><?php echo $h($r['advertised_text']); ?></td>
                        <td class="num"><span class="pi-mono"><?php echo $h($mon($r['deposit'], $sym, true)); ?></span><?php if (!empty($r['deposit_capped'])): ?> <span class="wp-depcap" title="Deposit <?php echo $h(implode(', ', $r['deposit_capped'])); ?>: the cap needs more than the operator&rsquo;s maximum deposit, so the leg is priced at that maximum and the banner cap is out of reach. Divide the banner by this deposit and you will not get the effective match.">&le;</span><?php endif; ?></td>
                        <td class="num"><span class="pi-delta <?php echo $effClass($r['effective_pct']); ?>" title="Total bonus at the caps divided by total deposit"><?php echo $h($pct($r['effective_pct'])); ?></span></td>
                        <td class="num">
                            <span class="pi-mono<?php echo $firstBeats ? ' wp-first--beats' : ''; ?>"<?php echo $firstBeats ? ' title="Higher than the package: the later deposits dilute it"' : ''; ?>><?php echo $h($pct($r['first_pct'])); ?><?php echo $firstBeats ? ' <span class="wp-mark" aria-hidden="true">&uarr;</span>' : ''; ?></span>
                        </td>
                        <td class="num"><span class="pi-mono<?php echo $r['turnover'] === null ? ' wp-na' : ''; ?>"><?php echo $h($mon($r['turnover'], $sym)); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <nav class="fec-pg wp-pager" id="wp-pager" aria-label="Index pages" hidden></nav>
            <p class="pi-scroll-hint">Swipe sideways for the full table.</p>
            <p class="wp-none" id="wp-none" hidden>No package matches. Clear the filters to see all <?php echo (int)$nPriced; ?>.</p>
            <p class="pi-note"><strong>Advertised</strong> is the sum of the match percentages and caps across the deposits, free spins counted and never priced. <strong>You deposit</strong> is what collects every cap in full. <strong>Effective</strong> is total bonus divided by total deposit. <strong>1st deposit alone</strong> is the match of the first leg on its own; the arrow marks packages where it beats the package average. <strong>Turnover</strong> is the wagering multiplier times its stated base, summed. Nothing is estimated; where a wagering base is missing the turnover reads not stated.<?php if ($cc !== ''): ?> <strong>Restricted in <?php echo $h($ccName); ?></strong> marks operators whose own terms exclude players from your country, read from the same lists the bonus pages use; the package is listed all the same, because the numbers do not depend on where you are.<?php endif; ?></p>
            <?php endif; ?>
        </section>

        <?php // -- Awaiting terms ?>
        <?php if ($live && $nUnpriced > 0): ?>
        <section id="unpriced" class="rg-section pi-section">
            <h2>Awaiting terms</h2>
            <p class="pi-caption">Packages the casino describes without publishing the terms of every deposit. Listed, not priced; nothing is estimated in place of a missing cap or match.</p>
            <div class="pi-table-wrap">
            <table class="pi-table wp-table wp-table--unpriced" id="wp-table-unpriced">
                <thead>
                    <tr>
                        <th>Casino</th>
                        <th class="num">Deposits</th>
                        <th class="num" title="Legs with both a match rate and a cap stated (a spins leg counts as stated)">Terms stated</th>
                        <th>Known so far</th>
                        <th class="num">Effective</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($unpriced as $r): ?>
                    <tr>
                        <td class="pi-casino">
                            <div class="wp-cas"><?php echo $chip($r['brand']); ?><div class="wp-cas-txt">
                            <?php if ($r['url'] !== ''): ?>
                                <a class="pi-casino-name" href="<?php echo $h($r['url']); ?>"><?php echo $h($r['brand']); ?></a>
                            <?php else: ?>
                                <span class="pi-casino-name"><?php echo $h($r['brand']); ?></span>
                            <?php endif; ?>
                            <?php if ($r['track_label'] !== ''): ?><span class="pi-sub"><?php echo $h($r['track_label']); ?></span><?php endif; ?>
                            <?php if (!empty($r['alt_titles'])): ?><span class="pi-sub wp-alt" title="The casino runs this package beside the one named: a player takes one or the other">alternative to the <?php echo $h(implode(' and the ', $r['alt_titles'])); ?></span><?php endif; ?>
                            <?php if (!empty($r['flagged'])): ?><span class="pi-sub wp-flag" title="The casino is under review by the desk">&#9888; Under review</span><?php endif; ?>
                            <?php if (!empty($r['geo_blocked'])): ?><span class="pi-sub wp-geo" title="The operator does not accept players from <?php echo $h($ccName); ?>, by its own terms">Restricted in <?php echo $h($ccName); ?></span><?php endif; ?>
                            </div></div>
                        </td>
                        <td class="num"><?php echo (int)$r['deposits']; ?><?php if (!empty($r['spins_legs'])): ?> <span class="wp-spinsleg" title="Deposit <?php echo $h(implode(', ', $r['spins_legs'])); ?>: free spins only &mdash; counted in the banner, left out of the four numbers">&#8902;</span><?php endif; ?><?php if (!empty($r['cashout_capped'])): ?> <span class="wp-capped" title="Deposit <?php echo $h(implode(', ', $r['cashout_capped'])); ?>: withdrawals capped by the operator &mdash; reported on the bonus page, not priced into these numbers">&#9651;</span><?php endif; ?></td>
                        <td class="num"><?php echo (int)$r['legs_stated']; ?> of <?php echo (int)$r['deposits']; ?></td>
                        <td class="wp-adv"><?php echo ($r['first_pct'] !== null) ? '1st deposit ' . $h($pct($r['first_pct'])) . ($r['first_cap'] !== null && $r['symbol'] !== '' ? ' up to ' . $h($mon($r['first_cap'], $r['symbol'])) : '') : 'not stated'; ?></td>
                        <td class="num"><span class="pi-mono wp-na">not stated</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <nav class="fec-pg wp-pager" id="wp-pager-unpriced" aria-label="Awaiting terms pages" hidden></nav>
            <p class="pi-scroll-hint">Swipe sideways for the full table.</p>
        </section>
        <?php endif; ?>

        <?php // -- Method ?>
        <section id="method" class="rg-section">
            <h2>How to read the index</h2>
            <ol class="rg-steps">
                <li><strong>Read Effective, not Advertised.</strong> The banner is the sum of the legs; the effective match is what the deposited money returns. A sum is always above an average, so every banner is above its rate; the gap is the finding.</li>
                <li><strong>Compare the first deposit with the package.</strong> Where the first leg alone has the higher match, the later deposits cost more than they pay. The arrow marks those rows.</li>
                <li><strong>Read You deposit and Turnover together.</strong> A rate that needs tens of thousands to collect and millions to release is a rate for a different player.</li>
                <li><strong>Deposit limits and missing caps.</strong> A leg is priced at its minimum deposit when the minimum already exceeds the cap, and at its maximum when the cap cannot be reached in one deposit (marked &le; in the You deposit column: on those rows the banner cap is out of reach, so banner &divide; deposit is not the effective match). A leg without a stated cap keeps the package out of this table until the cap is entered.</li>
                <li><strong>Two packages at one casino.</strong> A casino may run a standard track beside a high-roller or crypto track. Each is its own row with its own four numbers; where the desk has linked them, the row says &ldquo;alternative to&rdquo; the other, because a player takes one or the other, not both.</li>
                <li><strong>Maximum cashout.</strong> Some operators cap what can be withdrawn from a leg (&ldquo;max cashout 5&times; the deposit&rdquo;). The cap is a term, not a rate: it is reported beside the leg on the bonus page, marked &#9651; in the Deposits column here, and never priced into the four numbers. A high effective match with a cashout cap is a different offer from the same match without one; the checkbox hides the capped packages.</li>
                <li><strong>Free spins and currencies.</strong> Spins are counted in the banner and never given a cash value. A deposit that buys only free spins (&ldquo;3rd deposit: 100 free spins&rdquo;) is a spins leg: it counts as a deposit of the ladder, marked &#8902; in the Deposits column, and stays out of the four numbers, which price the match legs alone. The wagering on the spins&rsquo; winnings is a term of its own, apart from the match&rsquo;s, and the bonus page prints it beside the leg (&ldquo;spins: no wagering&rdquo;, &ldquo;spins: 40&times; winnings&rdquo;); it is never read from the match&rsquo;s term and never priced. Packages whose deposits are stated in different currencies are not summed.</li>
                <li><strong>Exclusions and updates.</strong> Packages whose first-deposit bonus has expired, and casinos the desk has marked closed, suspended or blacklisted, do not appear; a casino under review stays listed and is marked as such. Every line is recomputed from the listing on each page view. The rules are versioned on the <a href="<?php echo $h($methodUrl); ?>">FXRate methodology page</a>.</li>
            </ol>
        </section>

        <?php // -- FAQ ?>
        <section id="faq" class="rg-section pi-faq">
            <h2>Questions</h2>
            <?php foreach ($faq as $f): ?>
            <div class="pi-faq-item">
                <h3><?php echo $h($f['q']); ?></h3>
                <p><?php echo $h($f['a']); ?></p>
            </div>
            <?php endforeach; ?>
        </section>

    </div>

    <?php // -- ASIDE ------------------------------------------------------ ?>
    <aside class="au-side rg-side" aria-label="On this page">
        <div class="au-side-card rg-toc">
            <div class="eyebrow">On this page</div>
            <ul>
                <li><a href="#index">The index</a></li>
                <?php if ($live && $nUnpriced > 0): ?><li><a href="#unpriced">Awaiting terms</a></li><?php endif; ?>
                <li><a href="#method">How to read the index</a></li>
                <li><a href="#faq">Questions</a></li>
            </ul>
        </div>

        <div class="au-side-card pi-about">
            <div class="eyebrow">About the index</div>
            <p>Four numbers per package, computed from the operator&rsquo;s own terms as listed on FreeExtraChips: advertised, you deposit, effective, turnover. No return-to-player, no simulation, no score. Where a term is missing the line says not stated and the package waits in its own table.</p>
            <p>Every casino listed links to its first-deposit bonus, where the full ladder is priced leg by leg.</p>
        </div>

        <?php if ($reviewer): ?>
            <div class="au-side-card rg-reviewer-card">
                <div class="eyebrow">Reviewed by</div>
                <a href="<?php echo $h($reviewerUrl); ?>" class="rg-rc-link">
                    <?php if (!empty($reviewer['avatar_url'])): ?><img src="<?php echo $h($reviewer['avatar_url']); ?>" alt="<?php echo $h($reviewer['name']); ?>" class="rg-rc-avatar" width="48" height="48"><?php endif; ?>
                    <div class="rg-rc-body">
                        <div class="rg-rc-name"><?php echo $h($reviewer['name']); ?></div>
                        <?php if (!empty($reviewer['role'])): ?><div class="rg-rc-role"><?php echo $h($reviewer['role']); ?></div><?php endif; ?>
                    </div>
                </a>
                <?php if (!empty($reviewer['short_bio'])): ?><p class="rg-rc-bio"><?php echo $h($reviewer['short_bio']); ?></p><?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="au-side-card rg-disclosure">
            <div class="eyebrow">Related</div>
            <p style="margin-top: 8px;"><a href="<?php echo $h($methodUrl); ?>">How FXRate works</a> &mdash; the four numbers and the rules for missing terms.</p>
            <p style="margin-top: 8px;"><a href="<?php echo $h($fxcheckUrl); ?>">How FXCheck works</a> &mdash; whether a bonus actually paid, from player reports.</p>
            <p style="margin-top: 8px;"><a href="<?php echo $h($bonusesUrl); ?>">All bonuses</a></p>
            <p style="margin-top: 8px;"><a href="<?php echo $h($casinosUrl); ?>">All casino reviews</a></p>
            <p style="margin-top: 8px;"><a href="<?php echo $h($discloseUrl); ?>">Affiliate disclosure</a> &mdash; the firewall between partnerships and this page.</p>
        </div>
    </aside>

</div>

<?php // -- Footer byline ----------------------------------------------- ?>
<div class="wrap rg-footer-byline">
    <div class="hub-byline">
        <?php if ($author): ?>
            <div class="person">
                <span class="who">Written by</span>
                <span class="name"><a href="<?php echo $h($authorUrl); ?>" rel="author"><?php echo $h($author['name']); ?></a></span>
                <?php if (!empty($author['role'])): ?><span class="role"><?php echo $h($author['role']); ?></span><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($reviewer): ?>
            <div class="person">
                <span class="who">Reviewed by</span>
                <span class="name"><a href="<?php echo $h($reviewerUrl); ?>"><?php echo $h($reviewer['name']); ?></a></span>
                <?php if (!empty($reviewer['role'])): ?><span class="role"><?php echo $h($reviewer['role']); ?></span><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($dataAsOfTxt !== ''): ?>
            <div class="updated">Index data: <time datetime="<?php echo $h($dataAsOf); ?>"><?php echo $h($dataAsOfTxt); ?></time></div>
        <?php endif; ?>
    </div>
</div>

<?php // -- JSON-LD: page graph (view) + ItemList/Dataset + FAQPage + breadcrumb ?>
<?php if ($this->jsonLdGraph !== ''): ?>
<script type="application/ld+json"><?php echo $this->jsonLdGraph; ?></script>
<?php endif; ?>
<?php if ($dataJson !== false && $dataJson !== ''): ?>
<script type="application/ld+json"><?php echo $dataJson; ?></script>
<?php endif; ?>
<?php if ($faqJson !== false && $faqJson !== ''): ?>
<script type="application/ld+json"><?php echo $faqJson; ?></script>
<?php endif; ?>
<?php if ($crumbJsonLd !== ''): ?>
<?php echo $crumbJsonLd; ?>
<?php endif; ?>

<?php if ($live && $nPriced > 0): ?>
<script>
/* Welcome Package Index: client-side order, filters and pages over the
   server-rendered rows. Numbers live in data-* attributes; the DOM is only
   reordered and shown/hidden, never recomputed. Without this script every
   row stays visible, in the server order. */
(function () {
  var PAGE = 25;

  /* pager: pages a list of rows, draws the .fec-pg nav, updates a count line */
  function makePager(rows, pagerEl, countEl, scrollTo) {
    var state = { page: 1, all: false, visible: rows.slice() };
    function draw() {
      var total = state.visible.length;
      var pages = state.all ? 1 : Math.max(1, Math.ceil(total / PAGE));
      if (state.page > pages) state.page = pages;
      var from = state.all ? 0 : (state.page - 1) * PAGE;
      var to = state.all ? total : Math.min(total, from + PAGE);
      for (var i = 0; i < rows.length; i++) rows[i].style.display = 'none';
      for (var j = from; j < to; j++) state.visible[j].style.display = '';
      if (countEl) countEl.textContent = total === 0 ? 'Showing 0 of 0' : ('Showing ' + (from + 1) + '–' + to + ' of ' + total);
      if (!pagerEl) return;
      if (total <= PAGE) { pagerEl.hidden = true; pagerEl.innerHTML = ''; return; }
      var items = [];
      function li(label, page, cls, aria) {
        items.push('<li class="' + (cls || '') + '"><a href="#index" data-page="' + page + '"' + (aria ? ' aria-label="' + aria + '"' : '') + '>' + label + '</a></li>');
      }
      if (state.all) {
        li('Show 25 per page', 'paged', '');
      } else {
        li('‹', state.page - 1, state.page === 1 ? 'disabled' : '', 'Previous page');
        var last = 0;
        for (var p = 1; p <= pages; p++) {
          var near = Math.abs(p - state.page) <= 1 || p === 1 || p === pages;
          if (!near) { if (last !== -1) { items.push('<li><span>…</span></li>'); last = -1; } continue; }
          li(String(p), p, p === state.page ? 'active' : '');
          last = p;
        }
        li('›', state.page + 1, state.page === pages ? 'disabled' : '', 'Next page');
        li('Show all ' + total, 'all', '');
      }
      pagerEl.innerHTML = '<ul class="pagination">' + items.join('') + '</ul>';
      pagerEl.hidden = false;
    }
    if (pagerEl) {
      pagerEl.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[data-page]') : null;
        if (!a) return;
        e.preventDefault();
        var v = a.getAttribute('data-page');
        if (a.parentNode.className.indexOf('disabled') !== -1) return;
        if (v === 'all') { state.all = true; }
        else if (v === 'paged') { state.all = false; state.page = 1; }
        else { state.page = parseInt(v, 10) || 1; }
        draw();
        if (scrollTo && scrollTo.getBoundingClientRect().top < 0) scrollTo.scrollIntoView({ block: 'start' });
      });
    }
    return {
      set: function (visible) { state.visible = visible; state.page = 1; draw(); },
      draw: draw
    };
  }

  /* main table: sort + filter, then page */
  var tbody = document.querySelector('#wp-table tbody');
  var sortEl = document.getElementById('wp-sort');
  var depEl = document.getElementById('wp-deposits');
  var qEl = document.getElementById('wp-search');
  var geoEl = document.getElementById('wp-geo');
  var capEl = document.getElementById('wp-capped');
  var countEl = document.getElementById('wp-count');
  var noneEl = document.getElementById('wp-none');
  var controls = document.getElementById('wp-controls');
  if (tbody && sortEl && depEl && qEl) {
    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
    var pager = makePager(rows, document.getElementById('wp-pager'), countEl, controls);
    function num(tr, k) { var v = tr.getAttribute('data-' + k); return (v === null || v === '') ? null : parseFloat(v); }
    function cmp(key, dir) {
      return function (a, b) {
        if (key === 'brand') { return dir * a.getAttribute('data-brand').localeCompare(b.getAttribute('data-brand')); }
        var x = num(a, key), y = num(b, key);
        if (x === null && y === null) return a.getAttribute('data-brand').localeCompare(b.getAttribute('data-brand'));
        if (x === null) return 1;   /* not stated always last */
        if (y === null) return -1;
        if (x === y) return a.getAttribute('data-brand').localeCompare(b.getAttribute('data-brand'));
        return dir * (x - y);
      };
    }
    var orders = {
      'eff-desc': cmp('eff', -1), 'eff-asc': cmp('eff', 1), 'gap-desc': cmp('gap', -1), 'adv-desc': cmp('adv', -1),
      'dep-asc': cmp('dep', 1), 'dep-desc': cmp('dep', -1), 'to-asc': cmp('to', 1), 'first-desc': cmp('first', -1), 'brand-asc': cmp('brand', 1)
    };
    function apply() {
      var order = orders[sortEl.value] || orders['eff-desc'];
      var dep = parseInt(depEl.value, 10) || 0;
      var q = (qEl.value || '').toLowerCase().replace(/^\s+|\s+$/g, '');
      rows.sort(order);
      var visible = [];
      for (var i = 0; i < rows.length; i++) {
        var tr = rows[i];
        var n = parseInt(tr.getAttribute('data-n'), 10) || 0;
        var ok = (dep === 0) || (dep === 5 ? n >= 5 : n === dep);
        if (ok && geoEl && geoEl.checked && tr.getAttribute('data-geo') === '1') ok = false;
        if (ok && capEl && capEl.checked && tr.getAttribute('data-capped') === '1') ok = false;
        if (ok && q !== '' && tr.getAttribute('data-brand').indexOf(q) === -1) ok = false;
        if (ok) visible.push(tr);
        tbody.appendChild(tr);
      }
      pager.set(visible);
      if (noneEl) noneEl.hidden = (visible.length > 0);
    }
    sortEl.addEventListener('change', apply);
    depEl.addEventListener('change', apply);
    qEl.addEventListener('input', apply);
    if (geoEl) geoEl.addEventListener('change', apply);
    if (capEl) capEl.addEventListener('change', apply);
    apply();
  }

  /* awaiting-terms table: pages only */
  var ub = document.querySelector('#wp-table-unpriced tbody');
  if (ub) {
    var urows = Array.prototype.slice.call(ub.querySelectorAll('tr'));
    var upager = makePager(urows, document.getElementById('wp-pager-unpriced'), null, document.getElementById('unpriced'));
    upager.set(urows);
  }
})();
</script>
<?php endif; ?>

</div>
