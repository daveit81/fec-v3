<?php
/**
 * Public template — operator profile. fecv2 design system: au-hero card,
 * stats strip, me-content + au-sidebar. Data arrives pre-filtered
 * (published entity, no single_source rows, no editor notes).
 */
defined('_JEXEC') or die;

$e        = $this->entity;
$licences = $this->licences;
$brands   = $this->brands;
$events   = $this->events;
$sources  = $this->sources;

$esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$arrow = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"></path></svg>';

// Review URL builder: core content route helper with a safe fallback.
$routeHelper = JPATH_SITE . '/components/com_content/helpers/route.php';
if (file_exists($routeHelper)) {
    require_once $routeHelper;
}
$reviewUrl = function ($b) {
    if (class_exists('ContentHelperRoute')) {
        $slug = $b->review_alias ? ($b->contentid . ':' . $b->review_alias) : $b->contentid;
        return JRoute::_(ContentHelperRoute::getArticleRoute($slug, (int)$b->catid));
    }
    return JRoute::_('index.php?option=com_content&view=article&id=' . (int)$b->contentid);
};

$currentOps  = array();
$currentNet  = array();
$currentRel  = array();
$closedEras  = array();
foreach ($brands as $b) {
    if ($b->date_to) { $closedEras[] = $b; }
    elseif ($b->relation_type === 'operator') { $currentOps[] = $b; }
    elseif ($b->relation_type === 'network')  { $currentNet[] = $b; }
    else { $currentRel[] = $b; }
}

// Umbrella entities (programs, commercial layers) do not operate casinos:
// their members are network relations and the page speaks that language.
$isUmbrella = in_array($e->entity_type, array('affiliate_program', 'commercial'), true);

$relLabels = array(
    'operator'          => 'Operator',
    'owner'             => 'Owner',
    'network'           => 'Affiliate network',
    'platform_provider' => 'Platform provider',
);

$indexUrl     = atlas_route('index.php?option=com_atlas&view=operators');
$changelogUrl = atlas_route('index.php?option=com_atlas&view=changelog');

$tagline = trim((string)$e->public_notes);
if ($tagline === '') {
    $tagline = 'Registration, licences and the casino brands this entity operates, with sourced confidence grades and full era history.';
}
?>
<div class="fecx me-page atlp">

<!-- ========================== HERO ========================== -->
<section class="au-hero">
  <div class="wrap">
    <div class="me-hero-card">
      <div class="me-hero-body">
        <div class="eyebrow"><a href="<?php echo $indexUrl; ?>">Operator Atlas</a></div>
        <h1 class="me-title"><?php echo $esc($e->name); ?></h1>
        <p class="me-tagline"><?php echo $esc($tagline); ?></p>
        <p class="atlp-badges">
          <span class="tag tag-info"><?php echo $esc(AtlasModelPublicbase::typeLabel($e->entity_type)); ?></span>
          <?php if ($e->status === 'active'): ?>
            <span class="tag tag-ok">Active</span>
          <?php else: ?>
            <span class="tag tag-warn"><?php echo $esc(str_replace('_', ' ', $e->status)); ?><?php echo $e->status_date ? ' &mdash; ' . $esc($e->status_date) : ''; ?></span>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <div class="au-stats-strip">
      <div class="stat">
        <div class="num"><?php echo $isUmbrella ? count($currentNet) : count($currentOps); ?></div>
        <div class="lbl"><?php echo $isUmbrella ? 'Brands in the program today' : 'Brands operated today'; ?></div>
      </div>
      <div class="stat">
        <div class="num"><?php echo count($licences); ?></div>
        <div class="lbl">Licence records</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo count($closedEras); ?></div>
        <div class="lbl">Past eras on record</div>
      </div>
      <div class="stat">
        <div class="num"><?php echo $e->last_verified ? $esc(date('j M Y', strtotime($e->last_verified))) : '&mdash;'; ?></div>
        <div class="lbl">Last verified</div>
      </div>
    </div>
  </div>
</section>

