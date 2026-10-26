<?php
defined('_JEXEC') or die;

/**
 * FEC Payout Index — /online-casinos/fast-payouts
 * (Joomla layout suffix: default_fast-payouts.php; kind='page' topic 'fast-payouts')
 *
 * The first cross-casino view of the two withdrawal-timing systems already
 * running on the site: FXShield desk tests (#__fec_payout_tests) and FXClock
 * player clocks (#__fec_clock_*). Data comes from models/payoutindex.php; this
 * file only lays it out.
 *
 * Editorial rules baked in:
 *   - Two lanes, two tables, never one number. Desk test = controlled sample
 *     of one; player median = crowd timing from ten clocks. Labelled as such.
 *   - Facts only on the page: elapsed time, declared window, gap, dates,
 *     counts, Staff Verified badge. FXShield scoring stays in editor notes.
 *   - Below-threshold medians are never shown; the empty state shows the
 *     clocks running in public instead (same honesty contract as the widget).
 *   - Country-neutral copy. No competitor names.
 *   - Byline voice: Mark Sullivan (payments) — clipped, timing-led, no first
 *     person. Reviewed by David Romano (numbers).
 *
 * Layout reuses the .au-* / .rg-* primitives of the other trust pages plus the
 * .pi-* additions in assets/page.css (section 14).
 */

require_once JPATH_COMPONENT . '/models/payoutindex.php';

$cfg      = is_array($this->config) ? $this->config : array();
$author   = $this->author;
$reviewer = $this->reviewer;

$h   = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$fmM = function ($m, $mode = 'auto') { return AuthorsModelPayoutindex::fmtMinutes($m, $mode); };
$fmH = function ($x, $mode = 'auto') { return AuthorsModelPayoutindex::fmtHours($x, $mode); };
$fmD = function ($x) { return AuthorsModelPayoutindex::fmtDelta($x); };
$fmT = function ($d) { return AuthorsModelPayoutindex::fmtDate($d); };

// ---- Live register or a frozen edition (2.7.55) ---------------------------
$edition  = (isset($this->edition) && is_array($this->edition)) ? $this->edition : null;
$editions = (isset($this->editions) && is_array($this->editions)) ? $this->editions : array();
if ($edition) {
    $data = $edition['payload'];
    foreach (array('running', 'latest') as $k) if (!isset($data['feed'][$k])) $data['feed'][$k] = array();
    foreach (array('desk', 'players', 'collecting') as $k) if (!isset($data[$k])) $data[$k] = array();
} else {
    $data = AuthorsModelPayoutindex::data();
}
$desk      = $data['desk'];
$players   = $data['players'];
$collect   = $data['collecting'];
$feed      = $data['feed'];
$threshold = (int)$data['threshold'];
$deskTests = (int)$data['desk_tests'];
$deskCas   = (int)$data['desk_casinos'];
$dataAsOf  = (string)$data['data_as_of'];
$diff      = ($edition && isset($edition['diff']) && is_array($edition['diff'])) ? $edition['diff'] : null;
$frozenTxt = $edition ? date('j F Y', strtotime($edition['published_at'])) : '';
$edLabel   = $edition ? AuthorsModelPayoutindex::editionNumberLabel($edition['number']) . ' · ' . AuthorsModelPayoutindex::editionMonthLabel($edition['edition']) : '';
$indexUrl  = JUri::base(true) . AuthorsModelPayoutindex::INDEX_PATH;

// ---- Dates ----------------------------------------------------------------
$updated    = isset($cfg['last_updated']) ? (string)$cfg['last_updated'] : '';
$updatedTxt = '';
if ($updated !== '') {
    try { $dt = new DateTime($updated); $updatedTxt = $dt->format('j F Y'); } catch (Exception $e) { $updatedTxt = $updated; }
}
$dataAsOfTxt = AuthorsModelPayoutindex::fmtDate($dataAsOf, true);

