<?php
/**
 * methodologyadmin / default.php — topic list
 */
defined('_JEXEC') or die;

$topics  = is_array($this->topics)  ? $this->topics  : array();
$authors = is_array($this->authors) ? $this->authors : array();

$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$statusBadgeClass = function ($s) {
    switch ($s) {
        case 'live':         return 'mta-badge mta-badge--live';
        case 'coming_soon':  return 'mta-badge mta-badge--coming';
        case 'external':     return 'mta-badge mta-badge--ext';
        case 'draft':        return 'mta-badge mta-badge--draft';
        default:             return 'mta-badge';
    }
};

$publicUrlFor = function ($slug, $topic) {
    if (!empty($topic['external_url'])) return $topic['external_url'];
    // Route by kind: page topics live under the page view (their own SEF URL
    // and template), methodology topics under /methodology/. Defaulting absent
    // kind to methodology keeps pre-2.7.38 rows working.
    $kind = (isset($topic['kind']) && $topic['kind'] !== '') ? $topic['kind'] : 'methodology';
    if ($kind === 'page') {
        return JRoute::_('index.php?option=com_authors&view=page&slug=' . $slug);
    }
    return JRoute::_('index.php?option=com_authors&view=methodology&topic=' . $slug);
};

$editUrlFor = function ($slug) {
    return JRoute::_('index.php?option=com_authors&view=methodologyadmin&edit=' . $slug);
};

$dashboardUrl = JRoute::_('index.php?option=com_authors&view=dashboard');
?>

<style>
/* Tokens reuse from fec-edition; this file scopes to .fecx .mta to avoid bleed. */
.fecx .mta-wrap { max-width: 1100px; margin: 0 auto; padding: 32px 18px 80px; font-family: var(--sans); color: var(--ink-1); }
.fecx .mta-hd { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 8px; }
.fecx .mta-hd h1 { font-family: var(--serif); font-size: 28px; margin: 0; }
.fecx .mta-hd .mta-back { font-size: 13px; color: var(--accent); text-decoration: none; }
.fecx .mta-hd .mta-back:hover { text-decoration: underline; }
.fecx .mta-sub { color: var(--ink-3); font-size: 14px; margin: 0 0 24px; max-width: 70ch; }

