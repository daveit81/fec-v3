<?php
/**
 * Atlas dashboard main template.
 *
 * Server-renders the entity list; taps open the detail panel which is
 * fetched and rendered client-side (assets/dashboard.js). All writes go
 * through AJAX so the page never reloads. Mobile-first, dashx-class UI.
 *
 * Icons: inline SVG, FEC pastello stroke style (house rule — no emoji,
 * no icon fonts anywhere in site-facing markup).
 */
defined('_JEXEC') or die;

$entities = $this->entities;
$stats    = $this->stats;
$search   = htmlspecialchars($this->search, ENT_QUOTES, 'UTF-8');
$type     = $this->type;
$status   = $this->status;
$total    = (int)$this->total;
$byConf   = isset($stats['licences_by_confidence']) ? $stats['licences_by_confidence'] : array();

$typeLabels = array(
    ''                  => 'All types',
    'operator'          => 'Operators',
    'holding'           => 'Holdings',
    'commercial'        => 'Commercial',
    'affiliate_program' => 'Programs',
    'payment_shell'     => 'Payment shells',
    'unknown'           => 'Unknown',
);
$statusLabels = array(
    ''           => 'All statuses',
    'active'     => 'Active',
    'dormant'    => 'Dormant',
    'struck_off' => 'Struck off',
    'dissolved'  => 'Dissolved',
    'unverified' => 'Unverified',
);

