<?php
defined('_JEXEC') or die;

/**
 * Responsible Gambling — public-service page for FreeExtraChips.
 *
 * Editorial rules baked into this template (do not remove without review):
 *   - NO affiliate links anywhere. Trust signal for YMYL.
 *   - Crisis banner sits ABOVE everything else, even before the hero, so
 *     users in acute distress see the path forward immediately.
 *   - Helpline numbers are hard-coded values verified against the issuing
 *     authority on the page last_updated date. When updating, re-verify
 *     each number with the source — do not propagate from memory.
 *   - Self-check is brief and not diagnostic. Source attribution is shown.
 *
 * Layout reuses .au-* primitives from fec-edition.css and adds .rg-* patterns
 * specific to this page (defined in assets/page.css).
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
<div class="fecx rg-page" id="rg-top">

<?php // -- CRISIS BANNER --------------------------------------------------- ?>
<aside class="rg-crisis" role="region" aria-label="Crisis support">
    <div class="wrap">
        <div class="rg-crisis-inner">
            <div class="rg-crisis-icon" aria-hidden="true">!</div>
            <div class="rg-crisis-body">
                <strong>In crisis right now?</strong>
                If you are thinking about harming yourself or feel unable to stay safe, contact a crisis line immediately:
                <ul class="rg-crisis-lines">
                    <li><span class="rg-cc">US&nbsp;/&nbsp;Canada</span> Call or text <a href="tel:988"><strong>988</strong></a> &mdash; Suicide &amp; Crisis Lifeline (24/7)</li>
                    <li><span class="rg-cc">UK</span> <a href="tel:116123"><strong>116&nbsp;123</strong></a> &mdash; Samaritans (24/7, free)</li>
                    <li><span class="rg-cc">Australia</span> <a href="tel:131114"><strong>13&nbsp;11&nbsp;14</strong></a> &mdash; Lifeline (24/7)</li>
                    <li><span class="rg-cc">Italia</span> <a href="tel:800860022"><strong>800&nbsp;86&nbsp;00&nbsp;22</strong></a> &mdash; Telefono Amico (lun-ven 10-24, sab-dom 14-22)</li>
                    <li><span class="rg-cc">EU</span> <a href="tel:116123"><strong>116&nbsp;123</strong></a> &mdash; Emotional support helpline (varies by country)</li>
                </ul>
                <p class="rg-crisis-note">If you are in immediate physical danger, call your local emergency number (911 / 999 / 112 / 000 / 113).</p>
            </div>
        </div>
    </div>
</aside>

<?php // -- HERO ---------------------------------------------------------- ?>
<section class="au-hero rg-hero">
    <div class="wrap">
        <div class="eyebrow">Public service &middot; Independent of advertising</div>
        <h1 class="au-hero-title"><?php echo htmlspecialchars($cfg['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
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

        <div class="rg-hero-pledge">
            <strong>Our pledge:</strong> this page contains <em>no affiliate links</em> and is not monetized. Helplines and resources listed here are independent of any commercial relationship.
        </div>
    </div>
</section>

<?php // -- MAIN GRID (content + sidebar TOC) -------------------------------- ?>
<div class="wrap au-main rg-main">

    <article class="au-content rg-content">

        <?php // -- Intro ?>
        <section id="intro" class="rg-section">
            <p class="rg-lede">Online casino gambling can be a form of entertainment for many people, played responsibly and within clear limits. For others, it can escalate into patterns that damage finances, relationships, work, and mental health. The transition rarely happens overnight, and the warning signs are often invisible to the person experiencing them.</p>

            <p>This page is for anyone who has questions about their own gambling, or about someone they care about. The aim is practical: <strong>recognize the problem early, know what to do next, and find the right support quickly.</strong> Nothing on this page is diagnostic. If you are unsure, the helplines listed below offer free, confidential conversations with trained professionals who can help you understand where you are.</p>

            <p>The National Council on Problem Gambling defines problem gambling as <em>gambling behavior that causes disruptions in any major area of life &mdash; psychological, physical, social, or vocational.</em> The key word is <strong>disruption</strong>, not frequency. Someone who plays once a month and lies about losses can have a more serious problem than someone who plays daily with full transparency. Consequences define the problem, not behavior alone.</p>
        </section>

        <?php // -- Warning signs ?>
        <section id="warning-signs" class="rg-section">
            <h2>Recognizing when gambling has become a problem</h2>
            <p>Most people experiencing gambling harm don't recognize it themselves until consequences become severe. The signals below tend to appear gradually. <strong>Recognizing two or more is reason enough to talk to a helpline.</strong> Recognizing four or more usually means professional support would help.</p>

            <ul class="rg-signs">
                <li><strong>Spending more time or money than intended</strong> &mdash; sessions consistently run longer than planned, deposits exceed the budget you set yourself, and stopping feels harder than it should.</li>
                <li><strong>Chasing losses</strong> &mdash; returning to gamble specifically to win back what you've lost. This is the single most reliable predictor of escalation in clinical literature.</li>
                <li><strong>Hiding or minimizing</strong> &mdash; lying to family, friends, or colleagues about how much you gamble, how often, or how much you've lost.</li>
                <li><strong>Borrowing, selling, or missing payments</strong> &mdash; taking loans, selling possessions, deferring bills, or moving money between accounts to fund gambling or cover losses.</li>
                <li><strong>Mood, sleep, or performance impact</strong> &mdash; gambling is affecting how you sleep, your concentration at work or in study, your mood, or your relationships.</li>
                <li><strong>Restlessness or irritability when stopping</strong> &mdash; feeling anxious, agitated, or low when trying to cut back or take a break. This is a withdrawal pattern recognized in the DSM-5 criteria for gambling disorder.</li>
                <li><strong>Gambling to relieve stress or low mood</strong> &mdash; using gambling specifically to escape difficult feelings, rather than for entertainment.</li>
                <li><strong>Family or friends have raised concerns</strong> &mdash; people who care about you have asked about your gambling, expressed worry, or set limits. External observation is often clearer than self-assessment.</li>
            </ul>
        </section>

        <?php // -- Self-check ?>
        <section id="self-check" class="rg-section">
            <h2>Brief self-check</h2>
            <p>The two questions below are adapted from the <a href="https://pubmed.ncbi.nlm.nih.gov/9460386/" rel="noopener" target="_blank">Lie/Bet screening tool</a> (Johnson et al., 1998), which has been validated as a brief indicator of probable gambling disorder. Answering "yes" to either question is a strong signal that a full conversation with a helpline counselor would be valuable.</p>

            <div class="rg-selfcheck">
                <div class="rg-sc-q">
                    <div class="rg-sc-n">Q1</div>
                    <div class="rg-sc-text">
                        <strong>Have you ever felt the need to bet more and more money?</strong>
                        <span class="rg-sc-hint">Needing larger stakes to feel the same excitement is a marker of tolerance, one of the diagnostic criteria for gambling disorder.</span>
                    </div>
                </div>
                <div class="rg-sc-q">
                    <div class="rg-sc-n">Q2</div>
                    <div class="rg-sc-text">
                        <strong>Have you ever had to lie to people important to you about how much you gambled?</strong>
                        <span class="rg-sc-hint">Concealment from family, friends, or partners almost always precedes more serious harm.</span>
                    </div>
                </div>
            </div>

            <p class="rg-sc-note">If you answered yes to either, <strong>this isn't a diagnosis</strong>. It is a signal that talking to a helpline counselor &mdash; for free, anonymously, with no obligation &mdash; would be worth the call. The helplines listed below exist for exactly this conversation.</p>
        </section>

        <?php // -- Practical first steps ?>
        <section id="first-steps" class="rg-section">
            <h2>Practical first steps</h2>
            <p>If you've recognized a pattern you want to change, the most effective first steps are concrete and small. The actions below are ordered roughly by how quickly they take effect.</p>

            <ol class="rg-steps">
                <li><strong>Pause &mdash; before anything else.</strong> Don't make any decisions, deposits, or withdrawals while you're emotionally activated. Close the casino tab, put your phone away, and give yourself 24 hours before any next move.</li>
                <li><strong>Set hard limits on the account you have right now.</strong> Most licensed operators offer deposit limits, loss limits, session time limits, and reality checks. The tools section below explains each. Set them to amounts that would be uncomfortable to exceed, not amounts you "probably won't reach."</li>
                <li><strong>Self-exclude when limits aren't enough.</strong> Self-exclusion blocks your account for a defined period (typically 24 hours up to permanent). National self-exclusion registers cover multiple operators at once &mdash; <strong>GAMSTOP</strong> (UK), <strong>BetStop</strong> (Australia), and <strong>state-level programs</strong> in regulated US markets.</li>
                <li><strong>Install gambling-blocking software on your devices.</strong> Tools like <strong>Gamban</strong>, <strong>BetBlocker</strong>, and <strong>Net Nanny</strong> block thousands of gambling sites and apps. Free options exist; the most comprehensive are subscription-based but inexpensive relative to the harm they prevent.</li>
                <li><strong>Talk to one person you trust.</strong> A partner, family member, close friend, or counselor. The first conversation is the hardest; subsequent ones are easier. Isolation is a major risk factor for escalation.</li>
                <li><strong>Call or chat with a helpline.</strong> The country-specific resources below are free, confidential, and staffed by trained professionals. You do not need to identify yourself, and you do not need to commit to anything beyond a single conversation.</li>
            </ol>
        </section>

        <?php // -- Operator tools ?>
        <section id="operator-tools" class="rg-section">
            <h2>Tools casinos offer (and how to use them)</h2>
            <p>Every licensed operator is required to provide responsible-gambling tools. The categories below are standard across the industry; the names sometimes differ but the function is consistent. <strong>Use them before you need them.</strong> Setting limits during a calm session is far more effective than trying to set them during a difficult one.</p>

            <div class="rg-tools-grid">
                <div class="rg-tool">
                    <h3>Deposit limits</h3>
                    <p>Cap the amount you can deposit per day, week, or month. Most operators allow lowering the limit instantly but require a cooling-off period (24&ndash;72 hours) before raising it. Set lower than you think you need.</p>
                </div>
                <div class="rg-tool">
                    <h3>Loss limits</h3>
                    <p>Cap net losses over a defined period. Less common than deposit limits but more useful for managing total exposure regardless of deposit count.</p>
                </div>
                <div class="rg-tool">
                    <h3>Session time limits</h3>
                    <p>Force a logout after a set duration. Useful if you tend to lose track of time during play. Pair with a deposit limit for layered control.</p>
                </div>
                <div class="rg-tool">
                    <h3>Reality checks</h3>
                    <p>Periodic on-screen reminders showing elapsed time and net wagering during the session. Default intervals are typically 60 minutes; setting them to 15&ndash;30 minutes is more effective for early-warning awareness.</p>
                </div>
                <div class="rg-tool">
                    <h3>Time-out (cool-off)</h3>
                    <p>Temporary account suspension from 24 hours up to several weeks. Reversible by the operator after the period ends. Useful for short pauses without a full self-exclusion commitment.</p>
                </div>
                <div class="rg-tool">
                    <h3>Self-exclusion</h3>
                    <p>Long-term or permanent account closure. Cannot be reversed by the operator until the period ends; minimum is typically 6 months. Most countries also have a <strong>national register</strong> that covers all licensed operators at once.</p>
                </div>
            </div>
        </section>

        <?php // -- HELPLINES BY COUNTRY -------------------------- ?>
        <section id="helplines" class="rg-section">
            <h2>Helplines and support organizations by country</h2>
            <p>All helplines listed below are independent of FreeExtraChips and any commercial relationships. Numbers and operating hours were verified on <?php echo htmlspecialchars($updatedTxt, ENT_QUOTES, 'UTF-8'); ?> against the issuing organization. If you call from outside the country, standard international rates may apply.</p>

            <div class="rg-helplines">

                <?php // -- US ?>
                <div class="rg-helpline">
                    <div class="rg-hp-flag" aria-hidden="true">🇺🇸</div>
                    <div class="rg-hp-body">
                        <h3>United States</h3>
                        <div class="rg-hp-primary">
                            <a href="tel:18006973738" class="rg-hp-tel">1-800-MY-RESET</a>
                            <span class="rg-hp-name">National Problem Gambling Helpline</span>
                        </div>
                        <p>Operated by the National Council on Problem Gambling. Adopted as the new national number in January 2026, replacing 1-800-GAMBLER. <strong>24/7, free, confidential.</strong> Text <strong>800GAM</strong> or chat at <a href="https://www.ncpgambling.org/chat" rel="noopener" target="_blank">NCPGambling.org/chat</a>.</p>
                        <p class="rg-hp-self">National self-exclusion: state-level programs in regulated markets (CT, DE, MI, NJ, PA, RI, WV). Federal options for sports betting via <a href="https://www.ncpgambling.org" rel="noopener" target="_blank">NCPG</a>.</p>
                    </div>
                </div>

                <?php // -- UK ?>
                <div class="rg-helpline">
                    <div class="rg-hp-flag" aria-hidden="true">🇬🇧</div>
                    <div class="rg-hp-body">
                        <h3>United Kingdom</h3>
                        <div class="rg-hp-primary">
                            <a href="tel:08088020133" class="rg-hp-tel">0808 8020 133</a>
                            <span class="rg-hp-name">GamCare &mdash; National Gambling Helpline</span>
                        </div>
                        <p>Operating the National Gambling Helpline since 1997. <strong>24/7, free, confidential.</strong> Live chat and WhatsApp also available at <a href="https://www.gamcare.org.uk" rel="noopener" target="_blank">gamcare.org.uk</a>.</p>
                        <p class="rg-hp-self">National self-exclusion: <a href="https://www.gamstop.co.uk" rel="noopener" target="_blank"><strong>GAMSTOP</strong></a> covers all UK-licensed online operators in a single registration.</p>
                    </div>
                </div>

                <?php // -- Australia ?>
                <div class="rg-helpline">
                    <div class="rg-hp-flag" aria-hidden="true">🇦🇺</div>
                    <div class="rg-hp-body">
                        <h3>Australia</h3>
                        <div class="rg-hp-primary">
                            <a href="tel:1800858858" class="rg-hp-tel">1800 858 858</a>
                            <span class="rg-hp-name">National Gambling Helpline</span>
                        </div>
                        <p>Operated by Gambling Help Online. <strong>24/7, free, confidential.</strong> Live chat at <a href="https://www.gamblinghelponline.org.au" rel="noopener" target="_blank">gamblinghelponline.org.au</a>.</p>
                        <p class="rg-hp-self">National self-exclusion: <a href="https://www.betstop.gov.au" rel="noopener" target="_blank"><strong>BetStop</strong></a> excludes you from all Australian-licensed online and phone wagering operators in one step. Period: 3 months to lifetime.</p>
                    </div>
                </div>

                <?php // -- Canada ?>
                <div class="rg-helpline">
                    <div class="rg-hp-flag" aria-hidden="true">🇨🇦</div>
                    <div class="rg-hp-body">
                        <h3>Canada</h3>
                        <div class="rg-hp-primary">
                            <a href="tel:18665312600" class="rg-hp-tel">1-866-531-2600</a>
                            <span class="rg-hp-name">ConnexOntario (Ontario)</span>
                        </div>
                        <p>Ontario-specific. <strong>24/7, free, confidential.</strong> Text <strong>connex</strong> to <strong>247247</strong> or chat at <a href="https://connexontario.ca" rel="noopener" target="_blank">connexontario.ca</a>.</p>
                        <p class="rg-hp-self">Other provinces have their own helplines: <strong>Quebec</strong> Gambling Help and Referral 1-800-461-0140; <strong>BC</strong> Problem Gambling Help Line 1-888-795-6111; <strong>Manitoba</strong> Addictions Helpline 1-855-662-6605. Find your province at <a href="https://www.responsiblegambling.org" rel="noopener" target="_blank">responsiblegambling.org</a>.</p>
                    </div>
                </div>

                <?php // -- Italy ?>
                <div class="rg-helpline">
                    <div class="rg-hp-flag" aria-hidden="true">🇮🇹</div>
                    <div class="rg-hp-body">
                        <h3>Italia</h3>
                        <div class="rg-hp-primary">
                            <a href="tel:800558822" class="rg-hp-tel">800 55 88 22</a>
                            <span class="rg-hp-name">Telefono Verde Nazionale Gioco d'Azzardo (TVNGA)</span>
                        </div>
                        <p>Operated by the Istituto Superiore di Sanità (ISS), the national public health authority. <strong>Free, anonymous</strong>. Available <strong>Monday&ndash;Friday, 10:00&ndash;16:00</strong> (not 24/7). Online platform: <a href="https://usciredalgioco.iss.it" rel="noopener" target="_blank">usciredalgioco.iss.it</a>.</p>
                        <p class="rg-hp-self">National self-exclusion: Registro Unico degli Auto-Esclusi (RUA) gestito da ADM &mdash; richiesta via <a href="https://www.adm.gov.it" rel="noopener" target="_blank">ADM</a> o presso un Servizio Dipendenze (SerD) locale.</p>
                    </div>
                </div>

                <?php // -- Germany ?>
                <div class="rg-helpline">
                    <div class="rg-hp-flag" aria-hidden="true">🇩🇪</div>
                    <div class="rg-hp-body">
                        <h3>Deutschland</h3>
                        <div class="rg-hp-primary">
                            <a href="tel:08001372700" class="rg-hp-tel">0800 137 27 00</a>
                            <span class="rg-hp-name">BIÖG &mdash; Telefonberatung zur Glücksspielsucht</span>
                        </div>
                        <p>Operated by the Bundesinstitut für Öffentliche Gesundheit (BIÖG, formerly BZgA). <strong>Free, anonymous</strong>. Mon&ndash;Thu 10&ndash;22, Fri&ndash;Sun 10&ndash;18, 363 days a year (closed Dec 24 and 31). Information and self-test at <a href="https://www.check-dein-spiel.de" rel="noopener" target="_blank">check-dein-spiel.de</a>.</p>
                        <p class="rg-hp-self">National self-exclusion: <strong>OASIS</strong> register, managed by the Gemeinsame Glücksspielbehörde der Länder (GGL). Covers all German-licensed operators.</p>
                    </div>
                </div>

                <?php // -- France ?>
                <div class="rg-helpline">
                    <div class="rg-hp-flag" aria-hidden="true">🇫🇷</div>
                    <div class="rg-hp-body">
                        <h3>France</h3>
                        <div class="rg-hp-primary">
                            <a href="tel:0974751313" class="rg-hp-tel">09 74 75 13 13</a>
                            <span class="rg-hp-name">Joueurs Info Service</span>
                        </div>
                        <p>Operated by Santé publique France. <strong>Free, anonymous</strong>. Available <strong>7 days a week, 8:00&ndash;2:00</strong>. Live chat Mon&ndash;Fri 14:00&ndash;24:00 at <a href="https://www.joueurs-info-service.fr" rel="noopener" target="_blank">joueurs-info-service.fr</a>.</p>
                        <p class="rg-hp-self">National self-exclusion: <strong>Fichier des interdits de jeu</strong>, managed by the Autorité nationale des jeux (ANJ). Covers all French-licensed gambling sites and physical venues.</p>
                    </div>
                </div>

            </div>

            <div class="rg-international">
                <h3>International &amp; cross-border resources</h3>
                <ul>
                    <li><strong><a href="https://www.gamblingtherapy.org" rel="noopener" target="_blank">Gambling Therapy</a></strong> &mdash; free online support and live counseling in multiple languages, run by the Gordon Moody Association (UK charity). Useful when local helplines aren't available in your language.</li>
                    <li><strong><a href="https://www.gamblersanonymous.org" rel="noopener" target="_blank">Gamblers Anonymous</a></strong> &mdash; peer support groups worldwide using the 12-step recovery model. Meetings available in-person and online in most countries.</li>
                    <li><strong><a href="https://www.gam-anon.org" rel="noopener" target="_blank">Gam-Anon</a></strong> &mdash; support for family members and friends of problem gamblers. Separate from Gamblers Anonymous; specifically for those affected by someone else's gambling.</li>
                </ul>
            </div>
        </section>

        <?php // -- For family and friends ?>
        <section id="family-friends" class="rg-section">
            <h2>If you're worried about someone else</h2>
            <p>If gambling is affecting someone close to you &mdash; a partner, family member, friend, or colleague &mdash; you are also affected, and you also need support. Trying to manage someone else's gambling on your own is exhausting and rarely successful. The same helplines listed above accept calls from family and friends, not only from gamblers themselves.</p>

            <p>Practical guidance from clinical sources:</p>

            <ul class="rg-family-tips">
                <li><strong>Don't cover financial losses</strong> beyond what's absolutely necessary to prevent immediate crisis (eviction, food, dependents' welfare). Repeated bailouts remove the consequences that trigger change.</li>
                <li><strong>Don't accuse, don't moralize.</strong> Confrontation in the heat of conflict typically pushes the gambler into deeper concealment. Calm, specific conversations about what you've observed work better.</li>
                <li><strong>Protect your own finances.</strong> Separate accounts if necessary, monitor joint accounts, document any borrowing. This isn't punitive &mdash; it's basic safety while the gambler works on recovery.</li>
                <li><strong>Encourage but don't compel treatment.</strong> Recovery typically requires the gambler's own motivation. Your role is to make help accessible, not to deliver it.</li>
                <li><strong>Use Gam-Anon or family-focused counseling.</strong> Living with someone in active gambling addiction is itself traumatic. You deserve dedicated support.</li>
            </ul>
        </section>

        <?php // -- Our position ?>
        <section id="position" class="rg-section">
            <h2>Our position</h2>
            <p>FreeExtraChips publishes information about online casino bonuses and promotions. We are not a gambling operator, we do not host gambling, and we do not encourage anyone to gamble beyond their means. Our editorial process is independent of operator relationships, and we have published this page <strong>without affiliate links of any kind</strong> as a matter of policy.</p>

            <p>If gambling has stopped being enjoyable, or is causing harm to you or someone close to you, <strong>the responsible choice is to stop and get support</strong>. The helplines on this page exist for that conversation. They are free, anonymous, and staffed by people who will not judge you.</p>

            <p>If you find an error on this page &mdash; an outdated helpline number, a closed organization, a regional resource we should add &mdash; please email <a href="mailto:editorial@freeextrachips.com">editorial@freeextrachips.com</a>. We treat this page as a public-service document and corrections are prioritized.</p>
        </section>

    </article>

    <?php // -- SIDEBAR: sticky TOC + reviewer card --------------------- ?>
    <aside class="au-side rg-side" aria-label="On this page">
        <div class="au-side-card rg-toc">
            <div class="eyebrow">On this page</div>
            <ul>
                <li><a href="#intro">Overview</a></li>
                <li><a href="#warning-signs">Warning signs</a></li>
                <li><a href="#self-check">Brief self-check</a></li>
                <li><a href="#first-steps">First steps</a></li>
                <li><a href="#operator-tools">Casino tools</a></li>
                <li><a href="#helplines">Helplines by country</a></li>
                <li><a href="#family-friends">For family &amp; friends</a></li>
                <li><a href="#position">Our position</a></li>
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
            <div class="eyebrow">Editorial independence</div>
            <p>This page is published as a public service. <strong>No affiliate links, no operator sponsorships, no commercial influence.</strong></p>
        </div>
    </aside>

</div>

<?php // -- Footer byline --------------------------------------------------- ?>
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
