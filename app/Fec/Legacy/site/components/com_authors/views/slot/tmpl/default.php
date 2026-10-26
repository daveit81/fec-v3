<?php
/**
 * Slot detail page template — /slots/<slug>
 *
 * Reuses fec-edition.css + methodology.css tokens + adds slot-specific
 * classes via assets/slot.css.
 */
defined('_JEXEC') or die;

$slot   = $this->slot;
$obs    = $this->observations;
$bons   = $this->bonuses;
$author = $this->author;

$h    = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$nfmt = function ($n) { return number_format((int)$n); };

// Compute display strings
$rtpMax     = $slot->rtp_theoretical_max !== null ? number_format((float)$slot->rtp_theoretical_max, 2) : null;
$rtpMin     = $slot->rtp_theoretical_min !== null ? number_format((float)$slot->rtp_theoretical_min, 2) : null;
$isMultiTier = ($rtpMin !== null && (float)$slot->rtp_theoretical_min < (float)$slot->rtp_theoretical_max);

$variants = array();
if (!empty($slot->rtp_variants_json)) {
    $decoded = json_decode($slot->rtp_variants_json, true);
    if (is_array($decoded)) {
        $variants = $decoded;
    }
}

$lastVerifiedFmt = '';
if (!empty($slot->last_verified_date) && $slot->last_verified_date !== '0000-00-00') {
    $lastVerifiedFmt = date('j F Y', strtotime($slot->last_verified_date));
}

$methodologyUrl = JRoute::_('index.php?option=com_authors&view=methodology&topic=rtp-verification');
$slotsIndexUrl  = JRoute::_('index.php?option=com_authors&view=slots');
$authorUrl = '';
if ($author && !empty($author['slug'])) {
    $authorUrl = JRoute::_('index.php?option=com_authors&view=author&slug=' . urlencode($author['slug']));
}

// Type labels for active bonus list (mirror existing site conventions)
$bonusTypeLabels = array(
    'nd'  => 'No deposit',
    'fs'  => 'Free spins',
    'su'  => 'Sign-up match',
    '2d'  => '2nd deposit',
    '3d'  => '3rd deposit',
    '4d'  => '4th deposit',
    '5d'  => '5th deposit',
    'sl'  => 'Slots bonus',
    'hr'  => 'High roller',
    'cb'  => 'Cashback',
    'bj'  => 'Blackjack',
    'fp'  => 'Free play',
    'rl'  => 'Roulette',
    'vp'  => 'Video poker',
    'db'  => 'Daily bonus',
    'wb'  => 'Weekly bonus',
    'fc'  => 'Free chip',
);

?>
<div class="fecx slt-page">

<!-- ========================== HERO ========================== -->
<section class="au-hero">
  <div class="wrap">
    <div class="me-hero-card">
      <div class="me-hero-body">
        <div class="eyebrow">Slot · RTP-verified</div>
        <h1 class="me-title"><?php echo $h($slot->game_name); ?></h1>
        <p class="me-tagline">
          <?php echo $h($slot->provider_name); ?>
          <?php if (!empty($slot->release_year)): ?>
            · Released <?php echo $h($slot->release_year); ?>
          <?php endif; ?>
        </p>

        <div class="me-byline">
          <?php if ($author): ?>
            <span>Verified by
              <?php if ($authorUrl !== ''): ?>
                <a href="<?php echo $authorUrl; ?>"><b><?php echo $h($author['name']); ?></b></a>
              <?php else: ?>
                <b><?php echo $h($author['name']); ?></b>
              <?php endif; ?>
            </span>
          <?php endif; ?>
          <?php if ($lastVerifiedFmt !== ''): ?>
            <span class="div">·</span>
            <span>Last verified <b><?php echo $h($lastVerifiedFmt); ?></b></span>
          <?php endif; ?>
          <span class="div">·</span>
          <span><a href="<?php echo $methodologyUrl; ?>">Methodology</a></span>
        </div>
      </div>
    </div>

    <!-- Stats strip -->
    <div class="au-stats-strip">
      <?php if ($rtpMax !== null): ?>
      <div class="stat">
        <div class="num"><?php echo $h($rtpMax); ?>%</div>
        <div class="lbl">RTP<?php echo $isMultiTier ? ' (max tier)' : ''; ?></div>
      </div>
      <?php endif; ?>

      <?php if ($isMultiTier): ?>
      <div class="stat slt-warn">
        <div class="num"><?php echo $h($rtpMin); ?>%</div>
        <div class="lbl">RTP (min tier) ⚠</div>
      </div>
      <?php endif; ?>

      <?php if (!empty($slot->volatility)): ?>
      <div class="stat">
        <div class="num"><?php echo $h(AuthorsHelper::volatilityLabel($slot->volatility)); ?></div>
        <div class="lbl">Volatility</div>
      </div>
      <?php endif; ?>

      <?php if (!empty($slot->max_win_x)): ?>
      <div class="stat">
        <div class="num"><?php echo $nfmt($slot->max_win_x); ?>×</div>
        <div class="lbl">Max win</div>
      </div>
      <?php endif; ?>

      <div class="stat">
        <div class="num"><?php echo $nfmt(count($bons)); ?></div>
        <div class="lbl">Active bonuses</div>
      </div>
    </div>
  </div>
