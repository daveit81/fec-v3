<?php
defined('_JEXEC') or die;

/**
 * Editorial Standards methodology page (Joomla layout suffix: default_editorial-standards.php).
 *
 * Mirrors the scaffolding of default_rtp-verification.php and default_fxcheck.php
 * (hero, stats strip, content/sidebar TOC layout). Uses the same fec-edition.css +
 * methodology.css tokens.
 *
 * Data from views/methodology/view.html.php:
 *   $this->topic        slug ('editorial-standards')
 *   $this->config       topic config (title, tagline, author/reviewer slugs, version, last_updated)
 *   $this->author       byline author row (or null)
 *   $this->reviewer     reviewer row (or null)
 *   $this->jsonLdGraph  pre-encoded JSON-LD @graph
 */

$cfg      = is_array($this->config) ? $this->config : array();
$author   = $this->author;
$reviewer = $this->reviewer;

// ---- Local helpers --------------------------------------------------------
$h    = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$lastUpdated = !empty($cfg['last_updated']) ? date('j F Y', strtotime($cfg['last_updated'])) : '';
$version     = isset($cfg['version'])      ? (string)$cfg['version']      : '';

$authorUrl   = ($author   && !empty($author['slug']))   ? JRoute::_('index.php?option=com_authors&view=author&slug=' . $author['slug'])   : '';
$reviewerUrl = ($reviewer && !empty($reviewer['slug'])) ? JRoute::_('index.php?option=com_authors&view=author&slug=' . $reviewer['slug']) : '';

$rtpMethodUrl     = JRoute::_('index.php?option=com_authors&view=methodology&topic=rtp-verification');
$fxcheckMethodUrl = JRoute::_('index.php?option=com_authors&view=methodology&topic=fxcheck');
$methodologyHub   = JRoute::_('index.php?option=com_authors&view=methodologyhub');
$disclosureUrl    = '/affiliate-advertiser-disclosure';
$authorsUrl       = JRoute::_('index.php?option=com_authors&view=authors');

// ---- Breadcrumb -----------------------------------------------------------
$crumbHtml = '';
if (function_exists('fec_build_breadcrumb_items_from_path')
    && function_exists('fec_render_breadcrumb_html')) {
    $path       = JUri::getInstance()->getPath();
    $siteBase   = rtrim(JUri::base(true), '/');
    $crumbItems = fec_build_breadcrumb_items_from_path($path, $siteBase);
    if (!empty($crumbItems)) {
        $lastIdx = count($crumbItems) - 1;
        if (isset($crumbItems[$lastIdx]) && is_array($crumbItems[$lastIdx])) {
            foreach (array('name', 'label', 'title', 'text') as $k) {
                if (array_key_exists($k, $crumbItems[$lastIdx])) {
                    $crumbItems[$lastIdx][$k] = $cfg['title'];
                }
            }
        }
        $crumbHtml = fec_render_breadcrumb_html($crumbItems);
    }
}
?>

<?php if (!empty($this->jsonLdGraph)): ?>
<script type="application/ld+json"><?php echo $this->jsonLdGraph; ?></script>
<?php endif; ?>

<div class="fecx me-page">

<?php if ($crumbHtml !== ''): ?>
<div class="wrap"><?php echo $crumbHtml; ?></div>
<?php endif; ?>

<!-- ========================== HERO ========================== -->
<section class="au-hero">
  <div class="wrap">
    <div class="me-hero-card">
      <div class="me-hero-body">
        <div class="eyebrow">Methodology</div>
        <h1 class="me-title"><?php echo $h($cfg['title']); ?></h1>
        <?php if (!empty($cfg['tagline'])): ?>
          <p class="me-tagline"><?php echo $h($cfg['tagline']); ?></p>
        <?php endif; ?>

        <div class="me-byline">
          <?php if ($author): ?>
            <span>By
              <?php if ($authorUrl !== ''): ?>
                <a href="<?php echo $authorUrl; ?>"><b><?php echo $h($author['name']); ?></b></a>
              <?php else: ?>
                <b><?php echo $h($author['name']); ?></b>
              <?php endif; ?>
            </span>
          <?php endif; ?>
          <?php if ($reviewer): ?>
            <span class="div">·</span>
            <span>Reviewed by
              <?php if ($reviewerUrl !== ''): ?>
                <a href="<?php echo $reviewerUrl; ?>"><b><?php echo $h($reviewer['name']); ?></b></a>
              <?php else: ?>
                <b><?php echo $h($reviewer['name']); ?></b>
              <?php endif; ?>
            </span>
          <?php endif; ?>
          <?php if ($lastUpdated !== ''): ?>
            <span class="div">·</span>
            <span>Updated <b><?php echo $h($lastUpdated); ?></b></span>
          <?php endif; ?>
          <?php if ($version !== ''): ?>
            <span class="div">·</span>
            <span>Version <b><?php echo $h($version); ?></b></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Stats strip — static principles -->
    <div class="au-stats-strip">
      <div class="stat">
        <div class="num">17<small>+</small></div>
        <div class="lbl">Years online</div>
      </div>
      <div class="stat">
        <div class="num">4</div>
        <div class="lbl">Source tiers</div>
      </div>
      <div class="stat">
        <div class="num">100<small>%</small></div>
        <div class="lbl">Claims sourced</div>
      </div>
      <div class="stat">
        <div class="num">2</div>
        <div class="lbl">Eyes per review</div>
      </div>
    </div>
  </div>
