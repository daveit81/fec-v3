<?php
defined('_JEXEC') or die;

/**
 * Privacy Policy — FreeExtraChips.
 *
 * Editorial rules baked into this template:
 *   - Describes the site's ACTUAL data flows (newsletter, signed click
 *     tracking, GA4, Cloudflare, affiliate outbound links) — not CMS
 *     boilerplate. If a system changes, update this page in the same deploy.
 *   - Honest about the affiliate reporting we receive (pseudonymous player
 *     IDs from operators, never names) — consistent with the Affiliate
 *     Disclosure page.
 *   - No invented retention periods or certifications: criteria-based
 *     statements only.
 *   - Versioning note at the bottom so changes are auditable.
 *
 * Layout reuses the .au-* / .rg-* primitives from the other trust pages.
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
<div class="fecx rg-page" id="pp-top">

<?php // -- HERO ---------------------------------------------------------- ?>
<section class="au-hero rg-hero">
    <div class="wrap">
        <div class="eyebrow">Editorial &middot; Data transparency</div>
        <h1 class="au-hero-title">Privacy Policy</h1>
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

<div class="wrap au-main rg-main">

    <div class="rg-content">

        <?php // -- Intro ?>
        <section id="intro" class="rg-section">
            <p class="rg-lede">FreeExtraChips is an independent editorial site about online casino bonuses. We are not a casino, we do not take deposits, and we do not process gambling transactions. The personal data we handle is limited to what an editorial site needs: making the site work, understanding how it is used, and running the newsletter readers ask for.</p>

            <p>This page describes <strong>what we actually collect, why, who processes it on our behalf, and the choices you have</strong>. It is written to match the real systems running on this site &mdash; if a data flow is not described here, we do not operate it. In particular: we run no display advertising networks, we do not sell or rent personal data, and we have no data-broker relationships.</p>

            <p>Questions about anything on this page: <a href="mailto:privacy@freeextrachips.com">privacy@freeextrachips.com</a>.</p>
        </section>

        <?php // -- What we collect ?>
        <section id="what-we-collect" class="rg-section">
            <h2>What we collect, and why</h2>

            <div class="rg-tools-grid">
                <div class="rg-tool">
                    <h3>Browsing the site</h3>
                    <p>Standard technical data: IP address, browser and device information, pages requested, and referring page. This arrives in ordinary server logs and in our content-delivery layer (Cloudflare), which uses it for security filtering, caching, and deriving an approximate country. We use it to keep the site online, block abuse, and understand aggregate traffic. It is not used to build individual profiles.</p>
                </div>
                <div class="rg-tool">
                    <h3>Analytics</h3>
                    <p>We use Google Analytics&nbsp;4 to measure how the site is used: pages viewed, approximate location (country/region level), device type, and how visitors arrive. Analytics data reaches us in aggregate form &mdash; we do not attempt to re-identify individual visitors from it.</p>
                </div>
                <div class="rg-tool">
                    <h3>Newsletter</h3>
                    <p>If you subscribe, we store your email address, the date of subscription, and the country segment derived at signup (from the network-level country of your connection), which we use to show offers relevant to where you are. Newsletter emails contain links that pass through our redirect: when you click one, we record which link was clicked and when. We use this to understand which content is useful and to keep the newsletter worth reading.</p>
                </div>
                <div class="rg-tool">
                    <h3>Reader submissions</h3>
                    <p>When you submit a report or contact the editorial team, we receive what you send us (report content, your message, and the address you write from). Please do not include personal or account details in public bonus reports &mdash; they are not needed for verification.</p>
                </div>
            </div>
        </section>

        <?php // -- Cookies ?>
        <section id="cookies" class="rg-section">
            <h2>Cookies</h2>
            <p>The site sets a small number of cookies, in three groups:</p>
            <ol class="rg-steps">
                <li><strong>Strictly necessary.</strong> The session cookie set by the site platform, and the security cookies set by Cloudflare to distinguish legitimate visitors from automated abuse. These are required for the site to function.</li>
                <li><strong>Analytics.</strong> Google Analytics&nbsp;4 cookies used for aggregate usage measurement, as described above.</li>
                <li><strong>Nothing else.</strong> We set no advertising cookies, no cross-site tracking pixels, and no social-network trackers. There is no display advertising on FreeExtraChips.</li>
            </ol>
            <p>You can block or delete cookies in your browser settings at any time; the site remains readable with analytics cookies blocked.</p>
        </section>

        <?php // -- Affiliate links ?>
        <section id="affiliate-links" class="rg-section">
            <h2>Affiliate links and what operators report back</h2>
            <p>FreeExtraChips is funded by affiliate relationships with licensed gambling operators &mdash; the full mechanics are on the <a href="<?php echo JRoute::_('index.php?option=com_authors&view=page&slug=affiliate-disclosure'); ?>">Affiliate &amp; Advertiser Disclosure</a> page. From a privacy standpoint, two things matter:</p>
            <p><strong>Outbound clicks.</strong> When you follow an affiliate link, you leave this site and arrive at the operator's site with a referral tag identifying FreeExtraChips as the source. From that moment, the operator's own privacy policy governs what happens to your data. We encourage reading it &mdash; operators collect far more than an editorial site does.</p>
            <p><strong>What we receive back.</strong> Affiliate programs report referred-player activity to us using operator-assigned pseudonymous identifiers &mdash; never names, emails, or contact details. We can see that "a referred player" registered or deposited; we cannot see who that player is, and we do not attempt to link that reporting to newsletter subscribers or site visitors.</p>
        </section>

        <?php // -- Processors ?>
        <section id="processors" class="rg-section">
            <h2>Who processes data on our behalf</h2>
            <p>We use a small set of infrastructure providers, each processing only what its function requires: our hosting provider (server logs, site database including newsletter subscriptions), Cloudflare (content delivery, security filtering, country derivation), and Google (Analytics). Where these providers process data outside the European Economic Area, transfers rely on the safeguards those providers maintain for their services, such as standard contractual clauses.</p>
            <p>We do not sell personal data to anyone, and no third party is permitted to use data we control for its own marketing.</p>
        </section>

        <?php // -- Retention ?>
        <section id="retention" class="rg-section">
            <h2>How long we keep data</h2>
            <p>Retention follows the purpose, not a vault mentality: server and security logs rotate on the short cycles normal for infrastructure operation; analytics data is retained on the schedule configured in Google Analytics; newsletter data is kept while your subscription is active. When you unsubscribe, your address stops receiving mail immediately and is removed from active mailing data, with only what is needed to honour the unsubscription retained.</p>
        </section>

        <?php // -- Your rights ?>
        <section id="your-rights" class="rg-section">
            <h2>Your choices and rights</h2>
            <ol class="rg-steps">
                <li><strong>Newsletter:</strong> every email contains an unsubscribe link, effective immediately. No account, no retention tricks, no "are you sure" funnels.</li>
                <li><strong>Cookies and analytics:</strong> block them in your browser; the editorial content works without them.</li>
                <li><strong>Access, correction, deletion:</strong> you can ask what data we hold about you, have it corrected, or have it deleted. Where EU/EEA data-protection law (GDPR) applies to you, this includes the rights of access, rectification, erasure, restriction, portability, and objection, and the right to complain to your supervisory authority.</li>
            </ol>
            <p>To exercise any of these, email <a href="mailto:privacy@freeextrachips.com">privacy@freeextrachips.com</a>. We answer identity-verified requests within the timeframes the applicable law sets.</p>
        </section>

        <?php // -- Age ?>
        <section id="age" class="rg-section">
            <h2>Age</h2>
            <p>FreeExtraChips covers gambling-related content and is intended for adults of legal gambling age only (18+, or higher where local law requires). We do not knowingly collect personal data from minors; if you believe a minor has subscribed to the newsletter, contact us and we will remove the address.</p>
        </section>

        <?php // -- Changes ?>
        <section id="changes" class="rg-section">
            <h2>Changes to this policy</h2>
            <p>When a data flow changes &mdash; a new tool, a removed one, a changed provider &mdash; this page is updated in the same deployment, and the "last reviewed" date above reflects it. Material changes affecting newsletter subscribers are announced in the newsletter itself.</p>
        </section>

    </div>

    <?php // -- ASIDE ------------------------------------------------------ ?>
    <aside class="au-side rg-side" aria-label="On this page">
        <div class="au-side-card rg-toc">
            <div class="eyebrow">On this page</div>
            <ul>
                <li><a href="#what-we-collect">What we collect</a></li>
                <li><a href="#cookies">Cookies</a></li>
                <li><a href="#affiliate-links">Affiliate links</a></li>
                <li><a href="#processors">Processors</a></li>
                <li><a href="#retention">Retention</a></li>
                <li><a href="#your-rights">Your rights</a></li>
                <li><a href="#age">Age</a></li>
                <li><a href="#changes">Changes</a></li>
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
            <p style="margin-top: 8px;"><a href="<?php echo JRoute::_('index.php?option=com_authors&view=page&slug=affiliate-disclosure'); ?>">Affiliate &amp; Advertiser Disclosure</a> &mdash; how the site is funded.</p>
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
