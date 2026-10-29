<?php
/**
 * Public template — Atlas changelog. fecv2 design system.
 */
defined('_JEXEC') or die;

$events = $this->events;
$esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$arrow = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"></path></svg>';
$indexUrl = atlas_route('index.php?option=com_atlas&view=operators');
?>
<div class="fecx me-page atlp">

<!-- ========================== HERO ========================== -->
<section class="au-hero">
  <div class="wrap">
    <div class="me-hero-card">
      <div class="me-hero-body">
        <div class="eyebrow"><a href="<?php echo $indexUrl; ?>">Operator Atlas</a></div>
        <h1 class="me-title">Atlas Changelog</h1>
        <p class="me-tagline">
          Corporate structures move: licences are granted and revoked, entities migrate
          jurisdictions, companies get struck off registries, brands change hands. This is the
          dated record of every material change the desk has verified and published.
        </p>
      </div>
    </div>

    <div class="au-stats-strip">
      <div class="stat">
        <div class="num"><?php echo count($events); ?></div>
        <div class="lbl">Published changes</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo count($events) ? $esc(date('j M Y', strtotime($events[0]->event_date))) : '&mdash;'; ?></div>
        <div class="lbl">Most recent</div>
      </div>
    </div>
  </div>
</section>

<!-- ========================== MAIN ========================== -->
<section class="au-main wrap">
  <article class="me-content">

    <h2 id="log">The log</h2>
    <?php if (!count($events)): ?>
      <p class="atlp-empty">No published changes yet &mdash; the register is young. Watch this space.</p>
    <?php else: ?>
      <ul class="atlp-timeline atlp-timeline-global">
        <?php foreach ($events as $ev): ?>
          <li>
            <span class="atlp-tl-date"><?php echo $esc($ev->event_date); ?></span>
            <span class="atlp-tl-body">
              <a href="<?php echo atlas_route('index.php?option=com_atlas&view=operator&slug=' . $ev->entity_slug); ?>"><?php echo $esc($ev->entity_name); ?></a>
              &mdash; <strong><?php echo $esc($ev->title); ?></strong>
              <?php if ($ev->description): ?><br><?php echo $esc($ev->description); ?><?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

  </article>

  <aside class="au-sidebar">
    <div class="au-side-card">
      <h4>About this log</h4>
      <p style="font-size:.85rem;color:#6b7280;line-height:1.55;margin:0">
        Every entry is verified against primary sources before publication. Findings resting on a
        single unconfirmed source never appear here.
      </p>
    </div>

    <div class="au-side-cta">
      <h4>Back to the register</h4>
      <p>Browse the full map of operators, holdings, licences and brand ownership.</p>
      <a class="btn" href="<?php echo $indexUrl; ?>">Open the Atlas <?php echo $arrow; ?></a>
    </div>
  </aside>
</section>

</div>