</section>

<!-- ========================== MAIN ========================== -->
<section class="au-main wrap">

  <!-- ============ CONTENT ============ -->
  <article class="me-content">

    <!-- Quick verdict -->
    <div class="me-verdict">
      <div class="me-verdict-label">The principle</div>
      <p>
        FreeExtraChips is an <b>affiliate site</b>: we earn commission when a reader clicks through and signs
        up at a casino we list. We disclose this on every page. What it does <i>not</i> mean is that we
        list bonuses based on how much they pay us, or that our reviews reflect commercial pressure. The
        wall between commercial relationships and editorial decisions is the most important policy on this
        site, and this page documents how it actually works in practice.
      </p>
      <p>
        Every factual claim — RTP values, wagering requirements, country restrictions, bonus terms — is
        sourced from one of <b>four tiers</b> of evidence, with provider primary sources at the top and
        third-party aggregators excluded as primary references. Reviews go through a second editor before
        publication. Corrections are flagged at the top of pages, not silently rewritten.
      </p>
    </div>

    <h2 id="independence">Editorial independence</h2>
    <p>
      FreeExtraChips earns revenue through affiliate links. When a reader clicks a "PLAY NOW" button and
      registers at the destination casino, the casino's affiliate program pays FreeExtraChips a commission —
      typically a CPA (cost per acquisition) flat fee, sometimes a revenue share on the player's losses
      against the house edge. This is the same business model as most independent casino review sites and
      most travel sites: the publisher gets paid by the merchant, not the reader.
    </p>
    <p>
      The risk of this model is that publishers can be tempted to surface bonuses that pay the highest
      commission rather than the bonuses that are best for the reader. FreeExtraChips' separation between
      commercial and editorial functions is structural:
    </p>
    <ul>
      <li>
        <b>Casino selection is not pay-to-list.</b> Every operator in our directory was added because
        an editor decided it was worth covering — based on license validity, software providers, banking
        options, and player-reported track record. Casinos cannot purchase a listing.
      </li>
      <li>
        <b>Bonus selection is criteria-driven.</b> Bonuses appear in our database when an editor adds them
        from the operator's promotions page, not when an affiliate program suggests them to us. The criteria
        for inclusion are: the bonus is actually available, the terms are documented, and the operator is
        already in our directory.
      </li>
      <li>
        <b>Ranking is algorithmic, not commercial.</b> Rankings on category pages (e.g. "Best No Deposit
        Bonuses") use editorially-defined criteria — wagering requirement, max cashout, FXCheck™ track
        record, recency — not the commission rate of each link.
      </li>
      <li>
        <b>Sponsored content is labelled.</b> If we ever accept paid placement (we currently don't),
        it will carry a visible <code>SPONSORED</code> badge. The "EXCLUSIVE" tag on some bonuses indicates
        an exclusive negotiated deal for our readers — not paid placement; it means the offer is unavailable
        elsewhere.
      </li>
    </ul>

    <h2 id="sources">Source hierarchy</h2>
    <p>
      Every factual claim on this site — slot RTP, bonus wagering, country restrictions, payment methods —
      comes from a specific source, ranked in priority order. When tiers conflict, the higher tier wins
      and we document the disagreement.
    </p>
    <ol class="me-numbered">
      <li>
        <b>Tier 1 — Provider primary source.</b> The official game data page on the slot provider's website
        (e.g. <code>pragmaticplay.com/en/games/wolf-gold/</code>). We archive each source URL on the Wayback
        Machine when we record it, so the cited value is reproducible even if the provider later changes
        the page. This is the canonical reference for RTP, max win, volatility, paylines, and release year.
      </li>
      <li>
        <b>Tier 2 — Regulator certificate.</b> Test reports from accredited testing labs (eCOGRA, GLI, BMM,
        iTechLabs). When a regulator certificate is publicly accessible, we link to it. Most aren't —
        regulators provide them to operators under confidentiality. We document the testing lab even when
        the cert itself isn't public.
      </li>
      <li>
        <b>Tier 3 — In-game paytable observation.</b> Some providers (RTG/SpinLogic, Rival, IGT legacy
        online catalogue) don't publish public game data pages. For their slots, our editors capture
        timestamped screenshots of the in-game paytable at licensed operators. Each observation records
        the operator, the date, and the configured RTP value. Multiple observations across operators
        triangulate the truth.
      </li>
      <li>
        <b>Tier 4 — Operator bonus terms.</b> For bonus-specific facts (wagering multiplier, max cashout,
        eligible games, country restrictions), we read the actual T&C on the operator's site, not the
        promotion page summary. T&Cs trump promotional copy when they disagree.
      </li>
    </ol>
    <p>
      <b>What we don't use as primary sources:</b> aggregator sites (SlotCatalog, AskGamblers, Casino Guru),
      affiliate networks' published bonus lists, or operator marketing emails. These can be useful as
      cross-references — if SlotCatalog reports a different RTP than ours, we re-check the provider source
      to confirm — but they're not authoritative on their own.
    </p>

    <h2 id="fact-checking">Fact-checking workflow</h2>
    <p>
      Every review and methodology page passes through two editors before publication. The author writes;
      a second editor reviews. The reviewer's job isn't copy-edit (though that happens too) — it's
      <i>source verification</i>: does every numerical claim link to a primary source? Does the wagering
      math add up? Is the country restriction list current?
    </p>
    <ul>
      <li><b>Step 1 — Author writes the review.</b> Pulls bonus terms from the operator's T&C, slot data from the provider primary source, FXCheck™ history from our internal aggregation.</li>
      <li><b>Step 2 — Source pass.</b> Author cross-references every figure against the primary source one more time before submitting for review. The dashboard flags any field without an attached source URL.</li>
      <li><b>Step 3 — Peer review.</b> A second editor (assigned by specialism — RTG bonuses go to the US-facing editor, Microgaming slots to the NetEnt specialist) reads the draft, opens each cited source, and confirms the claims. The reviewer either approves or sends back with notes.</li>
      <li><b>Step 4 — Publish.</b> Approved drafts go live with both author and reviewer attributed by name on the published page.</li>
      <li><b>Step 5 — FXCheck™ activation.</b> Once published, the bonus accepts reader reports via the FXCheck™ Yes/No mechanism. Aggregated reports surface as a Verified / Mixed / Issues badge — see <a href="<?php echo $fxcheckMethodUrl; ?>">FXCheck™ methodology</a> for the algorithm.</li>
    </ul>

    <h2 id="ai-assistance">How we use AI</h2>
    <p>
      Our editors use AI tools in production, and this page says so because readers should not have to
      guess. A tool may turn a fact sheet, a set of T&amp;C clauses and a regulator register pull into a
      first draft, check a draft against the house style, or point out where a promotion page and its
      terms disagree. That is where the tool's role ends.
    </p>
    <ul>
      <li>
        <b>Every fact is verified by the editor who signs.</b> Licence numbers come from the regulator's
        register, bonus terms from the operator's T&amp;C fetched at the time of writing, withdrawal times
        from our own tests or from player-timed FXClock clocks. Nothing on a published page rests on what
        a model asserted.
      </li>
      <li>
        <b>Verdicts, flags and scores are human decisions.</b> The bottom line of a review, a flag or a
        hold, and every FXShield grade are decided by the named editor and the reviewer under the
        published methodology, never by a model.
      </li>
      <li>
        <b>Nothing is published automatically.</b> Every page goes through the two-editor workflow above
        before it goes live, and the editor who signs it answers for it.
      </li>
    </ul>
    <p>
      We do not use AI to invent author profiles, to produce portraits of people who do not exist, or to
      write reader reviews or player reports. Where a tool was used in a way that changes how a page
      should be read, the page says so.
    </p>

    <h2 id="update-cadence">Update cadence</h2>
    <p>
      Data goes stale at different rates. We track different data types on different cadences, and the
      dashboard auto-flags records that need refreshing.
    </p>
    <div class="me-status-table">
      <div class="me-status-row me-status-row--head">
        <div class="me-status-name">Data type</div>
        <div class="me-status-criteria">Refresh target</div>
        <div class="me-status-meaning">Trigger</div>
        <div class="me-status-count">Notes</div>
      </div>
      <div class="me-status-row">
        <div class="me-status-name"><b>Slot RTP data</b></div>
        <div class="me-status-criteria">180 days</div>
        <div class="me-status-meaning">Auto-flagged by RTP dashboard when <code>last_verified_date</code> &gt; 180 days. Editor re-fetches primary source + new Wayback snapshot.</div>
        <div class="me-status-count"><span class="me-muted">auto</span></div>
      </div>
      <div class="me-status-row">
        <div class="me-status-name"><b>Bonus availability</b></div>
        <div class="me-status-criteria">Continuous</div>
        <div class="me-status-meaning">FXCheck™ reports + monthly editor sweep. Operators close bonuses without notice; FXCheck usually catches it first.</div>
        <div class="me-status-count"><span class="me-muted">live</span></div>
      </div>
      <div class="me-status-row">
        <div class="me-status-name"><b>Bonus terms</b></div>
        <div class="me-status-criteria">30 days</div>
        <div class="me-status-meaning">Wagering, max cashout, code, country list. Editor sweep monthly; FXCheck "Issues" status also triggers re-check.</div>
        <div class="me-status-count"><span class="me-muted">monthly</span></div>
      </div>
      <div class="me-status-row">
        <div class="me-status-name"><b>Country restrictions</b></div>
        <div class="me-status-criteria">Within 48h</div>
        <div class="me-status-meaning">When an operator pushes a geo-change, FXCheck reports from blocked countries arrive within 24h. Editor updates within 48h.</div>
        <div class="me-status-count"><span class="me-muted">live</span></div>
      </div>
      <div class="me-status-row">
        <div class="me-status-name"><b>Casino licensing</b></div>
        <div class="me-status-criteria">90 days</div>
        <div class="me-status-meaning">License validity, regulator status, blacklist appearances. Editor re-checks operator licensing page + cross-references the regulator.</div>
        <div class="me-status-count"><span class="me-muted">quarterly</span></div>
      </div>
      <div class="me-status-row">
        <div class="me-status-name"><b>Editor profiles</b></div>
        <div class="me-status-criteria">Annual</div>
        <div class="me-status-meaning">Author bios, role assignments, specialism areas. Reviewed every year or when an editor's role changes.</div>
        <div class="me-status-count"><span class="me-muted">yearly</span></div>
      </div>
    </div>

    <h2 id="corrections">Correction policy</h2>
    <p>
      When we get something wrong, we fix it in three steps:
    </p>
    <ol>
      <li>
        <b>Update the data.</b> The incorrect value is replaced with the correct one. The dashboard logs
        the change with timestamp and the editor who made it.
      </li>
      <li>
        <b>Flag the correction visibly.</b> For factual corrections that affect a reader's decision (wrong
        wagering, wrong max cashout, missing country restriction), a <code>CORRECTED</code> note appears
        at the top of the page with the original value, the corrected value, and the date.
      </li>
      <li>
        <b>Notify if material.</b> If the correction affects a published review's headline conclusion
        (e.g. we recommended a bonus that turns out to have a hidden cap), we add an editor note to
        the review and, where the FXCheck history shows reports affected by the bad data, we tag those
        reports for context.
      </li>
    </ol>
    <p>
      Minor edits (typos, formatting, link updates) don't trigger a visible correction note — they're
      logged in the dashboard's change history but not flagged on the published page. The bar for a
      visible correction is: <i>would a reader have made a different decision if they'd seen the right
      value?</i>
    </p>
    <p>
      If you spot an error on the site, <a href="/contactus">contact us</a>. We respond to factual
      corrections within 48 hours and credit the first reporter when they provide a name.
    </p>

    <h2 id="conflict-of-interest">Conflict-of-interest policy</h2>
    <ul>
      <li>
        <b>Editors disclose financial relationships.</b> If an editor has an ownership stake, paid
        consulting relationship, or family employment connection to any operator we cover, that
        relationship is recorded internally. The editor recuses from reviewing that operator.
      </li>
      <li>
        <b>Gift acceptance is bounded.</b> Operators occasionally offer junket invitations, branded
        merchandise, or testing accounts. Branded merchandise under €50 is acceptable. Testing accounts
        used for verification are acceptable provided the editor doesn't deposit personal funds and the
        account is closed after testing. Junket invitations and any benefit over €50 require disclosure
        and a second editor's sign-off before acceptance.
      </li>
      <li>
        <b>Editors don't play at operators they review.</b> We make an exception for short-duration
        testing accounts used for verification (FXCheck testing, bonus clearance verification). For
        regular personal play, editors choose operators outside their coverage area.
      </li>
      <li>
        <b>No personal SEO/affiliate links.</b> Editors don't run their own competing affiliate sites
        on the same operators they cover at FreeExtraChips. The exclusivity is in the employment terms.
      </li>
    </ul>

    <h2 id="authors">Author credentials</h2>
    <p>
      Every review and methodology page on this site is bylined. Author attribution is at the top of the
      page, linked to a public author profile that lists the editor's specialism area, their RTP
      verification track record, and the bonuses they've reviewed.
    </p>
    <p>
      Each editor has a clearly-scoped specialism. The specialism isn't an exclusivity rule (any editor
      can write any review when needed), but reviews and verifications default to the editor whose
      specialism matches the subject:
    </p>
    <ul>
      <li><b>US-facing providers</b> (Pragmatic Play, BGaming, SpinLogic/RTG, Rival, IGT legacy) — multi-tier RTP disclosure on Pragmatic catalogue is a specialism focus.</li>
      <li><b>EU classic providers</b> (NetEnt, Microgaming) — single-tier provider verification with deep historical knowledge of the catalogue.</li>
      <li><b>Modern European providers</b> (Play'n GO, BetSoft, Yggdrasil, Habanero) — multi-tier disclosure on Play'n GO catalogue.</li>
      <li><b>Payments &amp; risk</b> — withdrawal mechanics, payment method asymmetries, crypto FX exposure, bonus clearance math.</li>
    </ul>
    <p>
      Full editor profiles, including bylined work, are at <a href="<?php echo $authorsUrl; ?>">the editorial team page</a>.
    </p>

    <h2 id="bylines">Bylines and pen names</h2>
    <p>
      Our editors publish under <b>professional pen names</b>. Every pen name corresponds to one real
      member of the editorial team, and no name is shared between people. The gambling industry is small
      and not always friendly, and our editors keep their private identities out of it; the pen name is
      how the same person stays accountable for their work over time.
    </p>
    <ul>
      <li>
        <b>Profiles state only what is true.</b> Each profile describes the editor's actual beat and
        verification work: what they cover, what they check, and the bylined record behind it. No
        invented credentials, no social profiles created to support a byline. Portraits are stylised
        renderings of the editors themselves, not stock images or invented faces.
      </li>
      <li>
        <b>One name, one person.</b> A pen name is never shared, and the person behind it reviews what
        carries their signature. Reviewer attribution on a page means that second editor read the draft
        and opened the sources.
      </li>
      <li>
        <b>The publisher answers for the content.</b> Editorial responsibility for everything published
        here rests with FreeExtraChips. Corrections follow the <a href="#corrections">correction
        policy</a> above and are recorded on the page concerned.
      </li>
      <li>
        <b>Contributors, same rules.</b> Regular outside contributors, such as the writers of the Casino
        Bonus Blog, publish under pen names on the same terms: one real person per name, a profile that
        states only what is true, and the publisher answering for what is published. Their profiles say
        that they are contributors rather than members of the editorial desk.
      </li>
    </ul>

    <h2 id="faq">FAQ</h2>
    <div class="me-faq">
      <details open>
        <summary>Do you get paid more to recommend certain casinos?</summary>
        <p>
          Commission rates vary across operators we list — some pay flat CPA, others pay revenue share,
          and the amounts differ. Our ranking and selection logic deliberately ignores commission rates.
          The rankings on category pages use FXCheck™ track record, wagering math, and license validity
          — not what each click is worth to us.
        </p>
      </details>

      <details>
        <summary>How do you decide which casinos to add to the directory?</summary>
        <p>
          We add operators based on: license validity (regulator-issued, verifiable), software providers
          (at least one named provider in the lobby), banking options (real payment processors, not
          obscure-only), and editorial judgment that the operator is worth covering. We decline operators
          on regulator blacklists, those with unresolved payment disputes documented elsewhere, and those
          we can't verify the licensing chain for.
        </p>
      </details>

      <details>
        <summary>Why don't you cite SlotCatalog or AskGamblers for RTP values?</summary>
        <p>
          Aggregators are downstream of the same primary sources we use — and they're not always current.
          When we cite an RTP, we want the citation to lead a reader back to the provider's own published
          value, archived for accountability. If we used an aggregator and the aggregator was wrong, the
          mistake propagates. See <a href="<?php echo $rtpMethodUrl; ?>">RTP methodology</a> for the
          source tier order in practice.
        </p>
      </details>

      <details>
        <summary>What happens when an editor reviews a bonus they personally claimed?</summary>
        <p>
          Editors testing a bonus for FXCheck verification disclose the test internally and don't claim
          the bonus through their own readership link. Test accounts are closed after verification.
          Personal play at operators an editor covers is discouraged in the employment terms.
        </p>
      </details>

      <details>
        <summary>How do I report an error or contest a review?</summary>
        <p>
          Use the <a href="/contactus">contact form</a> or email the address listed there. Factual
          corrections (wrong wagering, wrong country list, broken link) get a 48-hour response. Disputed
          editorial judgments (we said a bonus was bad, the operator disagrees) go to the reviewing
          editor; if the operator presents new evidence, we update the review with a visible correction
          note.
        </p>
      </details>

      <details>
        <summary>Do you use AI to write reviews?</summary>
        <p>
          AI tools help our editors draft and check; they do not decide what we publish and they verify
          nothing on their own. Every licence, term and figure on a page is checked against its primary
          source by the editor who signs it, and verdicts, flags and scores are human decisions. See
          <a href="#ai-assistance">How we use AI</a>.
        </p>
      </details>

      <details>
        <summary>Do you have a sponsored content policy?</summary>
        <p>
          We don't currently accept paid placement. If that changes, sponsored content will be marked
          with a visible <code>SPONSORED</code> badge at the top of the page and excluded from algorithmic
          rankings on category pages.
        </p>
      </details>

      <details>
        <summary>Why is this page necessary?</summary>
        <p>
          The casino affiliate industry has a documented credibility problem: many sites publish reviews
          that don't reflect actual verification, ranking driven by commission rates rather than reader
          value, and undisclosed conflicts. Operators and regulators benefit from a clear public statement
          of which sites operate to standards and which don't. Readers benefit from being able to check
          our work. This page is the standard we hold ourselves to — and the criteria you can use to
          push back if we miss it.
        </p>
      </details>
    </div>

  </article>

  <!-- ============ SIDEBAR ============ -->
  <aside class="au-sidebar" aria-label="On this page">

    <div class="au-side-card me-toc">
      <div class="me-toc-label">On this page</div>
      <ul>
        <li><a href="#independence">Editorial independence</a></li>
        <li><a href="#sources">Source hierarchy</a></li>
        <li><a href="#fact-checking">Fact-checking workflow</a></li>
        <li><a href="#ai-assistance">How we use AI</a></li>
        <li><a href="#update-cadence">Update cadence</a></li>
        <li><a href="#corrections">Correction policy</a></li>
        <li><a href="#conflict-of-interest">Conflict-of-interest policy</a></li>
        <li><a href="#authors">Author credentials</a></li>
        <li><a href="#bylines">Bylines and pen names</a></li>
        <li><a href="#faq">FAQ</a></li>
      </ul>
    </div>

    <div class="au-side-card">
      <div class="me-toc-label">Related</div>
      <ul class="me-side-list">
        <li><a href="<?php echo $rtpMethodUrl; ?>">RTP verification methodology</a></li>
        <li><a href="<?php echo $fxcheckMethodUrl; ?>">FXCheck™ methodology</a></li>
        <li><a href="<?php echo $disclosureUrl; ?>">Affiliate disclosure</a></li>
        <li><a href="<?php echo $methodologyHub; ?>">All methodologies</a></li>
      </ul>
    </div>

    <div class="au-side-cta">
      <div class="me-cta-label">Spot an error?</div>
      <p>
        Every claim on this site is sourced. If a value looks wrong, mail us — we acknowledge within
        48 hours and document every correction.
      </p>
      <a class="me-cta-btn" href="mailto:editorial@freeextrachips.com?subject=Editorial%20correction">
        Email the editors
      </a>
    </div>

  </aside>

</section>

</div>