// ---- FXShield tier per desk-tested casino (2.7.89) --------------------------
// Live register only: a frozen edition is a snapshot and gets no live badge.
$fxBadges = array();
if (!$edition && !empty($desk)) {
    $fxIds = array();
    foreach ($desk as $r) if ((int)$r['casino_id'] > 0) $fxIds[] = (int)$r['casino_id'];
    $fxBadges = AuthorsModelPayoutindex::safetyBadges($fxIds);
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

$pageRel     = $edition ? AuthorsModelPayoutindex::editionPath($edition['edition'])
                        : JRoute::_('index.php?option=com_authors&view=page&slug=fast-payouts');
$pageAbs     = $abs($pageRel);
$fxshieldUrl = JRoute::_('index.php?option=com_authors&view=methodology&topic=fxshield');
$fxclockUrl  = JRoute::_('index.php?option=com_authors&view=methodology&topic=fxclock');
$discloseUrl = JRoute::_('index.php?option=com_authors&view=page&slug=affiliate-disclosure');
$casinosUrl  = JUri::base(true) . '/online-casinos';
$atlasUrl    = JUri::base(true) . '/online-casinos/operators';

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

// ---- Stats strip cells (facts only; a weak count never poses as proof) ----
$cells = array();
$cells[] = array('num' => $deskTests, 'lbl' => 'Desk-tested withdrawals');
$cells[] = array('num' => $deskCas,   'lbl' => 'Casinos with a measured time');
if ((int)$feed['stopped_total'] > 0) {
    $cells[] = array('num' => (int)$feed['stopped_total'], 'lbl' => 'Player clocks stopped');
} else {
    $cells[] = array('num' => (int)$feed['brands_enabled'], 'lbl' => 'Casinos open to FXClock');
}
if ((int)$feed['brands_with_median'] > 0) {
    $cells[] = array('num' => (int)$feed['brands_with_median'], 'lbl' => 'Brands with a player median');
} elseif ((int)$feed['running_count'] > 0) {
    $cells[] = array('num' => (int)$feed['running_count'], 'lbl' => 'Clocks running now');
} else {
    $cells[] = array('num' => 0, 'lbl' => 'Desk edits to any clock');
}

// ---- Grade labels (FXClock spec v1.0) --------------------------------------
$gradeLabel = array('A' => 'on-chain verified', 'B' => 'deposit-matched', 'C' => 'contact verified', 'D' => 'anonymous');

// ---- FAQ (visible list + FAQPage JSON-LD built from the same array) --------
$faq = array(
    array(
        'q' => 'How are the withdrawal times on this page measured?',
        'a' => 'Two ways, kept apart. Desk tests: FreeExtraChips staff deposit, play, request a withdrawal and log the request and receipt times in UTC; the evidence is filed with the brand\'s FXShield verification. Player clocks: FXClock timings started and stopped by players on the casino review pages, published as a median once ' . $threshold . ' live clocks have stopped for a brand.',
    ),
    array(
        'q' => 'What does "declared" mean in the table?',
        'a' => 'The slowest end of the processing time the operator stated at the time of the test, taken from its own terms or cashier. A window of "24 to 72 hours" is recorded as 72h, so the gap column compares the real time with the operator\'s own worst case.',
    ),
    array(
        'q' => 'Why is the list so short?',
        'a' => 'Because every row is a real withdrawal, not a quoted processing time. A casino enters the desk table only after a completed staff test; a brand enters the player ranking once ' . $threshold . ' live clocks have stopped, and until then the collection table shows how many have. The register grows at the pace of completed withdrawals.',
    ),
    array(
        'q' => 'Does a fast payout mean the casino is safe?',
        'a' => 'No. Speed is one fact about one withdrawal. The FXShield tier beside each casino is the safety assessment - licence, terms, dispute handling and recorded events - and the verification report linked on the row has the detail where one exists. A fast payer can sit in the Caution or High risk tier; the row shows both.',
    ),
    array(
        'q' => 'Can an operator pay to be listed or to move up?',
        'a' => 'No. Rank follows measured time only, commercial relationships have no input, and brands the desk has marked closed, suspended or blacklisted are excluded regardless of speed.',
    ),
);
$faqEntities = array();
foreach ($faq as $f) {
    $faqEntities[] = array(
        '@type'          => 'Question',
        'name'           => $f['q'],
        'acceptedAnswer' => array('@type' => 'Answer', 'text' => $f['a']),
    );
}
$faqLd = array('@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqEntities);

// ---- ItemList + Dataset JSON-LD (desk lane only: one ranked list, one fact) -
$listItems = array();
$pos = 0;
$earliest = '';
foreach ($desk as $r) {
    $pos++;
    $listItems[] = array(
        '@type'    => 'ListItem',
        'position' => $pos,
        'name'     => $r['title'],
        'url'      => $abs($r['url']),
    );
    if ($r['requested_date'] !== '' && ($earliest === '' || strcmp($r['requested_date'], $earliest) < 0)) $earliest = $r['requested_date'];
}
$dataLd = array('@context' => 'https://schema.org', '@graph' => array());
if (!empty($listItems)) {
    $dataLd['@graph'][] = array(
        '@type'           => 'ItemList',
        '@id'             => $pageAbs . '#desk-tested',
        'name'            => 'FEC Payout Index — desk-tested withdrawals, fastest first',
        'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
        'numberOfItems'   => count($listItems),
        'itemListElement' => $listItems,
    );
}
$dataset = array(
    '@type'               => 'Dataset',
    '@id'                 => $pageAbs . '#dataset',
    'name'                => 'FEC Payout Index',
    'description'         => 'Measured online casino withdrawal times: desk tests by FreeExtraChips staff (request to receipt, UTC, against the operator\'s declared window) and player-timed FXClock medians. Facts only; one row per completed withdrawal test.',
    'url'                 => $pageAbs,
    'mainEntityOfPage'    => array('@id' => $pageAbs . '#webpage'),
    'creator'             => array('@type' => 'Organization', '@id' => $siteRoot . '/#organization', 'name' => 'FreeExtraChips'),
    'isAccessibleForFree' => true,
    'variableMeasured'    => array('Withdrawal elapsed time, request to receipt (hours)', 'Operator declared processing window at test time (hours)', 'Player-timed withdrawal median (minutes)'),
);
if ($dataAsOf !== '') $dataset['dateModified'] = $dataAsOf;
if ($earliest !== '' && $dataAsOf !== '') $dataset['temporalCoverage'] = $earliest . '/' . $dataAsOf;
if (!$edition) $dataLd['@graph'][] = $dataset;
if ($edition) $faqLd = null; // FAQ is the live page's; editions carry the frozen list only

$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
$faqJson  = ($faqLd !== null) ? json_encode($faqLd, $jsonFlags) : '';
$dataJson = json_encode($dataLd, $jsonFlags);
?>
<div class="fecx rg-page pi-page" id="pi-top">

<?php if ($crumbHtml !== ''): ?>
<div class="wrap"><?php echo $crumbHtml; ?></div>
<?php endif; ?>

<?php // -- HERO ---------------------------------------------------------- ?>
<section class="au-hero rg-hero pi-hero">
    <div class="wrap">
        <div class="eyebrow"><?php echo $edition ? 'FEC Payout Index &middot; Monthly edition' : 'FEC Payout Index &middot; Live register'; ?></div>
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
            <?php if ($edition): ?>
                <div class="rg-hero-updated">
                    <span class="rg-bl-label">Frozen on</span>
                    <time datetime="<?php echo $h(substr($edition['published_at'], 0, 10)); ?>"><?php echo $h($frozenTxt); ?></time>
                </div>
            <?php elseif ($dataAsOfTxt !== ''): ?>
                <div class="rg-hero-updated">
                    <span class="rg-bl-label">Register data</span>
                    <time datetime="<?php echo $h($dataAsOf); ?>"><?php echo $h($dataAsOfTxt); ?></time>
                </div>
            <?php endif; ?>
            <?php if ($updatedTxt !== '' && !$edition): ?>
                <div class="rg-hero-updated">
                    <span class="rg-bl-label">Page reviewed</span>
                    <time datetime="<?php echo $h($updated); ?>"><?php echo $h($updatedTxt); ?></time>
                </div>
            <?php endif; ?>
        </div>

        <div class="au-stats-strip pi-stats">
            <?php foreach ($cells as $c): ?>
            <div class="stat">
                <div class="num"><?php echo (int)$c['num']; ?></div>
                <div class="lbl"><?php echo $h($c['lbl']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="wrap au-main rg-main">

    <div class="rg-content">

        <?php // -- Intro (Mark Sullivan: numbers first, no first person) ?>
        <section id="intro" class="rg-section">
            <p class="rg-lede"><?php echo (int)$deskTests; ?> withdrawal<?php echo $deskTests === 1 ? '' : 's'; ?>. <?php echo (int)$deskCas; ?> casino<?php echo $deskCas === 1 ? '' : 's'; ?>. Zero estimates.</p>

            <?php if ($edition): ?>
            <div class="pi-edition-note">
                <p>This is <?php echo $h($edLabel); ?> of the register, frozen on <?php echo $h($frozenTxt); ?>. Numbers below are as they stood that day; the <a href="<?php echo $h($indexUrl); ?>">live register</a> keeps moving.</p>
            </div>
            <section id="changes" class="pi-changes">
                <h2>What changed since the previous edition</h2>
                <?php if ($diff === null): ?>
                    <p>Nothing to compare yet: this is the first edition. Every later edition will list new entries, retests, position changes and departures here.</p>
                <?php else: ?>
                    <?php
                        $items = array();
                        foreach ((array)$diff['new_entries'] as $x) $items[] = '<strong>New:</strong> ' . $h($x['title']) . ', ' . $h($fmH($x['measured_hours'])) . ' measured against a declared ' . $h($fmH($x['declared_hours'])) . ', enters at #' . (int)$x['rank'] . '.';
                        foreach ((array)$diff['retested'] as $x)    $items[] = '<strong>Retested:</strong> ' . $h($x['title']) . ', ' . $h($fmH($x['from_hours'])) . ' &rarr; ' . $h($fmH($x['to_hours'])) . '.';
                        foreach ((array)$diff['moved'] as $x)       $items[] = '<strong>Moved:</strong> ' . $h($x['title']) . ', #' . (int)$x['from'] . ' &rarr; #' . (int)$x['to'] . '.';
                        foreach ((array)$diff['left'] as $x)        $items[] = '<strong>Removed:</strong> ' . $h($x['title']) . ' (was #' . (int)$x['rank'] . ').';
                        foreach ((array)$diff['players_new'] as $x) $items[] = '<strong>Player median published:</strong> ' . $h($x['title']) . ', ' . $h($fmM($x['median_min'])) . ' from ' . (int)$x['stopped_count'] . ' clocks.';
                        $dt = (int)$diff['desk_tests_delta']; $ds = (int)$diff['stopped_delta'];
                        $tot = array();
                        if ($dt !== 0) $tot[] = ($dt > 0 ? '+' : '') . $dt . ' desk test' . (abs($dt) === 1 ? '' : 's');
                        if ($ds !== 0) $tot[] = ($ds > 0 ? '+' : '') . $ds . ' player clock' . (abs($ds) === 1 ? '' : 's') . ' stopped';
                    ?>
                    <?php if (empty($items) && empty($tot)): ?>
                        <p>No change in the tables since the previous edition.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($items as $li): ?><li><?php echo $li; ?></li><?php endforeach; ?>
                            <?php if (!empty($tot)): ?><li><strong>Totals:</strong> <?php echo $h(implode(', ', $tot)); ?>.</li><?php endif; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <p>Every row in the first table is a cash-out requested by FreeExtraChips staff on a funded account. The request time and the arrival time are logged in UTC, the evidence is filed with the brand&rsquo;s <a href="<?php echo $h($fxshieldUrl); ?>">FXShield verification</a>, and the elapsed time is the fact. The operator&rsquo;s own stated processing window sits beside it, so the gap needs no interpretation.</p>

            <p>The second table belongs to players. <a href="<?php echo $h($fxclockUrl); ?>">FXClock</a> lets anyone start a clock when a withdrawal is requested and stop it when the money lands. A brand&rsquo;s median appears only once <?php echo (int)$threshold; ?> clocks have stopped, and nobody at the desk can start, stop or edit one. The two measurements never merge: a desk test is a controlled sample of one, a player median is a crowd of self-reported timings, and each is labelled as what it is.</p>

            <p>Most rankings of fast-paying casinos sort what operators promise. This one sorts what happened, and it grows one completed withdrawal at a time.</p>
        </section>

        <?php // -- Lane 1: desk-tested withdrawals ?>
        <section id="desk-tested" class="rg-section pi-section">
            <h2>Desk-tested withdrawals</h2>
            <p class="pi-caption">Ranked by the measured time of each casino&rsquo;s most recent desk test. Fastest first.</p>

            <?php if (empty($desk)): ?>
                <div class="pi-empty">
                    <h3>No completed desk tests yet</h3>
                    <p>The first measured withdrawal will appear here the day it is received and committed.</p>
                </div>
            <?php else: ?>
            <div class="pi-table-wrap">
            <table class="pi-table">
                <thead>
                    <tr>
                        <th class="pi-rank">#</th>
                        <th>Casino</th>
                        <th class="num">Measured</th>
                        <th class="num">Declared</th>
                        <th class="num">vs declared</th>
                        <th class="pi-col-date">Requested</th>
                        <th>Verification</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 0; foreach ($desk as $r): $i++;
                    $mode  = AuthorsModelPayoutindex::rowMode(array($r['measured_hours'], $r['declared_hours'], $r['median_hours']));
                    $delta = (float)$r['delta_hours'];
                    $deltaMin = (int)round($delta * 60);
                    $deltaCls = ($deltaMin === 0) ? 'pi-delta--ontime' : (($deltaMin > 0) ? 'pi-delta--slower' : 'pi-delta--faster');
                    $deltaTitle = ($deltaMin === 0) ? 'Exactly the declared time' : (($deltaMin > 0) ? 'Slower than declared' : 'Faster than declared');
                    $fxb = isset($fxBadges[(int)$r['casino_id']]) ? $fxBadges[(int)$r['casino_id']] : null;
                    $fxSlug = $fxb ? strtolower(trim(preg_replace('/[^a-z]+/i', '-', $fxb['tier']), '-')) : '';
                ?>
                    <tr>
                        <td class="pi-rank"><span class="pi-rank-chip"><?php echo (int)$i; ?></span></td>
                        <td class="pi-casino">
                            <a class="pi-casino-name" href="<?php echo $h($r['url']); ?>"><?php echo $h($r['title']); ?></a>
                            <?php if ($fxb): ?>
                            <a class="fx-badge fx-badge--<?php echo $h($fxSlug); ?> pi-fx-badge" href="<?php echo $h($r['url']); ?>#fxshield" title="FXShield&trade; Safety Index <?php echo (int)$fxb['score']; ?>/100 &mdash; <?php echo $h($fxb['tier']); ?>. Speed is one fact; this is the safety assessment."><?php echo $h($fxb['tier']); ?> &middot; <?php echo (int)$fxb['score']; ?></a>
                            <?php endif; ?>
                            <a class="pi-casino-cta" href="<?php echo $h($r['url']); ?>#fxc-start"><span class="pi-cta-long">Time your own withdrawal</span><span class="pi-cta-short">Time yours</span> &rarr;</a>
                        </td>
                        <td class="num pi-measured">
                            <span class="pi-mono pi-strong"><?php echo $h($fmH($r['measured_hours'], $mode)); ?></span>
                            <?php if ((int)$r['tests_count'] > 1 && $r['median_hours'] !== null): ?>
                                <span class="pi-sub">median of <?php echo (int)$r['tests_count']; ?> tests: <?php echo $h($fmH($r['median_hours'], $mode)); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="num"><span class="pi-mono"><?php echo $h($fmH($r['declared_hours'], $mode)); ?></span></td>
                        <td class="num"><span class="pi-delta <?php echo $deltaCls; ?>" title="<?php echo $h($deltaTitle); ?>"><?php echo $h($fmD($delta)); ?></span></td>
                        <td class="pi-date pi-col-date"><?php if ($r['requested_date'] !== ''): ?><time datetime="<?php echo $h($r['requested_date']); ?>"><?php echo $h($fmT($r['requested_date'])); ?></time><?php else: ?>&mdash;<?php endif; ?></td>
                        <td class="pi-verif">
                            <span class="pi-badge">Staff Verified</span>
                            <?php if ($r['report_url'] !== ''): ?>
                                <a class="pi-report" href="<?php echo $h($r['report_url']); ?>">Report</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="pi-scroll-hint">Swipe sideways for the full table.</p>
            <p class="pi-note"><strong>Declared</strong> is the slowest end of the processing time the operator stated when the test was requested. <strong>Measured</strong> runs from the withdrawal request to the money received, in UTC. One desk test is one withdrawal: it is evidence, not a guarantee.</p>
            <?php endif; ?>
        </section>

        <?php // -- Lane 2: player-timed withdrawals (FXClock) ?>
        <section id="player-timed" class="rg-section pi-section">
            <h2>Player-timed withdrawals</h2>
            <p class="pi-caption">FXClock medians, fastest first. A brand is listed once <?php echo (int)$threshold; ?> live clocks have stopped; backdated clocks never count.</p>

            <?php if (!empty($players)): ?>
            <div class="pi-table-wrap">
            <table class="pi-table">
                <thead>
                    <tr>
                        <th class="pi-rank">#</th>
                        <th>Casino</th>
                        <th class="num">Player median</th>
                        <th class="num">Middle half</th>
                        <th class="num">Clocks stopped</th>
                        <th class="num">Running now</th>
                        <th class="pi-col-date">Last received</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 0; foreach ($players as $r): $i++;
                    $pm = AuthorsModelPayoutindex::rowMode(array($r['median_min'] / 60.0, ($r['p25_min'] !== null) ? $r['p25_min'] / 60.0 : null));
                ?>
                    <tr>
                        <td class="pi-rank"><span class="pi-rank-chip"><?php echo (int)$i; ?></span></td>
                        <td class="pi-casino">
                            <a class="pi-casino-name" href="<?php echo $h($r['url']); ?>"><?php echo $h($r['title']); ?></a>
                            <a class="pi-casino-cta" href="<?php echo $h($r['url']); ?>#fxc-start"><span class="pi-cta-long">Add your clock</span><span class="pi-cta-short">Add yours</span> &rarr;</a>
                        </td>
                        <td class="num"><span class="pi-mono pi-strong"><?php echo $h($fmM($r['median_min'], $pm)); ?></span></td>
                        <td class="num"><span class="pi-mono"><?php echo ($r['p25_min'] !== null && $r['p75_min'] !== null) ? $h($fmM($r['p25_min'], $pm) . ' – ' . $fmM($r['p75_min'], $pm)) : '&mdash;'; ?></span></td>
                        <td class="num"><?php echo (int)$r['stopped_count']; ?></td>
                        <td class="num"><?php echo (int)$r['running_count']; ?></td>
                        <td class="pi-date pi-col-date"><?php echo $r['last_received_at'] !== '' ? '<time datetime="' . $h(substr($r['last_received_at'], 0, 10)) . '">' . $h($fmT($r['last_received_at'])) . '</time>' : '&mdash;'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="pi-scroll-hint">Swipe sideways for the full table.</p>
            <p class="pi-note"><strong>Middle half</strong> is the range between the 25th and 75th percentile of stopped clocks. Refused and unresolved withdrawals are counted separately on each casino page and never enter the median.</p>

            <?php else: ?>
            <div class="pi-empty">
                <h3>No brand has reached <?php echo (int)$threshold; ?> stopped clocks yet</h3>
                <p>FXClock has run on casino review pages since September 2026. Medians publish per brand once <?php echo (int)$threshold; ?> live clocks have stopped; until then the clocks run in public, one casino page at a time.</p>
            </div>
            <?php endif; ?>

            <?php if (!empty($collect)): ?>
            <h3 class="pi-subhead">Clocks collected so far</h3>
            <div class="pi-table-wrap">
            <table class="pi-table pi-table--collect">
                <thead>
                    <tr>
                        <th>Casino</th>
                        <th class="num">Clocks stopped</th>
                        <th class="num">Running now</th>
                        <th>Last receipt</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($collect as $r):
                    $l  = $r['last'];
                    $gl = ($l && isset($gradeLabel[$l['grade']])) ? $gradeLabel[$l['grade']] : '';
                ?>
                    <tr>
                        <td class="pi-casino">
                            <a class="pi-casino-name" href="<?php echo $h($r['url']); ?>"><?php echo $h($r['title']); ?></a>
                            <a class="pi-casino-cta" href="<?php echo $h($r['url']); ?>#fxc-start"><span class="pi-cta-long">Add your clock</span><span class="pi-cta-short">Add yours</span> &rarr;</a>
                        </td>
                        <td class="num"><span class="pi-mono pi-strong"><?php echo (int)$r['stopped_count']; ?></span> <span class="pi-sub-inline">of <?php echo (int)$threshold; ?></span></td>
                        <td class="num"><?php echo (int)$r['running_count']; ?></td>
                        <td class="pi-receipt">
                            <?php if ($l): ?>
                                <span class="pi-mono pi-strong"><?php echo $h($fmM($l['duration_min'])); ?></span>
                                <?php if ($gl !== ''): ?><span class="pi-grade">grade <?php echo $h($l['grade']); ?> &middot; <?php echo $h($gl); ?></span><?php endif; ?>
                                <?php if ($l['stopped_at'] !== ''): ?><time datetime="<?php echo $h(substr($l['stopped_at'], 0, 10)); ?>"><?php echo $h($fmT($l['stopped_at'])); ?></time><?php endif; ?>
                            <?php else: ?>&mdash;<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="pi-note">Ordered by clocks collected, not by time: one or two receipts are facts about one or two withdrawals, not a speed. The median publishes at <?php echo (int)$threshold; ?>.</p>
            <?php endif; ?>

            <?php if (empty($players) && empty($collect)): ?>
            <?php // 2.7.89: a brand with a running clock now sits in the collection table (0 of N, 1 running); the tiles remain for the day the table is empty. ?>
            <?php if (!empty($feed['running'])): ?>
            <h3 class="pi-subhead">Clocks running now</h3>
            <div class="pi-tiles">
                <?php foreach ($feed['running'] as $t): ?>
                <a class="pi-tile" href="<?php echo $h($t['url']); ?>#fxclock">
                    <span class="pi-tile-name"><?php echo $h($t['title']); ?></span>
                    <span class="pi-tile-num"><?php echo (int)$t['count']; ?> <?php echo ((int)$t['count'] === 1) ? 'clock' : 'clocks'; ?></span>
                    <?php if ($t['oldest'] !== ''): ?><span class="pi-tile-sub">oldest since <?php echo $h($fmT($t['oldest'])); ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($feed['latest']) && empty($collect)): ?>
            <h3 class="pi-subhead">Latest receipts</h3>
            <ul class="pi-receipts">
                <?php foreach ($feed['latest'] as $l):
                    $g = strtoupper((string)$l['grade']);
                    $gl = isset($gradeLabel[$g]) ? $gradeLabel[$g] : '';
                ?>
                <li>
                    <a href="<?php echo $h($l['url']); ?>#fxclock"><?php echo $h($l['title']); ?></a>
                    <span class="pi-mono pi-strong"><?php echo $h($fmM($l['duration_min'])); ?></span>
                    <?php if ($gl !== ''): ?><span class="pi-grade">grade <?php echo $h($g); ?> &middot; <?php echo $h($gl); ?></span><?php endif; ?>
                    <?php if ($l['stopped_at'] !== ''): ?><time datetime="<?php echo $h(substr($l['stopped_at'], 0, 10)); ?>"><?php echo $h($fmT($l['stopped_at'])); ?></time><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <p class="pi-cta-line">Withdrawing from a casino covered here? Open its <a href="<?php echo $h($casinosUrl); ?>">review</a> and start a clock. Two taps, one fact. <a href="<?php echo $h($fxclockUrl); ?>">How FXClock works &rarr;</a></p>
            <?php endif; ?>
        </section>

        <?php // -- Method ?>
        <section id="method" class="rg-section">
            <h2>How to read this register</h2>
            <ol class="rg-steps">
                <li><strong>Timestamps.</strong> Request and receipt times are logged in UTC; the elapsed time is their difference, to the minute.</li>
                <li><strong>Declared.</strong> The slowest end of the processing window the operator stated in its own terms or cashier when the test was requested. &ldquo;Up to 24 hours&rdquo; is recorded as 24h.</li>
                <li><strong>One test, one row.</strong> A desk test is a single withdrawal on a single account. It is never averaged with other brands and never with player clocks. Where a casino has more than one desk test, the most recent sets the rank and the median of all tests is shown underneath.</li>
                <li><strong>Player clocks.</strong> FXClock medians appear from <?php echo (int)$threshold; ?> stopped live clocks. Backdated clocks are excluded; refused and unresolved withdrawals are counted separately on the casino page.</li>
                <li><strong>Exclusions.</strong> Brands marked closed, suspended or blacklisted by the FreeExtraChips desk are not listed, whatever their speed. The FXShield scoring derived from these tests is documented in the <a href="<?php echo $h($fxshieldUrl); ?>">FXShield methodology</a> and is not reproduced here.</li>
                <li><strong>Updates.</strong> The register changes when a desk test is committed or a player clock stops with the money received. The &ldquo;Register data&rdquo; date under the title is the later of those two; a statistics rebuild that adds no receipt does not move it.</li>
            </ol>
        </section>

        <?php // -- FAQ (live page only; editions carry the frozen list) ?>
        <?php if (!$edition): ?>
        <section id="faq" class="rg-section pi-faq">
            <h2>Questions</h2>
            <?php foreach ($faq as $f): ?>
            <div class="pi-faq-item">
                <h3><?php echo $h($f['q']); ?></h3>
                <p><?php echo $h($f['a']); ?></p>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

    </div>

    <?php // -- ASIDE ------------------------------------------------------ ?>
    <aside class="au-side rg-side" aria-label="On this page">
        <div class="au-side-card rg-toc">
            <div class="eyebrow">On this page</div>
            <ul>
                <?php if ($edition): ?><li><a href="#changes">What changed</a></li><?php endif; ?>
                <li><a href="#desk-tested">Desk-tested withdrawals</a></li>
                <li><a href="#player-timed">Player-timed withdrawals</a></li>
                <li><a href="#method">How to read this register</a></li>
                <?php if (!$edition): ?><li><a href="#faq">Questions</a></li><?php endif; ?>
            </ul>
        </div>

        <?php if (!empty($editions) || $edition): ?>
        <div class="au-side-card pi-editions">
            <div class="eyebrow">Monthly editions</div>
            <?php if ($edition): ?>
                <p><a href="<?php echo $h($indexUrl); ?>">&larr; Live register</a></p>
            <?php else: ?>
                <p>The register is frozen on the first of each month. Each edition keeps its tables and lists what changed.</p>
            <?php endif; ?>
            <ul>
                <?php foreach ($editions as $e): $cur = ($edition && $e['edition'] === $edition['edition']); ?>
                <li<?php echo $cur ? ' class="is-current"' : ''; ?>>
                    <?php if ($cur): ?>
                        <span><?php echo $h(AuthorsModelPayoutindex::editionNumberLabel($e['number'])); ?> &middot; <?php echo $h(AuthorsModelPayoutindex::editionMonthLabel($e['edition'])); ?></span>
                    <?php else: ?>
                        <a href="<?php echo $h(JUri::base(true) . AuthorsModelPayoutindex::editionPath($e['edition'])); ?>"><?php echo $h(AuthorsModelPayoutindex::editionNumberLabel($e['number'])); ?> &middot; <?php echo $h(AuthorsModelPayoutindex::editionMonthLabel($e['edition'])); ?></a>
                    <?php endif; ?>
                    <small><?php echo (int)$e['desk_tests']; ?> tests</small>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="au-side-card pi-about">
            <div class="eyebrow">About the register</div>
            <p>Two sources, two tables. Desk tests are withdrawals requested by FreeExtraChips staff and timed in UTC. Player clocks are FXClock timings started and stopped by players, published as a median from <?php echo (int)$threshold; ?> clocks. Neither is adjusted, weighted or combined.</p>
            <p>Every casino listed links to its review, where a clock can be started in two taps.</p>
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
            <p style="margin-top: 8px;"><a href="<?php echo $h($fxshieldUrl); ?>">How FXShield works</a> &mdash; the desk verification behind each test.</p>
            <p style="margin-top: 8px;"><a href="<?php echo $h($fxclockUrl); ?>">How FXClock works</a> &mdash; player-timed clocks and the four grades.</p>
            <p style="margin-top: 8px;"><a href="<?php echo $h($atlasUrl); ?>">Operator Atlas</a> &mdash; who runs which casino.</p>
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
        <?php if ($edition): ?>
            <div class="updated">Frozen on <time datetime="<?php echo $h(substr($edition['published_at'], 0, 10)); ?>"><?php echo $h($frozenTxt); ?></time></div>
        <?php elseif ($dataAsOfTxt !== ''): ?>
            <div class="updated">Register data: <time datetime="<?php echo $h($dataAsOf); ?>"><?php echo $h($dataAsOfTxt); ?></time></div>
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

</div>