<!-- ========================== MAIN ========================== -->
<section class="au-main wrap">
  <article class="me-content">

    <h2 id="registration">Registration</h2>
    <dl class="atlp-kv">
      <?php if ($e->jurisdiction): ?>
        <div><dt>Jurisdiction</dt><dd><?php echo $esc(AtlasModelPublicbase::jurisdictionLabel($e->jurisdiction)); ?></dd></div>
      <?php endif; ?>
      <?php if ($e->reg_number): ?>
        <div><dt>Registration number</dt><dd><code><?php echo $esc($e->reg_number); ?></code></dd></div>
      <?php endif; ?>
      <?php if ($e->reg_address): ?>
        <div><dt>Registered address</dt><dd><?php echo $esc($e->reg_address); ?></dd></div>
      <?php endif; ?>
      <?php if ($e->status !== 'active' && $e->status_date): ?>
        <div><dt>Status since</dt><dd><?php echo $esc($e->status_date); ?></dd></div>
      <?php endif; ?>
    </dl>
    <?php if ($e->successor || count($e->predecessors)): ?>
      <p class="atlp-lineage">
        <?php if (count($e->predecessors)): ?>
          Continues from:
          <?php $parts = array(); foreach ($e->predecessors as $p) {
              $parts[] = (int)$p->published
                ? '<a href="' . atlas_route('index.php?option=com_atlas&view=operator&slug=' . $p->slug) . '">' . $esc($p->name) . '</a>'
                : $esc($p->name);
          } echo implode(', ', $parts); ?>.
        <?php endif; ?>
        <?php if ($e->successor): ?>
          Succeeded by:
          <?php echo (int)$e->successor->published
            ? '<a href="' . atlas_route('index.php?option=com_atlas&view=operator&slug=' . $e->successor->slug) . '">' . $esc($e->successor->name) . '</a>'
            : $esc($e->successor->name); ?>.
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <h2 id="licences">Licences</h2>
    <?php if (!count($licences)): ?>
      <p class="atlp-empty">No licence has met the publication bar for this entity yet.</p>
    <?php else: ?>
      <?php foreach ($licences as $l): ?>
        <div class="atlp-item">
          <p class="atlp-item-head">
            <strong><?php echo $esc($l->regulator); ?></strong>
            <?php if ($l->licence_number): ?><code><?php echo $esc($l->licence_number); ?></code><?php endif; ?>
            <span class="tag <?php echo $l->status === 'active' ? 'tag-ok' : 'tag-warn'; ?>"><?php echo $esc($l->status); ?></span>
          </p>
          <p class="atlp-item-sub">
            <?php
              $bits = array();
              if ($l->licence_type) $bits[] = $esc($l->licence_type);
              if ($l->valid_from)   $bits[] = 'from ' . $esc($l->valid_from);
              if ($l->valid_to)     $bits[] = 'to ' . $esc($l->valid_to);
              $bits[] = $esc(AtlasModelPublicbase::$confidenceLabels[$l->confidence]);
              echo implode(' &middot; ', $bits);
            ?>
          </p>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <h2 id="brands">Brands</h2>
    <?php if ($isUmbrella): ?>
      <?php if (count($currentNet)): ?>
        <h3>In this program today</h3>
        <p class="atlp-hint">Program membership is a commercial relation: each brand is run by its own certified operating company, shown on the brand review.</p>
        <ul class="atlp-brandlist">
          <?php foreach ($currentNet as $b): ?>
            <li>
              <a href="<?php echo $reviewUrl($b); ?>"><?php echo $esc($b->review_title); ?></a>
              <span class="atlp-mini"><?php echo $esc(AtlasModelPublicbase::$confidenceLabels[$b->confidence]); ?><?php echo $b->date_from ? ' &middot; since ' . $esc($b->date_from) : ''; ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if (count($currentOps) || count($currentRel)): ?>
        <h3>Other current relations</h3>
        <ul class="atlp-brandlist">
          <?php foreach (array_merge($currentOps, $currentRel) as $b): ?>
            <li>
              <a href="<?php echo $reviewUrl($b); ?>"><?php echo $esc($b->review_title); ?></a>
              <span class="atlp-mini"><?php echo $esc($relLabels[$b->relation_type]); ?> &middot; <?php echo $esc(AtlasModelPublicbase::$confidenceLabels[$b->confidence]); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php else: ?>
      <?php if (count($currentOps)): ?>
        <h3>Operated today</h3>
        <ul class="atlp-brandlist">
          <?php foreach ($currentOps as $b): ?>
            <li>
              <a href="<?php echo $reviewUrl($b); ?>"><?php echo $esc($b->review_title); ?></a>
              <span class="atlp-mini"><?php echo $esc(AtlasModelPublicbase::$confidenceLabels[$b->confidence]); ?><?php echo $b->date_from ? ' &middot; since ' . $esc($b->date_from) : ''; ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if (count($currentNet) || count($currentRel)): ?>
        <h3>Other current relations</h3>
        <ul class="atlp-brandlist">
          <?php foreach (array_merge($currentNet, $currentRel) as $b): ?>
            <li>
              <a href="<?php echo $reviewUrl($b); ?>"><?php echo $esc($b->review_title); ?></a>
              <span class="atlp-mini"><?php echo $esc($relLabels[$b->relation_type]); ?> &middot; <?php echo $esc(AtlasModelPublicbase::$confidenceLabels[$b->confidence]); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (count($closedEras)): ?>
      <h3>Past eras</h3>
      <p class="atlp-hint">Ownership history is never deleted: a closed era means the brand moved on, not that the past stopped mattering.</p>
      <ul class="atlp-brandlist atlp-closed">
        <?php foreach ($closedEras as $b): ?>
          <li>
            <a href="<?php echo $reviewUrl($b); ?>"><?php echo $esc($b->review_title); ?></a>
            <span class="atlp-mini"><?php echo $esc($relLabels[$b->relation_type]); ?><?php echo $b->date_from ? ' &middot; from ' . $esc($b->date_from) : ''; ?> &middot; until <?php echo $esc($b->date_to); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if (!count($brands)): ?>
      <p class="atlp-empty">No brand link has met the publication bar for this entity yet.</p>
    <?php endif; ?>

    <?php if (count($events)): ?>
    <h2 id="history">History</h2>
    <ul class="atlp-timeline">
      <?php foreach ($events as $ev): ?>
        <li>
          <span class="atlp-tl-date"><?php echo $esc($ev->event_date); ?></span>
          <span class="atlp-tl-body">
            <strong><?php echo $esc($ev->title); ?></strong>
            <?php if ($ev->description): ?><br><?php echo $esc($ev->description); ?><?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if (count($sources)): ?>
    <h2 id="sources">Sources</h2>
    <ul class="atlp-sources">
      <?php foreach ($sources as $s): ?>
        <li>
          <?php if ($s->url): ?>
            <a href="<?php echo $esc($s->url); ?>" target="_blank" rel="noopener"><?php echo $esc($s->description); ?></a>
          <?php else: ?>
            <?php echo $esc($s->description); ?>
          <?php endif; ?>
          <?php if ($s->retrieved_at): ?><span class="atlp-mini">retrieved <?php echo $esc(substr($s->retrieved_at, 0, 10)); ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

  </article>

  <aside class="au-sidebar">
    <div class="au-side-card">
      <h4>What this page covers</h4>
      <nav class="me-toc">
        <a href="#registration">Registration</a>
        <a href="#licences">Licences</a>
        <a href="#brands">Brands</a>
        <?php if (count($events)): ?><a href="#history">History</a><?php endif; ?>
        <?php if (count($sources)): ?><a href="#sources">Sources</a><?php endif; ?>
      </nav>
    </div>

    <div class="au-side-card">
      <h4>Entity snapshot</h4>
      <?php if ($e->jurisdiction): ?>
        <div class="row"><span class="k">Jurisdiction</span><span class="v"><?php echo $esc(AtlasModelPublicbase::jurisdictionLabel($e->jurisdiction)); ?></span></div>
      <?php endif; ?>
      <?php if ($e->reg_number): ?>
        <div class="row"><span class="k">Reg number</span><span class="v"><?php echo $esc($e->reg_number); ?></span></div>
      <?php endif; ?>
      <div class="row"><span class="k">Status</span><span class="v"><?php echo $esc(str_replace('_', ' ', $e->status)); ?></span></div>
      <div class="row"><span class="k">Brands today</span><span class="v"><?php echo $isUmbrella ? count($currentNet) : count($currentOps); ?></span></div>
      <?php if ($e->last_verified): ?>
        <div class="row"><span class="k">Last verified</span><span class="v"><?php echo $esc(date('j M Y', strtotime($e->last_verified))); ?></span></div>
      <?php endif; ?>
    </div>

    <?php if ($this->cluster): ?>
    <div class="au-side-card">
      <h4>Group view</h4>
      <p style="font-size:.85rem;color:#6b7280;line-height:1.55;margin:0 0 10px">
        Every <?php echo $esc($this->cluster->label); ?> brand side by side: shared terms,
        payout behaviour and reputation across the estate.
      </p>
      <a href="/online-casinos/clusters/<?php echo $esc($this->cluster->slug); ?>" style="font-weight:600">
        Open the cluster page <?php echo $arrow; ?>
      </a>
    </div>
    <?php endif; ?>

    <div class="au-side-cta">
      <h4>Spotted a change?</h4>
      <p>Structures move faster than any register. Verified changes land in the Atlas changelog with date and rationale.</p>
      <a class="btn" href="<?php echo $changelogUrl; ?>">Open the changelog <?php echo $arrow; ?></a>
    </div>
  </aside>
</section>

</div>