.fecx .mta-flash { padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
.fecx .mta-flash--ok  { background: #e9f5e6; color: #1f5a2c; border: 1px solid #c4e6c0; }
.fecx .mta-flash--err { background: #fdecea; color: #7a1f1f; border: 1px solid #f3c5c0; }

.fecx .mta-table { background: var(--paper); border: 1px solid var(--rule); border-radius: 14px; overflow: hidden; }
.fecx .mta-row { display: grid; grid-template-columns: 1fr 110px 180px 130px 100px; gap: 18px; padding: 16px 20px; border-top: 1px solid var(--rule-2); align-items: center; font-size: 13.5px; }
.fecx .mta-row:first-child { border-top: 0; background: var(--bg-2); font-family: var(--mono); font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink-3); }
.fecx .mta-row--head { font-weight: 600; }
.fecx .mta-title b { display: block; font-family: var(--serif); font-size: 16px; margin-bottom: 2px; color: var(--ink-1); }
.fecx .mta-slug { font-family: var(--mono); font-size: 11px; color: var(--ink-3); }
.fecx .mta-byline { font-size: 12px; color: var(--ink-2); }
.fecx .mta-byline span { display: block; }

.fecx .mta-badge { display: inline-block; padding: 3px 8px; font-family: var(--mono); font-size: 10px; letter-spacing: 0.06em; text-transform: uppercase; border-radius: 6px; border: 1px solid; }
.fecx .mta-badge--live   { background: #e9f5e6; border-color: #c4e6c0; color: #1f5a2c; }
.fecx .mta-badge--coming { background: #fef5e1; border-color: #f0d8a0; color: #7a5a14; }
.fecx .mta-badge--ext    { background: #e8f0fb; border-color: #c5d6ee; color: #234277; }
.fecx .mta-badge--draft  { background: #f0f0f0; border-color: #d0d0d0; color: #555; }

.fecx .mta-updated { font-family: var(--mono); font-size: 11px; color: var(--ink-2); }

.fecx .mta-actions a { display: inline-block; padding: 6px 12px; border-radius: 8px; font-size: 12px; text-decoration: none; margin-right: 4px; }
.fecx .mta-actions .mta-btn-edit { background: var(--accent); color: white; }
.fecx .mta-actions .mta-btn-edit:hover { background: #095853; }
.fecx .mta-actions .mta-btn-view { color: var(--accent); border: 1px solid var(--rule); }
.fecx .mta-actions .mta-btn-view:hover { background: var(--bg-2); }

@media (max-width: 720px) {
  .fecx .mta-row { grid-template-columns: 1fr; gap: 8px; }
  .fecx .mta-row:first-child { display: none; }
  .fecx .mta-row { padding: 14px; border-top: 1px solid var(--rule-2); }
}
.fecx .mta-section-title { font-size: 15px; font-weight: 600; color: var(--ink); margin: 0 0 10px; }
.fecx .mta-section-note { font-size: 13px; color: var(--ink-3); margin: 0 0 12px; max-width: 640px; line-height: 1.5; }
</style>

<div class="fecx">
<div class="mta-wrap">

  <div class="mta-hd">
    <h1>Methodology pages</h1>
    <a class="mta-back" href="<?php echo $dashboardUrl; ?>">← Dashboard</a>
  </div>
  <p class="mta-sub">
    Edit metadata for the trust-hub methodology pages: status, byline (author + reviewer), version,
    last-updated date, and meta description. Body content (the prose sections on each page) is in
    the template file and remains editable via FTP. Saving here auto-bumps the database
    <code>updated_at</code> stamp.
  </p>

  <?php if ($this->saved): ?>
    <div class="mta-flash mta-flash--ok">Saved.</div>
  <?php endif; ?>
  <?php if ($this->error !== ''): ?>
    <div class="mta-flash mta-flash--err"><?php echo $h($this->error); ?></div>
  <?php endif; ?>

  <?php
    // Split topics by kind so the two types — which have different public URLs
    // and templates — are managed in clearly separated sections.
    $methodologyTopics = array();
    $pageTopics        = array();
    foreach ($topics as $slug => $t) {
        $kind = (isset($t['kind']) && $t['kind'] !== '') ? $t['kind'] : 'methodology';
        if ($kind === 'page') { $pageTopics[$slug] = $t; }
        else                  { $methodologyTopics[$slug] = $t; }
    }

    // Reusable row renderer (closure) so both sections share identical markup.
    $renderRow = function ($slug, $t) use ($h, $authors, $statusBadgeClass, $editUrlFor, $publicUrlFor) {
        $authorName = '';
        $reviewerName = '';
        if (!empty($t['author_slug']) && isset($authors[$t['author_slug']])) {
            $authorName = $authors[$t['author_slug']]['name'];
        }
        if (!empty($t['reviewer_slug']) && isset($authors[$t['reviewer_slug']])) {
            $reviewerName = $authors[$t['reviewer_slug']]['name'];
        }
        $lastUpd = !empty($t['last_updated']) ? date('j M Y', strtotime($t['last_updated'])) : '—';
        ?>
        <div class="mta-row">
          <div class="mta-title">
            <b><?php echo $h($t['title']); ?></b>
            <span class="mta-slug">/<?php echo $h($slug); ?>
              <?php if (!empty($t['version'])): ?> · <?php echo $h($t['version']); ?><?php endif; ?>
            </span>
          </div>
          <div>
            <span class="<?php echo $statusBadgeClass($t['status']); ?>">
              <?php echo $h(str_replace('_', ' ', $t['status'])); ?>
            </span>
          </div>
          <div class="mta-byline">
            <?php if ($authorName !== ''): ?>
              <span>By <?php echo $h($authorName); ?></span>
            <?php else: ?>
              <span style="color: var(--ink-3);">— no author —</span>
            <?php endif; ?>
            <?php if ($reviewerName !== ''): ?>
              <span style="color: var(--ink-3);">rev. <?php echo $h($reviewerName); ?></span>
            <?php endif; ?>
          </div>
          <div class="mta-updated"><?php echo $h($lastUpd); ?></div>
          <div class="mta-actions">
            <a class="mta-btn-edit" href="<?php echo $editUrlFor($slug); ?>">Edit</a>
            <a class="mta-btn-view" href="<?php echo $publicUrlFor($slug, $t); ?>" target="_blank" rel="noopener">View</a>
          </div>
        </div>
        <?php
    };
  ?>

  <h2 class="mta-section-title">Methodology topics</h2>
  <div class="mta-table">
    <div class="mta-row mta-row--head">
      <div>Topic</div>
      <div>Status</div>
      <div>Byline</div>
      <div>Last updated</div>
      <div>Actions</div>
    </div>
    <?php foreach ($methodologyTopics as $slug => $t) { $renderRow($slug, $t); } ?>
    <?php if (empty($methodologyTopics)): ?>
      <div class="mta-row">
        <div style="grid-column: 1 / -1; padding: 24px 0; text-align: center; color: var(--ink-3);">
          No methodology topics in the registry yet.
        </div>
      </div>
    <?php endif; ?>
  </div>

  <h2 class="mta-section-title" style="margin-top: 32px;">Editorial pages</h2>
  <p class="mta-section-note">
    Trust-hub and public-service pages (responsible gambling, disclosures, about). Same metadata
    editing as methodology topics; their body content lives in each page's template file.
  </p>
  <div class="mta-table">
    <div class="mta-row mta-row--head">
      <div>Page</div>
      <div>Status</div>
      <div>Byline</div>
      <div>Last updated</div>
      <div>Actions</div>
    </div>
    <?php foreach ($pageTopics as $slug => $t) { $renderRow($slug, $t); } ?>
    <?php if (empty($pageTopics)): ?>
      <div class="mta-row">
        <div style="grid-column: 1 / -1; padding: 24px 0; text-align: center; color: var(--ink-3);">
          No editorial pages yet. Apply migration <code>2.7.38.sql</code> to bring the
          page topics (responsible-gambling, affiliate-disclosure, about) into the registry.
        </div>
      </div>
    <?php endif; ?>
  </div>

</div>
</div>