</section>

<!-- ========================== MAIN ========================== -->
<section class="au-main wrap">
  <article class="me-content">

    <!-- ============ RETIRED BANNER (if applicable) ============ -->
    <?php if (!empty($slot->retired) && (int)$slot->retired === 1): ?>
      <div class="slt-retired-banner" role="status">
        <div class="slt-retired-label">⚠ Slot retired</div>
        <p>
          This slot has been retired. Historical data is preserved here for reference,
          but the slot may no longer be available at any operator we track. Bonuses
          listed below may also be inactive.
        </p>
        <?php if (!empty($slot->retired_reason)): ?>
          <p class="slt-retired-reason">
            <b>Reason:</b> <?php echo $h($slot->retired_reason); ?>
          </p>
        <?php endif; ?>
        <?php if (!empty($slot->retired_at) && $slot->retired_at !== '0000-00-00 00:00:00'): ?>
          <p class="slt-retired-when">
            Retired on <?php echo $h(date('j F Y', strtotime($slot->retired_at))); ?>.
          </p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- ============ MULTI-TIER DISCLOSURE BOX (if applicable) ============ -->
    <?php if ($isMultiTier): ?>
    <div class="slt-tier-box">
      <div class="slt-tier-label">⚠ Multi-tier RTP</div>
      <h2 class="slt-tier-title">
        This slot ships in <?php echo count($variants) ?: 'multiple'; ?> RTP versions
      </h2>
      <p>
        <?php echo $h($slot->provider_name); ?> distributes <?php echo $h($slot->game_name); ?>
        in multiple configurations. Operators choose which version to serve. Two players spinning
        the same game on different casinos may be playing at very different actual payback rates.
      </p>
      <?php if (!empty($variants)): ?>
        <div class="slt-tier-grid">
          <?php
            foreach ($variants as $i => $v):
              $v = is_string($v) ? trim($v) : '';
              if ($v === '') continue;
              $isTopTier = ($i === 0);
              $isLowTier = ($i === count($variants) - 1 && count($variants) > 1);
              $tierClass = $isTopTier ? 'is-top' : ($isLowTier ? 'is-low' : '');
              $tierLabel = $isTopTier ? 'Top tier' : ($isLowTier ? 'Lowest tier' : 'Middle tier');
          ?>
            <div class="slt-tier-cell <?php echo $tierClass; ?>">
              <div class="slt-tier-pct"><?php echo $h($v); ?>%</div>
              <div class="slt-tier-cap"><?php echo $tierLabel; ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <p class="slt-tier-cta">
        The difference between the top and bottom tier is approximately
        <b><?php echo number_format(((float)$slot->rtp_theoretical_max - (float)$slot->rtp_theoretical_min), 2); ?>
        percentage points</b> of expected return.
        Always verify the configured RTP at the specific casino via the in-game paytable.
        <a href="<?php echo $methodologyUrl; ?>#multi-tier">Read how we verify multi-tier RTP →</a>
      </p>
    </div>
    <?php endif; ?>

    <!-- ============ GAME OVERVIEW ============ -->
    <h2 id="overview">Overview</h2>
    <div class="slt-overview">
      <dl class="slt-spec">
        <?php if ($rtpMax !== null): ?>
          <div><dt>Theoretical RTP<?php echo $isMultiTier ? ' (max tier)' : ''; ?></dt><dd><?php echo $h($rtpMax); ?>%</dd></div>
        <?php endif; ?>
        <?php if ($isMultiTier): ?>
          <div><dt>Theoretical RTP (min tier)</dt><dd><?php echo $h($rtpMin); ?>%</dd></div>
        <?php endif; ?>
        <?php if (!empty($slot->volatility)): ?>
          <div><dt>Volatility</dt><dd><?php echo $h(AuthorsHelper::volatilityLabel($slot->volatility)); ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($slot->max_win_x)): ?>
          <div><dt>Maximum win</dt><dd><?php echo $nfmt($slot->max_win_x); ?>× bet</dd></div>
        <?php endif; ?>
        <?php if (!empty($slot->reels)): ?>
          <div><dt>Reels</dt><dd><?php echo $h($slot->reels); ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($slot->paylines)): ?>
          <div><dt>Paylines / ways</dt><dd><?php echo $h($slot->paylines); ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($slot->release_year)): ?>
          <div><dt>Release year</dt><dd><?php echo $h($slot->release_year); ?></dd></div>
        <?php endif; ?>
        <div><dt>Provider</dt><dd><?php echo $h($slot->provider_name); ?></dd></div>
      </dl>

      <?php if (!empty($slot->notes)): ?>
        <div class="slt-notes">
          <h3>Editor's notes</h3>
          <p><?php echo nl2br($h($slot->notes)); ?></p>
        </div>
      <?php endif; ?>
    </div>

    <!-- ============ SOURCES ============ -->
    <h2 id="sources">Sources</h2>
    <p>
      Every value on this page is sourced from primary documentation. Where the source URL has been
      preserved on the Internet Archive, the archived snapshot link is provided alongside the live
      URL so the data behind any claim can be inspected even if the live URL changes.
    </p>
    <ul class="slt-sources">
      <?php if (!empty($slot->primary_source_url)): ?>
        <li>
          <span class="slt-src-label">Provider game page</span>
          <a href="<?php echo $h($slot->primary_source_url); ?>" target="_blank" rel="nofollow noopener">
            <?php echo $h(preg_replace('~^https?://(www\.)?~', '', $slot->primary_source_url)); ?>
          </a>
          <?php if (!empty($slot->primary_source_archived_url)): ?>
            <span class="slt-src-archive">
              · <a href="<?php echo $h($slot->primary_source_archived_url); ?>" target="_blank" rel="nofollow noopener">archived snapshot ↗</a>
            </span>
          <?php endif; ?>
        </li>
      <?php else: ?>
        <li>
          <span class="slt-src-label">Provider game page</span>
          <em>Not publicly available. <?php echo $h($slot->provider_name); ?> does not maintain
          public game data pages — values verified via in-game paytable at the casino level (see
          per-casino observations below).</em>
        </li>
      <?php endif; ?>

      <?php if (!empty($slot->certificate_url)): ?>
        <li>
          <span class="slt-src-label">Regulator certificate</span>
          <a href="<?php echo $h($slot->certificate_url); ?>" target="_blank" rel="nofollow noopener">
            <?php echo $h(preg_replace('~^https?://(www\.)?~', '', $slot->certificate_url)); ?>
          </a>
        </li>
      <?php endif; ?>
    </ul>

    <!-- ============ PER-CASINO OBSERVATIONS ============ -->
    <?php if (!empty($obs)): ?>
      <h2 id="observations">Per-casino observations</h2>
      <p>
        What the in-game paytable actually shows at specific casinos. These are timestamped readings,
        not estimates.
      </p>
      <div class="slt-obs-wrap">
        <table class="slt-obs-table">
          <thead>
            <tr>
              <th>Casino</th>
              <th class="slt-num">RTP observed</th>
              <th>Date</th>
              <th>Evidence</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($obs as $o): ?>
              <?php
                $isBelowMax = ($rtpMax !== null && $o->rtp_observed !== null
                  && (float)$o->rtp_observed < (float)$slot->rtp_theoretical_max - 0.05);
                $rowClass = $isBelowMax ? 'is-below' : '';
              ?>
              <tr class="<?php echo $rowClass; ?>">
                <td><?php echo $h($o->casino_name ?: ('casino #' . $o->cid)); ?></td>
                <td class="slt-num"><b><?php echo $h(number_format((float)$o->rtp_observed, 2)); ?>%</b>
                  <?php if ($isBelowMax): ?><span class="slt-tag-warn">below headline</span><?php endif; ?>
                </td>
                <td><?php echo $h($o->observed_date && $o->observed_date !== '0000-00-00' ? date('j M Y', strtotime($o->observed_date)) : '—'); ?></td>
                <td><?php echo $h(str_replace('_', ' ', (string)$o->evidence_type)); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <!-- ============ ACTIVE BONUSES ============ -->
    <?php if ($this->totalBonuses > 0): ?>
      <h2 id="bonuses">Active bonuses on this slot</h2>
      <p>
        <?php echo $nfmt($this->totalBonuses); ?>
        bonus<?php echo $this->totalBonuses === 1 ? '' : 'es'; ?>
        currently offered on <?php echo $h($slot->game_name); ?>. Each links to the casino review
        and the bonus details with full T&amp;Cs.
        <?php if ($this->totalBonuses > $this->limit): ?>
          Showing <?php echo $nfmt($this->limitstart + 1); ?>–<?php echo $nfmt(min($this->limitstart + $this->limit, $this->totalBonuses)); ?>.
        <?php endif; ?>
      </p>
      <ul class="slt-bonus-list">
        <?php foreach ($bons as $b): ?>
          <?php
            $typeLabel = isset($bonusTypeLabels[$b->type]) ? $bonusTypeLabels[$b->type] : ucfirst((string)$b->type);
            $amount = '';
            if ((int)$b->bonus > 0) {
                if (in_array($b->type, array('fs', 'fp'), true)) {
                    $amount = $b->bonus . ' free spins';
                } elseif ((int)$b->bonus > 100 && !empty($b->currency)) {
                    $amount = strtoupper(substr($b->currency, 0, 1) === 'U' ? '$' : '€') . $b->bonus;
                } else {
                    $amount = $b->bonus . '%';
                }
            }
            // Casino link via com_content (catid=8, online-casinos category)
            $casinoUrl = JRoute::_(
                'index.php?option=com_content&view=article&id=' . (int)$b->cid
                . '&catid=8&lang=en'
            );
            // Bonus details link via com_bonuses
            $bonusUrl = JRoute::_(
                'index.php?option=com_bonuses&view=details&bid=' . (int)$b->bid
                . '&cid=' . (int)$b->cid
                . '&type=' . urlencode((string)$b->type)
                . '&lang=en'
            );
          ?>
          <li class="slt-bonus-row">
            <div class="slt-bonus-main">
              <a class="slt-bonus-type-link" href="<?php echo $h($bonusUrl); ?>">
                <span class="slt-bonus-type"><?php echo $h($typeLabel); ?></span>
              </a>
              <a class="slt-bonus-amount-link" href="<?php echo $h($bonusUrl); ?>">
                <span class="slt-bonus-amount"><?php echo $h($amount); ?></span>
              </a>
              <span class="slt-bonus-at">at</span>
              <a class="slt-bonus-casino" href="<?php echo $h($casinoUrl); ?>"><?php echo $h($b->casino_name ?: $b->brand); ?></a>
            </div>
            <div class="slt-bonus-meta">
              <?php if (!empty($b->wr) && (int)$b->wr > 0): ?>
                <span>WR <?php echo (int)$b->wr; ?>×<?php echo $b->wr_meth === 'b' ? ' bonus' : ($b->wr_meth === 'd+b' ? ' (d+b)' : ''); ?></span>
              <?php endif; ?>
              <?php if (!empty($b->code)): ?>
                <span class="slt-bonus-code"><?php echo $h($b->code); ?></span>
              <?php endif; ?>
              <?php if (!empty($b->max_bonus)): ?>
                <span>up to <?php echo $h(number_format((float)$b->max_bonus)); ?></span>
              <?php endif; ?>
              <a class="slt-bonus-details" href="<?php echo $h($bonusUrl); ?>">Details →</a>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php
        // Pagination — only render if more than 1 page
        if ($this->totalBonuses > $this->limit):
          $totalPages = (int)ceil($this->totalBonuses / $this->limit);
          $currentPage = (int)floor($this->limitstart / $this->limit) + 1;
          // Build canonical base for pagination links (slot page itself)
          $pageBase = JRoute::_('index.php?option=com_authors&view=slot&slug=' . urlencode($slot->slug));
      ?>
      <nav class="slt-pagination" role="navigation" aria-label="Bonuses pagination">
        <?php if ($currentPage > 1): ?>
          <?php
            $prevStart = $this->limitstart - $this->limit;
            $prevUrl = $pageBase . ($prevStart > 0 ? '?limitstart=' . $prevStart : '');
          ?>
          <a class="slt-page-link" href="<?php echo $h($prevUrl); ?>" rel="prev">← Prev</a>
        <?php else: ?>
          <span class="slt-page-link is-disabled">← Prev</span>
        <?php endif; ?>

        <span class="slt-page-info">
          Page <b><?php echo $currentPage; ?></b> of <b><?php echo $totalPages; ?></b>
        </span>

        <?php if ($currentPage < $totalPages): ?>
          <?php $nextUrl = $pageBase . '?limitstart=' . ($this->limitstart + $this->limit); ?>
          <a class="slt-page-link" href="<?php echo $h($nextUrl); ?>" rel="next">Next →</a>
        <?php else: ?>
          <span class="slt-page-link is-disabled">Next →</span>
        <?php endif; ?>
      </nav>
      <?php endif; ?>
    <?php endif; ?>

    <!-- ============ FROM THE BLOG (2.7.72) ============ -->
    <?php if (!empty($this->blogPosts)): ?>
      <?php
        $blogGame = 0; $blogFamily = 0;
        foreach ($this->blogPosts as $bp) { if (isset($bp['slot_rel']) && $bp['slot_rel'] === 'game') $blogGame++; else $blogFamily++; }
        $blogIndexUrl = AuthorsHelper::blogIndexUrl();
      ?>
      <h2 id="blog">From the blog</h2>
      <p>
        <?php if ($blogGame > 0 && $blogFamily > 0): ?>
          What our writers published on <?php echo $h($slot->game_name); ?> and on its family: sequels,
          variants and jackpot stories built on the same game. The verification above is about the original only.
        <?php elseif ($blogGame > 0): ?>
          What our writers published on <?php echo $h($slot->game_name); ?>.
        <?php else: ?>
          No post is about <?php echo $h($slot->game_name); ?> itself yet; these cover its family: sequels,
          variants and jackpot stories built on the same game. The verification above is about the original only.
        <?php endif; ?>
      </p>
      <ul class="slt-blog-list">
        <?php foreach ($this->blogPosts as $bp):
          $bpTitle = isset($bp['title']) ? trim((string)$bp['title']) : '';
          if ($bpTitle === '') continue;
          $bpUrl   = AuthorsHelper::blogPostUrl($bp['id'], $bp['alias'], isset($bp['catid']) ? $bp['catid'] : 0);
          $bpDate  = AuthorsHelper::blogDate(isset($bp['publish_up']) ? $bp['publish_up'] : '');
          $bpThumb = AuthorsHelper::blogPostThumb($bp, 96, 60);
          $bpKind  = AuthorsHelper::blogPostLabel(array('kind' => isset($bp['kind']) ? $bp['kind'] : 'news'));
          $bpRel   = (isset($bp['slot_rel']) && $bp['slot_rel'] === 'game') ? 'About this slot' : 'Same family';
        ?>
          <li>
            <a class="slt-blog-row" href="<?php echo $h($bpUrl); ?>">
              <span class="slt-blog-thumb<?php echo $bpThumb === '' ? ' is-empty' : ''; ?>" aria-hidden="true"><?php echo $bpThumb; ?></span>
              <span class="slt-blog-main">
                <span class="slt-blog-title"><?php echo $h($bpTitle); ?></span>
                <span class="slt-blog-meta">
                  <?php if ($bpDate !== ''): ?><span><?php echo $h($bpDate); ?></span><?php endif; ?>
                  <span><?php echo $h($bpKind); ?></span>
                  <?php if (!empty($bp['created_by_alias'])): ?><span><?php echo $h($bp['created_by_alias']); ?></span><?php endif; ?>
                  <span class="slt-blog-rel<?php echo $bpRel === 'About this slot' ? ' is-game' : ''; ?>"><?php echo $h($bpRel); ?></span>
                </span>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($this->blogTotal > count($this->blogPosts)): ?>
        <p class="slt-blog-more">
          <?php echo $nfmt($this->blogTotal); ?> posts mention <?php echo $h($slot->game_name); ?> on the blog.
          <a href="<?php echo $h($blogIndexUrl); ?>">Browse the blog →</a>
        </p>
      <?php endif; ?>
    <?php endif; ?>

    <!-- ============ AUTHOR SIGNATURE CARD ============ -->
    <?php if ($author): ?>
      <div class="slt-author-card">
        <div class="slt-author-avatar">
          <?php if (!empty($author['avatar_url'])): ?>
            <img src="<?php echo $h($author['avatar_url']); ?>"
                 alt="<?php echo $h($author['name']); ?>"
                 loading="lazy">
          <?php else: ?>
            <span class="slt-author-initials">
              <?php
                $parts = preg_split('~\s+~', trim($author['name']));
                $initials = '';
                foreach ($parts as $p) { if ($p !== '') $initials .= strtoupper(substr($p, 0, 1)); }
                echo $h(substr($initials, 0, 2));
              ?>
            </span>
          <?php endif; ?>
        </div>
        <div class="slt-author-body">
          <div class="slt-author-eyebrow">Verified by</div>
          <h3 class="slt-author-name">
            <?php if ($authorUrl !== ''): ?>
              <a href="<?php echo $authorUrl; ?>"><?php echo $h($author['name']); ?></a>
            <?php else: ?>
              <?php echo $h($author['name']); ?>
            <?php endif; ?>
          </h3>
          <?php if (!empty($author['role'])): ?>
            <div class="slt-author-role"><?php echo $h($author['role']); ?> · FreeExtraChips</div>
          <?php endif; ?>
          <?php if (!empty($author['short_bio'])): ?>
            <p class="slt-author-bio"><?php echo $h($author['short_bio']); ?></p>
          <?php endif; ?>
          <div class="slt-author-meta">
            <?php if ($lastVerifiedFmt !== ''): ?>
              <span>Last verified <b><?php echo $h($lastVerifiedFmt); ?></b></span>
            <?php endif; ?>
            <?php if ($authorUrl !== ''): ?>
              <span class="div">·</span>
              <a class="slt-author-profile" href="<?php echo $authorUrl; ?>">View profile →</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- ============ METHODOLOGY LINK ============ -->
    <div class="slt-methodology-cta">
      <p>
        <b>How was this verified?</b>
        Every value on this page comes from a primary source documented in our
        <a href="<?php echo $methodologyUrl; ?>">RTP verification methodology</a>.
        Spotted an error?
        <a href="mailto:editorial@freeextrachips.com?subject=Slot%20correction%3A%20<?php echo urlencode($slot->game_name); ?>">
          Email the editors</a> — acknowledged within 5 business days.
      </p>
    </div>

  </article>

  <!-- ============ SIDEBAR ============ -->
  <aside class="au-sidebar">
    <div class="au-side-card">
      <div class="me-toc-label">On this page</div>
      <ul>
        <li><a href="#overview">Overview</a></li>
        <?php if ($isMultiTier): ?><li><a href="#sources">Multi-tier RTP</a></li><?php endif; ?>
        <li><a href="#sources">Sources</a></li>
        <?php if (!empty($obs)): ?><li><a href="#observations">Per-casino observations</a></li><?php endif; ?>
        <?php if (!empty($bons)): ?><li><a href="#bonuses">Active bonuses</a></li><?php endif; ?>
        <?php if (!empty($this->blogPosts)): ?><li><a href="#blog">From the blog</a></li><?php endif; ?>
      </ul>
    </div>

    <div class="au-side-card">
      <div class="me-toc-label">Related</div>
      <ul class="me-side-list">
        <li><a href="<?php echo $methodologyUrl; ?>">RTP methodology</a></li>
        <li><a href="<?php echo $slotsIndexUrl; ?>">All RTP-verified slots</a></li>
        <li><a href="/affiliate-advertiser-disclosure">Affiliate disclosure</a></li>
      </ul>
    </div>

    <div class="au-side-cta">
      <div class="me-cta-label">Provider transparency</div>
      <p>
        <?php if (!empty($slot->primary_source_url)): ?>
          <?php echo $h($slot->provider_name); ?> publishes RTP data publicly. We've archived the
          page on the Wayback Machine on the date we verified.
        <?php else: ?>
          <?php echo $h($slot->provider_name); ?> does not publish public RTP data. Our values
          come from in-game paytable observations at licensed operators.
        <?php endif; ?>
      </p>
    </div>
  </aside>
</section>

</div>
