<?php
defined('_JEXEC') or die;

/**
 * Licence Watch — /online-casinos/licence-watch[/YYYY-MM]
 * (Joomla layout suffix: default_licence-watch.php; kind='page' topic 'licence-watch')
 *
 * Renders one edition of the quarterly licence register from its frozen
 * payload (models/licencewatch.php). The desk writes three sections
 * (overview, quarter, bottom line) in the dashboard; every table, figure and
 * register paragraph below is generated from the payload so the numbers can
 * never drift from the data they were frozen with.
 *
 * Data from views/page/view.html.php:
 *   $this->edition   edition row with payload/diff/events/sections (or null)
 *   $this->editions  light list (published; drafts too in desk preview)
 */

require_once JPATH_COMPONENT . '/models/licencewatch.php';

$cfg      = is_array($this->config) ? $this->config : array();
$author   = $this->author;
$reviewer = $this->reviewer;
$edition  = (isset($this->edition) && is_array($this->edition)) ? $this->edition : null;
$editions = (isset($this->editions) && is_array($this->editions)) ? $this->editions : array();

$h   = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$n   = function ($v) { return number_format((int)$v); };
$pl  = function ($v, $one, $many) { return ((int)$v === 1) ? $one : $many; };
$fmT = function ($d, $long = false) { return AuthorsModelLicencewatch::fmtDate($d, $long); };
$pct = function ($a, $b) { return AuthorsModelLicencewatch::pct($a, $b); };

$siteRoot = rtrim(JUri::root(), '/');
$abs = function ($u) use ($siteRoot) { $u = trim((string)$u); if ($u === '') return ''; if (preg_match('#^https?://#i', $u)) return $u; return $siteRoot . '/' . ltrim($u, '/'); };
$authorUrl   = ($author   && !empty($author['slug']))   ? JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($author['slug']))   : '';
$reviewerUrl = ($reviewer && !empty($reviewer['slug'])) ? JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($reviewer['slug'])) : '';
$indexUrl    = JUri::base(true) . AuthorsModelLicencewatch::INDEX_PATH;
$atlasUrl    = JUri::base(true) . '/online-casinos/operators';
$clustersUrl = JUri::base(true) . '/online-casinos/clusters';
$fxshieldUrl = JRoute::_('index.php?option=com_authors&view=methodology&topic=fxshield');
$discloseUrl = JRoute::_('index.php?option=com_authors&view=page&slug=affiliate-disclosure');
$pageRel     = $edition ? AuthorsModelLicencewatch::editionPath($edition['edition']) : JRoute::_('index.php?option=com_authors&view=page&slug=licence-watch');
$pageAbs     = $abs($pageRel);

// ---- Breadcrumb -----------------------------------------------------------
$crumbHtml = ''; $crumbJsonLd = '';
if (function_exists('fec_build_breadcrumb_items_from_path') && function_exists('fec_render_breadcrumb_html')) {
    $path = JUri::getInstance()->getPath(); $siteBase = rtrim(JUri::base(true), '/');
    $crumbItems = fec_build_breadcrumb_items_from_path($path, $siteBase);
    if (!empty($crumbItems)) {
        $lastIdx = count($crumbItems) - 1;
        if (isset($crumbItems[$lastIdx]) && is_array($crumbItems[$lastIdx])) {
            foreach (array('name', 'label', 'title', 'text') as $k) if (array_key_exists($k, $crumbItems[$lastIdx])) $crumbItems[$lastIdx][$k] = $cfg['title'];
        }
        $crumbHtml = fec_render_breadcrumb_html($crumbItems);
        if (function_exists('fec_render_breadcrumb_jsonld')) $crumbJsonLd = fec_render_breadcrumb_jsonld($crumbItems);
    }
}

// ---- Edition facts ---------------------------------------------------------
$P = $edition ? $edition['payload'] : null;
$S = $edition ? $edition['sections'] : array();
$D = $edition ? $edition['diff'] : null;
$EV = $edition ? $edition['events'] : array();
$isDraft = $edition && (string)$edition['status'] !== 'published';
$edLabel = $edition ? AuthorsModelLicencewatch::editionNumberLabel($edition['number']) . ' &middot; ' . AuthorsModelLicencewatch::editionMonthLabel($edition['edition']) : '';
$asOf    = $edition ? (string)$P['as_of'] : '';
$pubTxt  = $edition ? $fmT($edition['published_at'] ? $edition['published_at'] : $edition['frozen_at'], true) : '';
$sec = function ($k) use ($S) { return (isset($S[$k]) && trim((string)$S[$k]) !== '') ? (string)$S[$k] : ''; };

