<?php
defined('_JEXEC') or die;

/**
 * Affiliate &amp; Advertiser Disclosure — FreeExtraChips.
 *
 * Editorial rules baked into this template:
 *   - Specific about the business model (CPA / RevShare, not pay-per-click).
 *   - Distinguishes editorial firewall from commercial relationships and
 *     links to the methodology pages that operationalize that firewall.
 *   - Includes a "what does NOT affect rankings" section — the inverse list
 *     is often more reassuring than the positive criteria.
 *   - Versioning history at the bottom so changes are auditable.
 *
 * Layout reuses .au-* and .rg-* primitives. Where the responsible-gambling
 * page added .rg-helpline cards, this page reuses .rg-tools-grid for the
 * criteria cards and .rg-signs for the "what does NOT affect" list.
 */

$cfg     = $this->config;
$updated = isset($cfg['last_updated']) ? (string)$cfg['last_updated'] : '';
$updatedTxt = '';
if ($updated !== '') {
    try {
        $dt = new DateTime($updated);
        $updatedTxt = $dt->format('j F Y');
    } catch (Exception $e) {
        $updatedTxt = $updated;
    }
}

$author   = $this->author;
$reviewer = $this->reviewer;

$authorUrl   = $author   ? JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($author['slug']))   : '';
$reviewerUrl = $reviewer ? JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($reviewer['slug'])) : '';
?>
<div class="fecx rg-page" id="ad-top">