function atlx_icon($name)
{
    $paths = array(
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 8h1M14 8h1M9 12h1M14 12h1M9 16h1M14 16h1"/>',
        'shield'   => '<path d="M12 3l7 3v5c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-3z"/>',
        'link'     => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 1 0-5.7-5.7l-1.2 1.2"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 1 0 5.7 5.7l1.2-1.2"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 4 5.7 4 9s-1.5 6.4-4 9c-2.5-2.6-4-5.7-4-9s1.5-6.4 4-9z"/>',
    );
    $d = isset($paths[$name]) ? $paths[$name] : $paths['building'];
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}
?>
<div class="fecx atlx">

  <header class="atlx-head">
    <div class="atlx-head-row">
      <h1><?php echo atlx_icon('globe'); ?> Operator Atlas</h1>
      <div class="atlx-head-actions">
        <button type="button" class="atlx-btn" id="atlx-open-drift">Drift</button>
        <button type="button" class="atlx-btn-add" id="atlx-add-entity">+ Add entity</button>
        <span class="atlx-user">user #<?php echo (int)JFactory::getUser()->id; ?></span>
      </div>
    </div>
    <div class="atlx-stats" role="status">
      <span class="atlx-chip"><?php echo atlx_icon('building'); ?> <strong><?php echo (int)$stats['entities']; ?></strong> entities</span>
      <span class="atlx-chip"><strong><?php echo (int)$stats['published']; ?></strong> published</span>
      <span class="atlx-chip"><?php echo atlx_icon('shield'); ?> <strong><?php echo (int)$stats['licences']; ?></strong> licences
        <em class="atlx-conf">
          <b class="c-conf"><?php echo isset($byConf['confirmed']) ? (int)$byConf['confirmed'] : 0; ?></b> /
          <b class="c-prob"><?php echo isset($byConf['probable']) ? (int)$byConf['probable'] : 0; ?></b> /
          <b class="c-single"><?php echo isset($byConf['single_source']) ? (int)$byConf['single_source'] : 0; ?></b>
        </em>
      </span>
      <span class="atlx-chip"><?php echo atlx_icon('link'); ?> <strong><?php echo (int)$stats['current_operator_links']; ?></strong> current operator links</span>
    </div>
  </header>

  <form class="atlx-filters" method="get" action="" id="atlx-filter-form">
    <input type="hidden" name="option" value="com_atlas">
    <input type="hidden" name="view" value="dashboard">
    <input type="search" name="q" value="<?php echo $search; ?>" placeholder="Search entity, slug or reg number…" autocomplete="off" inputmode="search">
    <select name="type" aria-label="Entity type">
      <?php foreach ($typeLabels as $val => $label): ?>
        <option value="<?php echo $val; ?>"<?php echo $val === $type ? ' selected' : ''; ?>><?php echo $label; ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" aria-label="Entity status">
      <?php foreach ($statusLabels as $val => $label): ?>
        <option value="<?php echo $val; ?>"<?php echo $val === $status ? ' selected' : ''; ?>><?php echo $label; ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="atlx-btn">Filter</button>
  </form>

  <p class="atlx-count"><?php echo count($entities); ?> of <?php echo $total; ?> entities</p>

  <ul class="atlx-list" id="atlx-list">
    <?php foreach ($entities as $e): ?>
      <li class="atlx-row" data-id="<?php echo (int)$e->id; ?>" tabindex="0" role="button">
        <div class="atlx-row-main">
          <span class="atlx-row-name"><?php echo htmlspecialchars($e->name, ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="atlx-row-slug">/<?php echo htmlspecialchars($e->slug, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="atlx-row-meta">
          <span class="atlx-tag t-<?php echo htmlspecialchars($e->entity_type, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($e->entity_type, ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="atlx-tag s-<?php echo htmlspecialchars($e->status, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($e->status, ENT_QUOTES, 'UTF-8'); ?></span>
          <?php if ((int)$e->lic_count): ?><span class="atlx-mini"><?php echo (int)$e->lic_count; ?> lic</span><?php endif; ?>
          <?php if ((int)$e->brand_count): ?><span class="atlx-mini"><?php echo (int)$e->brand_count; ?> brands</span><?php endif; ?>
          <?php if ((int)$e->single_source_count): ?><span class="atlx-mini m-single" title="single_source items attached"><?php echo (int)$e->single_source_count; ?> ss</span><?php endif; ?>
          <?php if ((int)$e->published): ?><span class="atlx-pub">published</span><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
    <?php if (!count($entities)): ?>
      <li class="atlx-empty">No entities match.</li>
    <?php endif; ?>
  </ul>

  <?php if ($total > $this->limit): ?>
  <nav class="atlx-pager">
    <?php
      $base = 'index.php?option=com_atlas&view=dashboard&q=' . urlencode($this->search) . '&type=' . urlencode($type) . '&status=' . urlencode($status);
      if ($this->limitstart > 0):
        $prev = max(0, $this->limitstart - $this->limit); ?>
        <a class="atlx-btn" href="<?php echo JRoute::_($base . '&limitstart=' . $prev); ?>">&larr; Prev</a>
      <?php endif;
      if ($this->limitstart + $this->limit < $total): ?>
        <a class="atlx-btn" href="<?php echo JRoute::_($base . '&limitstart=' . ($this->limitstart + $this->limit)); ?>">Next &rarr;</a>
      <?php endif; ?>
  </nav>
  <?php endif; ?>

  <!-- Detail panel (slide-over; filled client-side) -->
  <section class="atlx-panel" id="atlx-panel" hidden aria-hidden="true">
    <div class="atlx-panel-backdrop" data-close="panel"></div>
    <div class="atlx-panel-inner" role="dialog" aria-labelledby="atlx-panel-title">
      <div class="atlx-panel-head">
        <h2 id="atlx-panel-title">Entity</h2>
        <button type="button" class="atlx-close" data-close="panel" aria-label="Close">&times;</button>
      </div>
      <div class="atlx-panel-body" id="atlx-panel-body"></div>
    </div>
  </section>

  <!-- Generic bottom-sheet for forms (filled client-side) -->
  <div class="atlx-sheet" id="atlx-sheet" hidden aria-hidden="true">
    <div class="atlx-sheet-backdrop" data-close="sheet"></div>
    <div class="atlx-sheet-inner" role="dialog" aria-labelledby="atlx-sheet-title">
      <div class="atlx-sheet-head">
        <h2 id="atlx-sheet-title">Edit</h2>
        <button type="button" class="atlx-close" data-close="sheet" aria-label="Close">&times;</button>
      </div>
      <div class="atlx-sheet-body" id="atlx-sheet-body"></div>
    </div>
  </div>

  <div class="atlx-toast" id="atlx-toast" hidden></div>

</div>