$declaredRow = function ($key) use ($P) { if (!$P) return 0; foreach ($P['declared'] as $d) if ($d['key'] === $key) return (int)$d['count']; return 0; };
$dataJson = ''; $faqJson = '';
if ($edition) {
    $dataset = array(
        '@context' => 'https://schema.org',
        '@type' => 'Dataset',
        '@id' => $pageAbs . '#dataset',
        'name' => 'FEC Licence Watch — ' . html_entity_decode(str_replace('&middot;', '·', $edLabel), ENT_QUOTES, 'UTF-8'),
        'description' => 'Quarterly register of online casino licences on FreeExtraChips: ' . (int)$P['totals']['live'] . ' live brands by declared regulator, ' . (int)$P['verified']['brands'] . ' verified against the regulators\' registers via the FEC Operator Atlas.',
        'url' => $pageAbs,
        'creator' => array('@type' => 'Organization', '@id' => $siteRoot . '/#organization', 'name' => 'FreeExtraChips'),
        'isAccessibleForFree' => true,
        'dateModified' => $asOf,
        'variableMeasured' => array('Live casino brands by declared licensing jurisdiction', 'Brands with operator and licence verified against the regulator register', 'Brands declaring no gambling regulator'),
    );
    $dataJson = json_encode($dataset, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
}
?>
<div class="fecx rg-page pi-page lw-page" id="lw-top">

<?php if ($crumbHtml !== ''): ?><div class="wrap"><?php echo $crumbHtml; ?></div><?php endif; ?>

<section class="au-hero rg-hero pi-hero">
    <div class="wrap">
        <div class="eyebrow"><?php echo $edition ? 'Licence Watch &middot; ' . $edLabel : 'Licence Watch &middot; Quarterly register'; ?></div>
        <h1 class="au-hero-title"><?php echo $h($cfg['title']); ?></h1>
        <p class="au-hero-lede"><?php echo $h($cfg['tagline']); ?></p>
        <?php if ($isDraft): ?><div class="lw-draft">Draft &mdash; visible to the desk only. Not published, not indexed.</div><?php endif; ?>

        <div class="rg-hero-meta">
            <?php if ($author): ?>
                <div class="rg-hero-byline"><span class="rg-bl-label">Written by</span> <a href="<?php echo $h($authorUrl); ?>" rel="author"><?php echo $h($author['name']); ?></a><?php if (!empty($author['role'])): ?> <span class="rg-bl-role"><?php echo $h($author['role']); ?></span><?php endif; ?></div>
            <?php endif; ?>
            <?php if ($reviewer): ?>
                <div class="rg-hero-byline"><span class="rg-bl-label">Reviewed by</span> <a href="<?php echo $h($reviewerUrl); ?>"><?php echo $h($reviewer['name']); ?></a><?php if (!empty($reviewer['role'])): ?> <span class="rg-bl-role"><?php echo $h($reviewer['role']); ?></span><?php endif; ?></div>
            <?php endif; ?>
            <?php if ($edition): ?>
                <div class="rg-hero-updated"><span class="rg-bl-label">Register frozen</span> <time datetime="<?php echo $h($asOf); ?>"><?php echo $h($fmT($asOf, true)); ?></time></div>
                <?php if ($edition['published_at']): ?><div class="rg-hero-updated"><span class="rg-bl-label">Published</span> <time datetime="<?php echo $h(substr($edition['published_at'], 0, 10)); ?>"><?php echo $h($pubTxt); ?></time></div><?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($edition): ?>
        <div class="au-stats-strip pi-stats">
            <div class="stat"><div class="num"><?php echo $n($P['totals']['live']); ?></div><div class="lbl">Live casinos counted</div></div>
            <div class="stat"><div class="num"><?php echo $n($P['verified']['brands']); ?></div><div class="lbl">Verified against the registers</div></div>
            <div class="stat"><div class="num"><?php echo $n($P['curacao']['declared']); ?></div><div class="lbl">Declare Cura&ccedil;ao</div></div>
            <div class="stat"><div class="num"><?php echo $n($P['no_regulator']['total']); ?></div><div class="lbl">No gambling regulator</div></div>
        </div>
        <?php endif; ?>
    </div>
</section>

<div class="wrap au-main rg-main">
    <div class="rg-content">

<?php if (!$edition): ?>
        <section id="intro" class="rg-section">
            <p class="rg-lede">The first edition of this register is in preparation.</p>
            <p>Licence Watch counts the licensing picture across every casino listed on FreeExtraChips, two ways: what each brand declares, and what this desk has verified against the regulators&#8217; own registers. Editions are frozen quarterly and kept here, with what changed between them.</p>
        </section>
<?php else: ?>

        <?php // -- Overview (desk) ?>
        <section id="overview" class="rg-section">
            <?php if ($sec('overview') !== ''): ?>
                <?php echo $sec('overview'); ?>
            <?php else: ?>
                <p class="rg-lede"><?php echo $n($P['totals']['live']); ?> live casinos. <?php echo $n($P['verified']['brands']); ?> verified. <?php echo $n($P['no_regulator']['total']); ?> with no regulator.</p>
                <p>This edition&#8217;s overview has not been written yet.</p>
            <?php endif; ?>
        </section>

        <?php if ($D !== null): ?>
        <section id="changes" class="pi-changes">
            <h2>What changed since the previous edition</h2>
            <ul>
                <li><strong>Live catalogue:</strong> <?php echo ($D['live_delta'] > 0 ? '+' : '') . (int)$D['live_delta']; ?> brands.</li>
                <li><strong>Verified layer:</strong> <?php echo ($D['verified_delta'] > 0 ? '+' : '') . (int)$D['verified_delta']; ?> brands; certificate-confirmed Cura&ccedil;ao <?php echo ($D['curacao_confirmed_delta'] > 0 ? '+' : '') . (int)$D['curacao_confirmed_delta']; ?>; unverified Cura&ccedil;ao claims <?php echo ($D['curacao_unverified_delta'] > 0 ? '+' : '') . (int)$D['curacao_unverified_delta']; ?>.</li>
                <li><strong>No gambling regulator:</strong> <?php echo ($D['no_regulator_delta'] > 0 ? '+' : '') . (int)$D['no_regulator_delta']; ?>.</li>
                <?php $labels = AuthorsModelLicencewatch::regulatorLabels(); foreach (array_slice((array)$D['declared'], 0, 6) as $x): ?>
                <li><strong><?php echo isset($labels[$x['key']]) ? $labels[$x['key']] : $h(ucwords(str_replace('-', ' ', $x['key']))); ?>:</strong> <?php echo (int)$x['from']; ?> &rarr; <?php echo (int)$x['to']; ?> declared.</li>
                <?php endforeach; ?>
                <li><strong>Licence events logged:</strong> <?php echo (int)$D['events_total']; ?><?php if (!empty($D['events_by_type'])): $bits = array(); foreach ($D['events_by_type'] as $t => $c) $bits[] = (int)$c . ' ' . strtolower(AuthorsModelLicencewatch::eventTypeLabel($t)); echo ' (' . $h(implode(', ', $bits)) . ')'; endif; ?>.</li>
            </ul>
        </section>
        <?php endif; ?>

        <?php // -- Method (static) ?>
        <section id="method" class="rg-section">
            <h2>How this register is built</h2>
            <p>The population is every published listing that is neither closed nor suspended on the day of freezing: <?php echo $n($P['totals']['live']); ?> brands on <?php echo $h($fmT($asOf, true)); ?>. Of the <?php echo $n($P['totals']['listed']); ?> casinos this site has ever reviewed, <?php echo $n($P['totals']['closed'] + $P['totals']['suspended']); ?> are closed or suspended and <?php echo $n($P['totals']['unpublished']); ?> were withdrawn from publication. Casinos we have blacklisted remain in the count while they operate &mdash; a blacklist is a verdict on conduct, not a statement that the licence does not exist &mdash; and <?php echo $n($P['totals']['blacklisted_live']); ?> live listings carry that flag.</p>
            <p>The declared layer is the licence field of each listing, which records the jurisdictions a brand cites in its own footer or terms. <?php echo $n($P['totals']['multi_licence']); ?> brands cite more than one, and each citation is counted once under its regulator, so the declared figures sum to more than <?php echo $n($P['totals']['live']); ?>. The verified layer is the <a href="<?php echo $h($atlasUrl); ?>">FEC Operator Atlas</a> &mdash; our public register of operating companies, their licences and the brands linked to them &mdash; and only links marked as current are counted. A brand enters the verified layer when we have matched it to an operator ourselves; the operator&#8217;s licence is then recorded at one of three confidence levels, confirmed, probable or single-source, and every figure on this page says which.</p>
            <p>Nothing is inherited. A sibling brand on the same affiliate programme does not acquire its sibling&#8217;s licence here, a verified operator does not verify a brand we have not matched to it, and where the two layers disagree the register says so and the listing is corrected, not the register.</p>
        </section>

        <?php // -- Declared map (generated) ?>
        <section id="declared" class="rg-section pi-section">
            <h2>The declared map</h2>
            <p class="pi-caption">Every regulator cited by a live listing. Where a brand cites two, both rows gain one.</p>
            <div class="pi-table-wrap">
            <table class="pi-table pi-table--collect">
                <thead><tr><th>Regulator</th><th class="num">Live brands</th><th class="num">Share</th><th class="num">Ever listed</th></tr></thead>
                <tbody>
                <?php foreach ($P['declared'] as $d): ?>
                    <tr>
                        <td><?php echo $d['label']; ?></td>
                        <td class="num"><span class="pi-mono pi-strong"><?php echo $n($d['count']); ?></span></td>
                        <td class="num"><span class="pi-mono"><?php echo $pct($d['count'], $P['totals']['live']); ?>%</span></td>
                        <td class="num"><span class="pi-mono"><?php echo $n($d['ever']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                    <tr><td>Field empty</td><td class="num"><span class="pi-mono pi-strong"><?php echo $n($P['totals']['blank']); ?></span></td><td class="num"><span class="pi-mono"><?php echo $pct($P['totals']['blank'], $P['totals']['live']); ?>%</span></td><td class="num">&mdash;</td></tr>
                </tbody>
            </table>
            </div>
            <p class="pi-scroll-hint">Swipe sideways for the full table.</p>
            <p class="pi-note"><strong>Share</strong> is of the <?php echo $n($P['totals']['live']); ?> live brands. <strong>Ever listed</strong> counts every listing that has cited the regulator, closed and suspended included.</p>
            <p>More than one regulator is cited by <?php echo $n($P['totals']['multi_licence']); ?> of the <?php echo $n($P['totals']['live']); ?> live brands: <?php echo $n($P['pairs']['uk_malta']); ?> pair a British licence with a Maltese one, the classic dual-licence estate, and <?php echo $n($P['pairs']['malta_curacao']); ?> pair Malta with Cura&ccedil;ao, a pairing that usually signals two separate operating companies behind one brand. The Cura&ccedil;ao figure, large as it is, says less than it appears to: since the island&#8217;s reform the label has covered both a direct licence issued by the Cura&ccedil;ao Gaming Authority, with a number of the form OGL/2024/nnnn/nnnn, and a sub-licence issued by one of the four former master-licence holders, a category that has been wound down. A footer can say &ldquo;Cura&ccedil;ao&rdquo; while citing a number from the second category, and nothing in the word itself tells a player which it is. That is what the verified layer is for.</p>
        </section>

        <?php // -- Verified layer (generated) ?>
        <section id="verified" class="rg-section pi-section">
            <h2>The verified layer</h2>
            <p>The Operator Atlas currently links <?php echo $n($P['verified']['brands']); ?> live brands to a named operating company, across <?php echo $n($P['verified']['operators']); ?> operator entities and <?php echo $n($P['verified']['licences']); ?> licence records &mdash; <?php echo $pct($P['verified']['brands'], $P['totals']['live']); ?> per cent of the live catalogue. The table groups them by the licence their operator holds and by the confidence we hold it at. <strong>Confirmed</strong> means a certificate, validator result or register entry pulled by this desk; <strong>pending</strong> means a licence number seen first-party, in a footer or in terms, with the register pull still to come; <strong>expired</strong> means a recorded licence from a regime that no longer issues or renews it.</p>
            <div class="pi-table-wrap">
            <table class="pi-table pi-table--collect">
                <thead><tr><th>Licence</th><th>Status</th><th class="num">Brands</th><th class="num">Operators</th></tr></thead>
                <tbody>
                <?php foreach ($P['verified']['groups'] as $g):
                    $b = $g['bucket'];
                    $cls = ($b === 'confirmed') ? 'pi-delta--faster' : (($b === 'pending') ? 'pi-delta--ontime' : 'pi-delta--slower');
                    $txt = ($b === 'confirmed') ? 'Confirmed' : (($b === 'pending') ? 'Pending' : (($b === 'expired') ? 'Expired' : 'No record'));
                ?>
                    <tr>
                        <td><?php echo $g['label']; ?></td>
                        <td><span class="pi-delta <?php echo $cls; ?>"><?php echo $txt; ?></span></td>
                        <td class="num"><span class="pi-mono pi-strong"><?php echo $n($g['brands']); ?></span></td>
                        <td class="num"><span class="pi-mono"><?php echo $n($g['operators']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="pi-scroll-hint">Swipe sideways for the full table.</p>
            <p class="pi-note">A brand whose operator holds more than one licence appears under each of them. Operators with no licence on record are companies we have identified without finding a current licence to attach to them.</p>
            <p>The coverage is weighted, not random: it follows the operators this desk has investigated in its <a href="<?php echo $h($clustersUrl); ?>">network series</a>, which is where the paperwork most needed checking. Estates on regulators that publish searchable licensee registers are under-represented here for the opposite reason &mdash; their footers tend to be accurate, and the verification queue has gone where the risk is.</p>
        </section>

        <?php // -- Curaçao (generated) ?>
        <?php $C = $P['curacao']; $checked = (int)$C['verified_confirmed'] + (int)$C['verified_pending']; ?>
        <section id="curacao" class="rg-section">
            <h2>Cura&ccedil;ao after the reform</h2>
            <p>Of the <?php echo $n($C['declared']); ?> live brands that declare a Cura&ccedil;ao licence, we have verified the operator and the licence for <?php echo $n($checked); ?>: <?php echo $n($C['verified_confirmed']); ?> on certificate-confirmed OGL licences held by <?php echo $n($C['operators_confirmed']); ?> <?php echo $pl($C['operators_confirmed'], 'company', 'companies'); ?>, and <?php echo $n($C['verified_pending']); ?> on OGL numbers we have seen but not yet pulled from the register, held by <?php echo $n($C['operators_pending']); ?> <?php echo $pl($C['operators_pending'], 'company', 'companies'); ?>. The remaining <?php echo $n($C['unverified_claims']); ?> are, for the purposes of this page, claims &mdash; not false, not confirmed, and the main item of work between this edition and the next.</p>
            <?php if ($C['grant_min'] !== '' && $C['grant_max'] !== ''): ?>
            <p>Every confirmed licence in the block is a direct licence of the new regime; those with a recorded grant date were granted between <?php echo $h($fmT($C['grant_min'], true)); ?> and <?php echo $h($fmT($C['grant_max'], true)); ?>. The structure is the single-licence, multi-domain model the reform produced: one OGL number, several casinos, each domain named individually on the regulator&#8217;s certificate portal. That is what makes the model checkable, and what makes a sibling discoverable. It also means a finding at one domain travels: a complaint pattern, a terms clause or a withdrawal cap set at the operator level reaches every casino on the certificate.</p>
            <?php endif; ?>
            <?php if ((int)$C['ghost_records'] > 0): ?>
            <p>The ghosts are the other half of the story: <?php echo $n($C['ghost_records']); ?> sub-licence <?php echo $pl($C['ghost_records'], 'string', 'strings'); ?> from the master-licence era <?php echo $pl($C['ghost_records'], 'remains', 'remain'); ?> in our register, marked expired, attached to <?php echo $n($C['ghost_entities']); ?> <?php echo $pl($C['ghost_entities'], 'company', 'companies'); ?> that operated listed brands. The system that issued them has been wound down; the numbers still circulate, in stale footers and in third-party listings that were never updated. The rule this desk applies, and the one worth borrowing, is simple: a Cura&ccedil;ao licence number that does not begin with OGL belongs to a regime that no longer issues or renews licences, and it should be treated as a historical reference until the operator shows a current certificate.</p>
            <?php endif; ?>
        </section>

        <?php // -- New rails (generated) ?>
        <?php $Rt = $P['rails']['tobique']; $Ra = $P['rails']['anjouan']; ?>
        <section id="new-rails" class="rg-section">
            <h2>The new rails: Tobique and Anjouan</h2>
            <p>Two regulators absent from this catalogue a few years ago now cover <?php echo $n((int)$Rt['declared'] + (int)$Ra['declared']); ?> declared and <?php echo $n((int)$Rt['verified'] + (int)$Ra['verified']); ?> verified live brands. The verified share is the highest of any regulator on the page, because every brand on either licence arrived there through a migration this desk documented.</p>
            <p>Tobique is declared by <?php echo $n($Rt['declared']); ?> live <?php echo $pl($Rt['declared'], 'brand', 'brands'); ?>, of which <?php echo $n($Rt['verified']); ?> <?php echo $pl($Rt['verified'], 'is', 'are'); ?> verified on <?php echo $n($Rt['operators']); ?> <?php echo $pl($Rt['operators'], 'licence', 'licences'); ?> confirmed on the commission&#8217;s validator, which names the licensed domains. Anjouan is declared by <?php echo $n($Ra['declared']); ?>, and verified behind <?php echo $n($Ra['verified']); ?>, on <?php echo $n($Ra['operators_confirmed']); ?> confirmed and <?php echo $n($Ra['operators_pending']); ?> probable <?php echo $pl((int)$Ra['operators_confirmed'] + (int)$Ra['operators_pending'], 'licensee', 'licensees'); ?>.<?php if ((int)$Ra['mislabelled'] > 0): ?> Of the verified Anjouan brands, <?php echo $n($Ra['mislabelled']); ?> still declared another regulator in our own listings at the time of freezing &mdash; a residue of the era before the operator migrated &mdash; and <?php echo $pl($Ra['mislabelled'], 'is', 'are'); ?> corrected with this edition.<?php endif; ?></p>
        </section>

        <?php // -- Geography (generated) ?>
        <?php $G = $P['geography']; $shells = $G['payment_shells']; ?>
        <section id="geography" class="rg-section">
            <h2>Corporate geography</h2>
            <p>The register records where each operating company is incorporated. Among the operators behind the <?php echo $n($P['verified']['brands']); ?> verified brands: <?php
                $bits = array();
                foreach (array_slice($G['operators'], 0, 6) as $g) {
                    $bits[] = $n($g['operators']) . ' ' . (($g['jurisdiction'] === '?') ? $pl($g['operators'], 'company with no verified country of incorporation', 'companies with no verified country of incorporation') : (AuthorsModelLicencewatch::countryName($g['jurisdiction']) . ' ' . $pl($g['operators'], 'company', 'companies'))) . ' running ' . $n($g['brands']) . ' ' . $pl($g['brands'], 'brand', 'brands');
                }
                echo implode('; ', $bits);
            ?>. Licence, company and cashier routinely sit in three different places, and only the first of them is a regulator.</p>
            <?php if ((int)$shells['total'] > 0): $cy = isset($shells['by_jurisdiction']['cy']) ? (int)$shells['by_jurisdiction']['cy'] : 0; ?>
            <p>Beneath that layer sits a third one. The register holds <?php echo $n($shells['total']); ?> payment-processing companies that appear in the cashier clauses of the estates we have investigated &mdash; the entity named on the card statement, the counterparty for refunds &mdash; and <?php echo $n($cy); ?> of them are incorporated in Cyprus. None of them holds, or needs, a gambling licence.</p>
            <?php endif; ?>
        </section>

        <?php // -- No regulator (generated) ?>
        <?php $N = $P['no_regulator']; ?>
        <section id="no-regulator" class="rg-section">
            <h2>No gambling regulator</h2>
            <p>No regulator at all is declared by <?php echo $n($N['not_regulated']); ?> live <?php echo $pl($N['not_regulated'], 'listing', 'listings'); ?>, because our review found no licence to cite. Costa Rica is declared by <?php echo $n($N['costarica']); ?> more; it has no online-gambling licensing regime, and a company there operates under ordinary business registration, which regulates nothing about the games, the cashier or the handling of a complaint.<?php if ((int)$N['costarica_top_group'] > 1): ?> A single operating group accounts for <?php echo $n($N['costarica_top_group']); ?> of the <?php echo $n($N['costarica']); ?>.<?php endif; ?> Together, <?php echo $n($N['total']); ?> brands &mdash; <?php echo $pct($N['total'], $P['totals']['live']); ?> per cent of the live catalogue &mdash; are casinos at which the only recourse is the operator itself.</p>
            <p><?php if ((int)$N['nl_antilles'] > 0): ?>The label of the Netherlands Antilles, a jurisdiction that ceased to exist in 2010, is still carried by <?php echo $n($N['nl_antilles']); ?> <?php echo $pl($N['nl_antilles'], 'listing', 'listings'); ?>; the field is corrected with this edition to show no verified licence. <?php endif; ?><?php if ((int)$N['mwali'] > 0): ?>A licence from Mwali, an authority whose register we do not currently pull first-party, is held by <?php echo $n($N['mwali']); ?> <?php echo $pl($N['mwali'], 'brand', 'brands'); ?>, recorded here at declared level only. <?php endif; ?>And <?php echo $n($N['blacklisted_live']); ?> live <?php echo $pl($N['blacklisted_live'], 'listing carries', 'listings carry'); ?> our blacklist flag<?php if ((int)$N['blacklisted_curacao'] > 0): ?>, <?php echo $n($N['blacklisted_curacao']); ?> of them declaring Cura&ccedil;ao<?php endif; ?> &mdash; the reminder this section exists to make: a licence, even a real one, is a floor and not a verdict. The flag rests on documented conduct; the licence column records what the operator holds.</p>
        </section>

        <?php // -- Quarter: desk text + logged events ?>
        <section id="quarter" class="rg-section">
            <h2>What the desk documented this quarter</h2>
            <?php if ($sec('quarter') !== ''): ?><?php echo $sec('quarter'); ?><?php endif; ?>
            <?php if (!empty($EV)): ?>
            <h3 class="pi-subhead">Licence events logged in the register</h3>
            <ul class="lw-events">
                <?php foreach ($EV as $ev): ?>
                <li>
                    <time datetime="<?php echo $h($ev['event_date']); ?>"><?php echo $h($fmT($ev['event_date'])); ?></time>
                    <span class="lw-ev-type"><?php echo $h(AuthorsModelLicencewatch::eventTypeLabel($ev['event_type'])); ?></span>
                    <span class="lw-ev-title"><?php echo $h($ev['title']); ?></span>
                    <?php if (!empty($ev['entity_name']) || !empty($ev['brand_title'])): ?><span class="lw-ev-who"><?php echo $h(trim((!empty($ev['entity_name']) ? $ev['entity_name'] : '') . (!empty($ev['brand_title']) ? ' · ' . $ev['brand_title'] : ''), ' ·')); ?></span><?php endif; ?>
                    <span class="pi-grade"><?php echo $h(str_replace('_', ' ', (string)$ev['confidence'])); ?></span>
                    <?php if (!empty($ev['description'])): ?><p><?php echo $h($ev['description']); ?></p><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php elseif ($sec('quarter') === ''): ?>
            <p>No licence events were logged in the register during this period.</p>
            <?php endif; ?>
        </section>

        <?php // -- Verify (static) ?>
        <section id="verify" class="rg-section">
            <h2>Verify it yourself</h2>
            <p>Every verification on this page was made with public tools, and the same tools are available to any player with five minutes. The rule that governs their use is the one this desk applies everywhere: the footer of the live site, not the badge, the banner or the review you read elsewhere, tells you which company you are contracting with, and the regulator&#8217;s own register, not the casino&#8217;s own seal, tells you whether that company&#8217;s licence covers the domain you are on.</p>
            <p>For a Cura&ccedil;ao licence of the new regime, the Gaming Authority&#8217;s certificate portal at cert.cga.cw answers by domain: enter the casino&#8217;s address and the portal returns the certificate, the licence number and the licensee, or nothing. A number of the form OGL/2024/nnnn/nnnn with no certificate for your domain is not a licence for your domain. For Tobique, the commission&#8217;s validator at validate.thetgc.ca names the licensed domains for each licence number. For Anjouan, the seal on the casino&#8217;s own page links to the licensing authority&#8217;s validator, which returns the registered website and support address for the licence; a seal that links to the casino&#8217;s own page, or to nothing, has not validated anything. For Kahnawake, the commission publishes a certificate for each authorised brand; the brand, not only the operator, should appear. For a Maltese licence, the authority&#8217;s licensee register enumerates the approved URLs under each licence, and the dynamic seal on a licensed site resolves to that register entry. For a British licence, the public register lists the domains under each account number.</p>
            <p>Three outcomes are possible, and only one of them is good. The register names your domain: the licence covers you. The register names the company but not your domain: the licence exists, and does not cover you. The register returns nothing, or a number from a regime that no longer issues licences: treat the casino as unlicensed, whatever the footer says.</p>
        </section>

        <?php // -- Next edition (generated) ?>
        <section id="next-edition" class="rg-section">
            <h2>The next edition</h2>
            <p>Every licence fact this desk verifies &mdash; a licence granted, a migration, a revocation, a company struck off, a rebrand &mdash; is logged in the Operator Atlas as a dated event, and the next edition reports the quarter as a count rather than as a narrative: licences granted and lost, brands moved between regulators, and the two numbers that matter most &mdash; how many of the <?php echo $n($C['unverified_claims']); ?> unverified Cura&ccedil;ao claims have been checked, and how many of the <?php echo $n($N['total']); ?> brands without a regulator are still listed. The verification queue is set by the figures above: Cura&ccedil;ao claims first, largest estates first, because a single certificate pull verifies every domain on the licence. Any operator that disputes a figure on this page can write to the desk; corrections are made on the page, with the date, and the register is updated, not the other way round.</p>
        </section>

        <?php // -- Bottom line (desk) ?>
        <section id="bottom-line" class="rg-section">
            <h2>Bottom line</h2>
            <?php if ($sec('bottom_line') !== ''): ?>
                <?php echo $sec('bottom_line'); ?>
            <?php else: ?>
                <p>Of the <?php echo $n($P['totals']['live']); ?> live casinos, <?php echo $n($C['declared']); ?> cite a licence label that no longer distinguishes a current licence from an expired one, <?php echo $n($N['total']); ?> cite no regulator at all, and this desk can state as fact the licence behind <?php echo $n($P['verified']['brands']); ?>. For the other <?php echo $n((int)$P['totals']['live'] - (int)$P['verified']['brands']); ?>, this page says &ldquo;declared&rdquo; and means it.</p>
            <?php endif; ?>
            <p class="lf-fineprint">Register frozen on <?php echo $h($fmT($asOf, true)); ?> &mdash; declared figures are the operators&#8217; own statements as recorded in our listings; verified figures are register checks made by this desk at the confidence level stated, from the FEC Operator Atlas and first-party pulls of the Cura&ccedil;ao Gaming Authority certificate portal, the Tobique Gaming Commission validator, the Anjouan licensing authority validator, the Kahnawake Gaming Commission certificates and the Malta Gaming Authority register. Verify the operator entity and licence status on the live site and the regulator&#8217;s register before signing up.</p>
        </section>
<?php endif; ?>

    </div>

    <aside class="au-side rg-side" aria-label="On this page">
        <?php if ($edition): ?>
        <div class="au-side-card rg-toc">
            <div class="eyebrow">On this page</div>
            <ul>
                <li><a href="#overview">Overview</a></li>
                <?php if ($D !== null): ?><li><a href="#changes">What changed</a></li><?php endif; ?>
                <li><a href="#method">How this register is built</a></li>
                <li><a href="#declared">The declared map</a></li>
                <li><a href="#verified">The verified layer</a></li>
                <li><a href="#curacao">Cura&ccedil;ao after the reform</a></li>
                <li><a href="#new-rails">The new rails</a></li>
                <li><a href="#geography">Corporate geography</a></li>
                <li><a href="#no-regulator">No gambling regulator</a></li>
                <li><a href="#quarter">What the desk documented</a></li>
                <li><a href="#verify">Verify it yourself</a></li>
                <li><a href="#bottom-line">Bottom line</a></li>
            </ul>
        </div>
        <?php endif; ?>

        <div class="au-side-card pi-editions">
            <div class="eyebrow">Editions</div>
            <p>The register is frozen quarterly. Each edition keeps its tables and lists what changed.</p>
            <?php if (!empty($editions)): ?>
            <ul>
                <?php foreach ($editions as $e): $cur = ($edition && $e['edition'] === $edition['edition']); $isD = ((string)$e['status'] !== 'published'); ?>
                <li<?php echo $cur ? ' class="is-current"' : ''; ?>>
                    <?php if ($cur): ?>
                        <span><?php echo $h(AuthorsModelLicencewatch::editionNumberLabel($e['number'])); ?> &middot; <?php echo $h(AuthorsModelLicencewatch::editionMonthLabel($e['edition'])); ?><?php echo $isD ? ' (draft)' : ''; ?></span>
                    <?php else: ?>
                        <a href="<?php echo $h(JUri::base(true) . AuthorsModelLicencewatch::editionPath($e['edition']) . ($isD ? '?preview=1' : '')); ?>"><?php echo $h(AuthorsModelLicencewatch::editionNumberLabel($e['number'])); ?> &middot; <?php echo $h(AuthorsModelLicencewatch::editionMonthLabel($e['edition'])); ?><?php echo $isD ? ' (draft)' : ''; ?></a>
                    <?php endif; ?>
                    <small><?php echo (int)$e['live_total']; ?> live &middot; <?php echo (int)$e['verified_total']; ?> verified<?php if ($cur && !$isD): ?> &middot; <a href="<?php echo $h(JUri::base(true) . AuthorsModelLicencewatch::editionPath($e['edition'])); ?>" title="Permanent address of this edition">permalink</a><?php endif; ?></small>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <?php if ($reviewer): ?>
            <div class="au-side-card rg-reviewer-card">
                <div class="eyebrow">Reviewed by</div>
                <a href="<?php echo $h($reviewerUrl); ?>" class="rg-rc-link">
                    <?php if (!empty($reviewer['avatar_url'])): ?><img src="<?php echo $h($reviewer['avatar_url']); ?>" alt="<?php echo $h($reviewer['name']); ?>" class="rg-rc-avatar" width="48" height="48"><?php endif; ?>
                    <div class="rg-rc-body"><div class="rg-rc-name"><?php echo $h($reviewer['name']); ?></div><?php if (!empty($reviewer['role'])): ?><div class="rg-rc-role"><?php echo $h($reviewer['role']); ?></div><?php endif; ?></div>
                </a>
                <?php if (!empty($reviewer['short_bio'])): ?><p class="rg-rc-bio"><?php echo $h($reviewer['short_bio']); ?></p><?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="au-side-card rg-disclosure">
            <div class="eyebrow">Related</div>
            <p style="margin-top: 8px;"><a href="<?php echo $h($atlasUrl); ?>">Operator Atlas</a> &mdash; the public register behind the verified layer.</p>
            <p style="margin-top: 8px;"><a href="<?php echo $h($clustersUrl); ?>">Casino clusters</a> &mdash; the networks behind the brands.</p>
            <p style="margin-top: 8px;"><a href="<?php echo $h($fxshieldUrl); ?>">How FXShield works</a> &mdash; what a licence does and does not settle.</p>
            <p style="margin-top: 8px;"><a href="<?php echo $h($discloseUrl); ?>">Affiliate disclosure</a> &mdash; the firewall between partnerships and this register.</p>
        </div>
    </aside>
</div>

<div class="wrap rg-footer-byline">
    <div class="hub-byline">
        <?php if ($author): ?><div class="person"><span class="who">Written by</span> <span class="name"><a href="<?php echo $h($authorUrl); ?>" rel="author"><?php echo $h($author['name']); ?></a></span><?php if (!empty($author['role'])): ?> <span class="role"><?php echo $h($author['role']); ?></span><?php endif; ?></div><?php endif; ?>
        <?php if ($reviewer): ?><div class="person"><span class="who">Reviewed by</span> <span class="name"><a href="<?php echo $h($reviewerUrl); ?>"><?php echo $h($reviewer['name']); ?></a></span><?php if (!empty($reviewer['role'])): ?> <span class="role"><?php echo $h($reviewer['role']); ?></span><?php endif; ?></div><?php endif; ?>
        <?php if ($edition): ?><div class="updated">Register frozen <time datetime="<?php echo $h($asOf); ?>"><?php echo $h($fmT($asOf, true)); ?></time></div><?php endif; ?>
    </div>
</div>

<?php if ($this->jsonLdGraph !== ''): ?><script type="application/ld+json"><?php echo $this->jsonLdGraph; ?></script><?php endif; ?>
<?php if ($dataJson !== '' && $dataJson !== false && (string)($edition ? $edition['status'] : '') === 'published'): ?><script type="application/ld+json"><?php echo $dataJson; ?></script><?php endif; ?>
<?php if ($crumbJsonLd !== ''): ?><?php echo $crumbJsonLd; ?><?php endif; ?>

</div>
