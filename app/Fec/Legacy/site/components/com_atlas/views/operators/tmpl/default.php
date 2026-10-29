<?php
/**
 * Public template — Atlas index. fecv2 design system (me-page / au-hero /
 * au-stats-strip / au-main + au-sidebar / me-hub-grid), same skeleton as
 * the Methodology hub for full site coherence.
 */
defined('_JEXEC') or die;

$groups = $this->groups;
$stats  = $this->stats;
$esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$cut = function ($s, $n) {
    $s = trim((string)$s);
    if ($s === '') return '';
    if (function_exists('mb_strlen')) {
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
    }
    return strlen($s) > $n ? substr($s, 0, $n - 1) . '...' : $s;
};

$arrow = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"></path></svg>';

$typeOrder = array('operator', 'holding', 'commercial', 'affiliate_program', 'payment_shell', 'unknown');
$typeAnchors = array(
    'operator' => 'operators', 'holding' => 'holdings', 'commercial' => 'commercial',
    'affiliate_program' => 'programs', 'payment_shell' => 'payment', 'unknown' => 'other',
);

$changelogUrl = atlas_route('index.php?option=com_atlas&view=changelog');
?>
<div class="fecx me-page atlp">

<!-- ========================== HERO ========================== -->
<section class="au-hero">
  <div class="wrap">
    <div class="me-hero-card">
      <div class="me-hero-body">
        <div class="eyebrow">Transparency</div>
        <h1 class="me-title">Casino Operator Atlas</h1>
        <p class="me-tagline">
          Every casino brand is run by a legal entity &mdash; and that entity, not the brand, is
          what a licence, a registry filing or a court record actually points at. The Atlas maps
          those entities: who they are, where they are registered, which licences they hold, and
          which brands they operate today or operated in the past.
        </p>
      </div>
    </div>

    <div class="au-stats-strip">
      <div class="stat">
        <div class="num"><?php echo (int)$stats['entities']; ?></div>
        <div class="lbl">Published entities</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo (int)$stats['brands']; ?></div>
        <div class="lbl">Brands mapped</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo (int)$stats['licences']; ?></div>
        <div class="lbl">Licence records</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $stats['last_update'] ? $esc(date('j M Y', strtotime($stats['last_update']))) : '&mdash;'; ?></div>
        <div class="lbl">Last update</div>
      </div>
    </div>
  </div>
</section>

<!-- ========================== MAIN ========================== -->
<section class="au-main wrap">
  <article class="me-content">

    <h2 id="how-to-read">How to read the Atlas</h2>
    <p>Everything published here carries a confidence grade. <b>Verified</b> means two independent
    sources agree &mdash; a regulator validator plus a first-party footer, a registry PDF plus live
    terms. <b>Probable</b> means one strong source, clearly identified. Findings resting on a
    single unconfirmed source are researched internally but never published: if it is on this
    page, it met the bar. Ownership history is never deleted &mdash; when a brand changes hands,
    the old era is closed and kept, because the past is part of the record.</p>

    <?php foreach ($typeOrder as $type): if (empty($groups[$type])) continue; ?>
      <h2 id="<?php echo $typeAnchors[$type]; ?>"><?php echo $esc(AtlasModelPublicbase::typeLabel($type)); ?>s</h2>
      <div class="me-hub-grid">
        <?php foreach ($groups[$type] as $e):
          $desc = trim((string)$e->public_notes);
          if ($desc === '') {
              $umbrella = in_array($e->entity_type, array('affiliate_program', 'commercial'), true);
              $bits = array();
              if ($umbrella && (int)$e->net_count) {
                  $bits[] = (int)$e->net_count . ' brand' . ((int)$e->net_count === 1 ? '' : 's') . ' in the program today';
              } elseif ((int)$e->brand_count) {
                  $bits[] = (int)$e->brand_count . ' brand' . ((int)$e->brand_count === 1 ? '' : 's') . ' operated today';
              }
              if ((int)$e->lic_count)   $bits[] = (int)$e->lic_count . ' licence record' . ((int)$e->lic_count === 1 ? '' : 's');
              $desc = count($bits) ? implode(' and ', $bits) . ', with sourced confidence grades and full era history.' : 'Profile with sourced confidence grades and full era history.';
          }
        ?>
        <a class="me-hub-card" href="<?php echo atlas_route('index.php?option=com_atlas&view=operator&slug=' . $e->slug); ?>">
          <div class="me-hub-card-head">
            <h3><?php echo $esc($e->name); ?></h3>
            <?php if ($e->status === 'active'): ?>
              <span class="tag tag-ok">Active</span>
            <?php else: ?>
              <span class="tag tag-warn"><?php echo $esc(str_replace('_', ' ', $e->status)); ?></span>
            <?php endif; ?>
          </div>
          <p class="me-hub-card-desc"><?php echo $esc($cut($desc, 170)); ?></p>
          <p class="me-hub-card-meta">
            <?php if ($e->jurisdiction): ?>
              <span class="meta-version"><?php echo $esc(AtlasModelPublicbase::jurisdictionLabel($e->jurisdiction)); ?></span>
            <?php endif; ?>
            <?php if ($e->jurisdiction && $e->reg_number): ?><span class="meta-sep" aria-hidden="true">&middot;</span><?php endif; ?>
            <?php if ($e->reg_number): ?>
              <span class="meta-updated">reg <?php echo $esc($e->reg_number); ?></span>
            <?php endif; ?>
          </p>
          <div class="me-hub-card-cta">Open profile <?php echo $arrow; ?></div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <?php if (!count($this->entities)): ?>
      <p class="atlp-empty">The first operator profiles are being finalised &mdash; check back shortly.</p>
    <?php endif; ?>

    <h2 id="updates">When this gets updated</h2>
    <p>Corporate structures move faster than any register. Licence grants and losses, entity
    migrations, registry strike-offs and rebrands are verified against primary sources, then
    logged with date and rationale in the
    <a href="<?php echo $changelogUrl; ?>">Atlas changelog</a>. Each profile shows when it was
    last verified.</p>

  </article>

  <aside class="au-sidebar">
    <div class="au-side-card">
      <h4>What this page covers</h4>
      <nav class="me-toc">
        <a href="#how-to-read">How to read the Atlas</a>
        <?php foreach ($typeOrder as $type): if (empty($groups[$type])) continue; ?>
          <a href="#<?php echo $typeAnchors[$type]; ?>"><?php echo $esc(AtlasModelPublicbase::typeLabel($type)); ?>s</a>
        <?php endforeach; ?>
        <a href="#updates">When this gets updated</a>
      </nav>
    </div>

    <div class="au-side-card">
      <h4>Register snapshot</h4>
      <div class="row"><span class="k">Published entities</span><span class="v"><?php echo (int)$stats['entities']; ?></span></div>
      <div class="row"><span class="k">Brands mapped</span><span class="v"><?php echo (int)$stats['brands']; ?></span></div>
      <div class="row"><span class="k">Licence records</span><span class="v"><?php echo (int)$stats['licences']; ?></span></div>
      <?php if ($stats['last_update']): ?>
        <div class="row"><span class="k">Last update</span><span class="v"><?php echo $esc(date('j M Y', strtotime($stats['last_update']))); ?></span></div>
      <?php endif; ?>
    </div>

    <div class="au-side-cta">
      <h4>Track the changes</h4>
      <p>Licences move, companies migrate, brands change hands. The changelog is the dated record of every verified change.</p>
      <a class="btn" href="<?php echo $changelogUrl; ?>">Open the changelog <?php echo $arrow; ?></a>
    </div>
  </aside>
</section>

</div>