<?php // -- HERO ---------------------------------------------------------- ?>
<section class="au-hero rg-hero">
    <div class="wrap">
        <div class="eyebrow">Editorial &middot; Business transparency</div>
        <h1 class="au-hero-title">Affiliate &amp; Advertiser Disclosure</h1>
        <p class="au-hero-lede"><?php echo htmlspecialchars($cfg['tagline'], ENT_QUOTES, 'UTF-8'); ?></p>

        <div class="rg-hero-meta">
            <?php if ($author): ?>
                <div class="rg-hero-byline">
                    <span class="rg-bl-label">Written by</span>
                    <a href="<?php echo htmlspecialchars($authorUrl, ENT_QUOTES, 'UTF-8'); ?>" rel="author"><?php echo htmlspecialchars($author['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php if (!empty($author['role'])): ?>
                        <span class="rg-bl-role"><?php echo htmlspecialchars($author['role'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($reviewer): ?>
                <div class="rg-hero-byline">
                    <span class="rg-bl-label">Reviewed by</span>
                    <a href="<?php echo htmlspecialchars($reviewerUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($reviewer['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php if (!empty($reviewer['role'])): ?>
                        <span class="rg-bl-role"><?php echo htmlspecialchars($reviewer['role'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($updatedTxt !== ''): ?>
                <div class="rg-hero-updated">
                    <span class="rg-bl-label">Last reviewed</span>
                    <time datetime="<?php echo htmlspecialchars($updated, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($updatedTxt, ENT_QUOTES, 'UTF-8'); ?></time>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php // -- MAIN GRID (content + sidebar) -------------------------------- ?>
<div class="wrap au-main rg-main">

    <article class="au-content rg-content">

        <?php // -- Intro ?>
        <section id="intro" class="rg-section">
            <p class="rg-lede">FreeExtraChips has been publishing information about online casino bonuses since 2010. We are an independent editorial site, not a casino operator, and the cost of running the site &mdash; bonus verification, content updates, hosting, and the editorial team &mdash; is covered through affiliate marketing relationships with licensed gambling operators.</p>

            <p>This disclosure explains the mechanics of those relationships specifically: <strong>how we earn revenue, what we will and will not do in exchange for that revenue, and how readers can verify that our editorial process is independent of commercial pressure.</strong> The point of the page is operational transparency, not boilerplate.</p>

            <p>If you have questions this page doesn't answer, email <a href="mailto:editorial@freeextrachips.com">editorial@freeextrachips.com</a>. We respond to every editorial correspondence within five business days.</p>
        </section>

        <?php // -- How we make money ?>
        <section id="business-model" class="rg-section">
            <h2>How FreeExtraChips makes money</h2>
            <p>Our entire revenue comes from <strong>affiliate marketing programs</strong> operated by online casinos. When a reader follows an affiliate link from FreeExtraChips to a casino and later takes a qualifying action on that casino's site, we earn a commission. We do not run display advertising, we do not sell user data, and we do not charge readers for any feature on the site.</p>

            <p>Two compensation models are common in the iGaming affiliate market, and both apply to different relationships in our portfolio:</p>

            <div class="rg-tools-grid">
                <div class="rg-tool">
                    <h3>CPA (Cost Per Acquisition)</h3>
                    <p>A one-time fixed payment from the operator when a referred player completes a qualifying first deposit. Once paid, the relationship ends &mdash; we receive nothing from that player's subsequent activity, whether they win or lose.</p>
                </div>
                <div class="rg-tool">
                    <h3>Revenue Share</h3>
                    <p>An ongoing percentage of the operator's <em>net revenue</em> from referred players. We share in the casino's margin over time, which means we earn more when players lose and less (or nothing) when they win. This model has clear ethical implications, which we address in the editorial firewall section below.</p>
                </div>
            </div>

            <p><strong>What we do not do:</strong> we are not paid per click, per impression, per page view, or per outbound link follow. A reader who never registers and never deposits generates zero revenue for us, regardless of how many bonus pages they read. This affects how we design pages &mdash; we have no incentive to maximize ad density or session length, only to surface bonuses that readers find useful enough to claim.</p>
        </section>

        <?php // -- Editorial firewall ?>
        <section id="editorial-firewall" class="rg-section">
            <h2>The editorial firewall</h2>
            <p>The editorial process and the commercial process are separated by design. Three operational guarantees make that separation concrete rather than rhetorical:</p>

            <ol class="rg-steps">
                <li><strong>Bonus rankings are not negotiable.</strong> Operators cannot pay for higher placement in our listings. The signals that determine sort order are documented on our <a href="<?php echo JRoute::_('index.php?option=com_authors&view=methodologyhub'); ?>">How We Verify</a> hub and are applied uniformly across all operators, including the ones who pay us the most.</li>
                <li><strong>FXCheck is independent of commission.</strong> The player-report verification system that powers bonus status (Verified, Mixed, Likely expired) runs on real player feedback collected through public forms. Operators cannot suppress negative reports, request status changes, or influence the thresholds. Full methodology on <a href="<?php echo JRoute::_('index.php?option=com_authors&view=methodology&topic=fxcheck'); ?>">/methodology/fxcheck</a>.</li>
                <li><strong>Editorial decisions are made before commercial ones.</strong> When a new operator becomes available, an editorial review precedes any affiliate negotiation. If the review finds disqualifying issues (license problems, payment complaints, structurally predatory terms), the operator is not added to our portfolio &mdash; even if the proposed commission is attractive.</li>
            </ol>

            <div class="rg-hero-pledge" style="margin-top: 18px;">
                <strong>The honest framing on RevShare:</strong> revenue share models do create a mathematical incentive for operators to retain losing players. We mitigate this by prioritizing CPA relationships where possible, by giving equal editorial weight to wagering math and cashout caps (the variables that protect players from losses), and by surfacing FXCheck signals prominently so readers see real-world outcomes rather than promotional language.
            </div>
        </section>

        <?php // -- What affects rankings ?>
        <section id="ranking-criteria" class="rg-section">
            <h2>What affects bonus rankings</h2>
            <p>The order in which bonuses appear on FreeExtraChips listing pages is determined by editorial criteria, applied in this rough priority:</p>

            <ul class="rg-signs">
                <li><strong>Wagering structure</strong> &mdash; lower multipliers rank above higher. Bonus-only wagering (b) ranks above bonus+deposit (b+d) at equivalent multipliers.</li>
                <li><strong>Cashout terms</strong> &mdash; offers with higher or uncapped cashout limits rank above offers with restrictive caps. Cashable bonuses rank above non-cashable (sticky) bonuses.</li>
                <li><strong>Game flexibility and RTP</strong> &mdash; offers playable on high-RTP slots or across multiple game types rank above single-slot restrictions on lower-RTP titles.</li>
                <li><strong>FXCheck signal</strong> &mdash; bonuses with Verified status (real player reports confirming they paid out as advertised) rank above Low data, which rank above Mixed, which rank above Likely expired.</li>
                <li><strong>Country availability and license validity</strong> &mdash; offers available to the reader's country and held under a current, verifiable license rank above geo-restricted or expired-license offers.</li>
                <li><strong>Operator track record</strong> &mdash; consistent payment history, responsive support, and absence of unresolved complaints on neutral forums.</li>
            </ul>

            <p>These criteria are documented in detail on our <a href="<?php echo JRoute::_('index.php?option=com_authors&view=methodologyhub'); ?>">methodology hub</a>. The criteria precede the affiliate relationship: a bonus that ranks well does so because its terms are favorable, not because the operator pays a higher commission.</p>
        </section>

        <?php // -- What does NOT affect rankings ?>
        <section id="not-ranking" class="rg-section">
            <h2>What does <em>not</em> affect rankings</h2>
            <p>The inverse list is often more reassuring than the positive one. The following factors are explicitly excluded from the ranking algorithm:</p>

            <ul class="rg-family-tips">
                <li><strong>Commission rate</strong>. An operator paying us €500 CPA does not rank higher than an operator paying €100 CPA, all else equal. Commission is a function of the operator's marketing budget, not the quality of their offer.</li>
                <li><strong>Length of partnership</strong>. Long-standing affiliate relationships do not earn ranking weight. New operators with strong terms can outrank established partners with weaker terms.</li>
                <li><strong>Exclusive offers</strong>. We sometimes negotiate exclusive bonus codes for FreeExtraChips readers. These are flagged as exclusive in the listing but do not receive a ranking boost &mdash; they're ranked alongside non-exclusive offers using the same criteria.</li>
                <li><strong>Operator size or brand recognition</strong>. A large branded casino with mediocre wagering terms ranks below a smaller operator with better terms.</li>
                <li><strong>Frequency of contact from the operator</strong>. Affiliate managers requesting promotion are politely told the answer depends on the offer's editorial fit, not on the request.</li>
            </ul>
        </section>

        <?php // -- When we don't promote ?>
        <section id="exclusions" class="rg-section">
            <h2>When we refuse to promote an offer</h2>
            <p>Some operators and offer structures are excluded from our portfolio regardless of commercial considerations. The exclusions are operational policy, not editorial preferences:</p>

            <ul class="rg-signs">
                <li><strong>Expired or unverifiable license</strong>. Operators advertising only old Cura&ccedil;ao sublicenses (Antillephone, Cura&ccedil;ao eGaming, Gaming Cura&ccedil;ao) that expired in January 2025 are not listed until they migrate to a direct CGA license or move to another valid jurisdiction.</li>
                <li><strong>Unresolved payment complaints pattern</strong>. Operators with documented multi-source patterns of denied legitimate withdrawals are removed from our portfolio. Single incidents are investigated; patterns end the relationship.</li>
                <li><strong>Retroactive term-change clauses</strong>. Operators whose bonus T&amp;Cs allow them to modify terms after a player has claimed the bonus are flagged as structurally unsafe and downgraded or removed.</li>
                <li><strong>Misleading bonus structures</strong>. Headline bonuses with structural ceilings that materially contradict the marketing claim (e.g. "up to $5,000" with a $100 cashout cap, marketed in a way that conceals the cap) are not added to the portfolio.</li>
                <li><strong>Predatory targeting</strong>. Operators using marketing language designed to target self-excluded players, players in recovery, or readers in financial distress.</li>
            </ul>

            <p>When an operator is removed from our portfolio, the existing bonus listings are not silently deleted &mdash; they are marked as closed or blacklisted (visible via the <code>jr_closed</code> / <code>jr_blacklisted</code> status fields), and the reason for the change is documented internally.</p>
        </section>

        <?php // -- Accuracy ?>
        <section id="accuracy" class="rg-section">
            <h2>Accuracy and changes</h2>
            <p>Bonus terms change frequently. Operators modify wagering requirements, cashout caps, eligible games, and country availability without notifying affiliate sites in advance. We make reasonable efforts to keep bonus information current, but at any given moment <strong>the authoritative source is the casino's own terms page</strong>.</p>

            <p>Before claiming any bonus, read the official terms on the operator's site. If you find a discrepancy between our listing and the operator's current terms, please report it to <a href="mailto:editorial@freeextrachips.com">editorial@freeextrachips.com</a> &mdash; corrections are prioritized over new content.</p>
        </section>

        <?php // -- What we are not ?>
        <section id="what-we-are-not" class="rg-section">
            <h2>What FreeExtraChips is not</h2>
            <ul class="rg-family-tips">
                <li><strong>Not a gambling operator.</strong> We do not host gambling, accept deposits, process payments, or provide gambling products. Any interaction with a casino is solely between the reader and that operator.</li>
                <li><strong>Not legal or financial advice.</strong> Information on this site is for general informational purposes. Gambling legality varies by jurisdiction; verify your local law before playing.</li>
                <li><strong>Not responsible for third-party outcomes.</strong> We are not responsible for losses, disputes, account issues, or any other consequences arising from gambling at third-party sites. We do help readers escalate documented complaints where possible (contact editorial), but we have no operational authority over operators.</li>
                <li><strong>Not for minors.</strong> Online gambling is restricted to adults (typically 18+ or 21+ depending on jurisdiction). This site and the offers it lists are not directed at minors. If you are under the legal gambling age in your country, you should not use any service listed here.</li>
            </ul>

            <p>For guidance on responsible gambling, the warning signs of problem gambling, and country-specific helplines, see our <a href="<?php echo JRoute::_('index.php?option=com_authors&view=page&slug=responsible-gambling'); ?>">Responsible Gambling</a> page.</p>
        </section>

        <?php // -- Contact ?>
        <section id="contact" class="rg-section">
            <h2>Contact and corrections</h2>
            <p>Questions about this disclosure, our editorial process, or specific bonus listings:</p>
            <ul class="rg-family-tips">
                <li><strong>Editorial corrections</strong> (outdated bonuses, wrong wagering numbers, broken links) &mdash; <a href="mailto:editorial@freeextrachips.com">editorial@freeextrachips.com</a></li>
                <li><strong>Business and partnership inquiries</strong> &mdash; <a href="mailto:proposals@freeextrachips.com">proposals@freeextrachips.com</a></li>
                <li><strong>General contact</strong> &mdash; <a href="mailto:webmaster@freeextrachips.com">webmaster@freeextrachips.com</a></li>
            </ul>
            <p>We respond to all editorial correspondence within five business days. Partnership inquiries receive a response only if the operator passes the exclusion screening described above.</p>
        </section>

        <?php // -- Versioning ?>
        <section id="versioning" class="rg-section">
            <h2>Versioning history</h2>
            <p>Changes to this disclosure are tracked here so readers can see how our position has evolved. The current version was last reviewed on <strong><?php echo htmlspecialchars($updatedTxt, ENT_QUOTES, 'UTF-8'); ?></strong>.</p>

            <ul class="rg-family-tips">
                <li><strong>May 2026</strong> &mdash; expanded into editorial format. Added specific business model (CPA vs RevShare), editorial firewall guarantees with links to methodology, "what does not affect rankings" inverse list, and operator exclusion criteria. Linked to FXCheck and FRank methodology pages.</li>
                <li><strong>January 2026</strong> &mdash; initial standalone disclosure page. Replaced legacy disclosure paragraph in site footer with dedicated URL.</li>
            </ul>
        </section>

    </article>

    <?php // -- SIDEBAR ----------------------------------------------- ?>
    <aside class="au-side rg-side" aria-label="On this page">
        <div class="au-side-card rg-toc">
            <div class="eyebrow">On this page</div>
            <ul>
                <li><a href="#intro">Overview</a></li>
                <li><a href="#business-model">How we make money</a></li>
                <li><a href="#editorial-firewall">Editorial firewall</a></li>
                <li><a href="#ranking-criteria">What affects rankings</a></li>
                <li><a href="#not-ranking">What does <em>not</em></a></li>
                <li><a href="#exclusions">When we refuse</a></li>
                <li><a href="#accuracy">Accuracy &amp; changes</a></li>
                <li><a href="#what-we-are-not">What we are not</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="#versioning">Versioning</a></li>
            </ul>
        </div>

        <?php if ($reviewer): ?>
            <div class="au-side-card rg-reviewer-card">
                <div class="eyebrow">Reviewed by</div>
                <a href="<?php echo htmlspecialchars($reviewerUrl, ENT_QUOTES, 'UTF-8'); ?>" class="rg-rc-link">
                    <?php if (!empty($reviewer['avatar_url'])): ?>
                        <img src="<?php echo htmlspecialchars($reviewer['avatar_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="" class="rg-rc-avatar">
                    <?php endif; ?>
                    <div class="rg-rc-body">
                        <div class="rg-rc-name"><?php echo htmlspecialchars($reviewer['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if (!empty($reviewer['role'])): ?>
                            <div class="rg-rc-role"><?php echo htmlspecialchars($reviewer['role'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php if (!empty($reviewer['short_bio'])): ?>
                    <p class="rg-rc-bio"><?php echo htmlspecialchars($reviewer['short_bio'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="au-side-card rg-disclosure">
            <div class="eyebrow">Related pages</div>
            <p style="margin-top: 8px;"><a href="<?php echo JRoute::_('index.php?option=com_authors&view=methodologyhub'); ?>">How we verify bonuses</a> &mdash; the methodology behind ranking criteria.</p>
            <p style="margin-top: 8px;"><a href="<?php echo JRoute::_('index.php?option=com_authors&view=page&slug=responsible-gambling'); ?>">Responsible Gambling</a> &mdash; warning signs and helplines.</p>
        </div>
    </aside>

</div>

<?php // -- Footer byline ----------------------------------------------- ?>
<div class="wrap rg-footer-byline">
    <div class="hub-byline">
        <?php if ($author): ?>
            <div class="person">
                <span class="who">Written by</span>
                <span class="name"><a href="<?php echo htmlspecialchars($authorUrl, ENT_QUOTES, 'UTF-8'); ?>" rel="author"><?php echo htmlspecialchars($author['name'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                <?php if (!empty($author['role'])): ?>
                    <span class="role"><?php echo htmlspecialchars($author['role'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($reviewer): ?>
            <div class="person">
                <span class="who">Reviewed by</span>
                <span class="name"><a href="<?php echo htmlspecialchars($reviewerUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($reviewer['name'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                <?php if (!empty($reviewer['role'])): ?>
                    <span class="role"><?php echo htmlspecialchars($reviewer['role'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($updatedTxt !== ''): ?>
            <div class="updated">Last reviewed: <time datetime="<?php echo htmlspecialchars($updated, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($updatedTxt, ENT_QUOTES, 'UTF-8'); ?></time></div>
        <?php endif; ?>
    </div>
</div>

<?php // -- JSON-LD ?>
<?php if ($this->jsonLdGraph !== ''): ?>
<script type="application/ld+json"><?php echo $this->jsonLdGraph; ?></script>
<?php endif; ?>

</div>
