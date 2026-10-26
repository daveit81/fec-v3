<?php
/**
 * methodologyadmin / edit.php — topic edit form
 */
defined('_JEXEC') or die;

$slug    = (string)$this->editSlug;
$topic   = is_array($this->editTopic) ? $this->editTopic : array();
$authors = is_array($this->authors)   ? $this->authors   : array();

$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$listUrl  = JRoute::_('index.php?option=com_authors&view=methodologyadmin');
$saveUrl  = JRoute::_('index.php?option=com_authors&task=methodologyadmin.save');
$publicUrl = !empty($topic['external_url'])
           ? $topic['external_url']
           : JRoute::_('index.php?option=com_authors&view=methodology&topic=' . $slug);

$status        = isset($topic['status'])            ? (string)$topic['status']            : 'coming_soon';
$title         = isset($topic['title'])             ? (string)$topic['title']             : '';
$tagline       = isset($topic['tagline'])           ? (string)$topic['tagline']           : '';
$shortDesc     = isset($topic['short_description']) ? (string)$topic['short_description'] : '';
$desc          = isset($topic['description'])       ? (string)$topic['description']       : '';
$authorSlug    = isset($topic['author_slug'])       ? (string)$topic['author_slug']       : '';
$reviewerSlug  = isset($topic['reviewer_slug'])     ? (string)$topic['reviewer_slug']     : '';
$version       = isset($topic['version'])           ? (string)$topic['version']           : '';
$lastUpdated   = isset($topic['last_updated'])      ? (string)$topic['last_updated']      : '';
$tmpl          = isset($topic['tmpl'])              ? (string)$topic['tmpl']              : '';
$externalUrl   = isset($topic['external_url'])      ? (string)$topic['external_url']      : '';
$sortOrder     = isset($topic['sort_order'])        ? (int)$topic['sort_order']           : 0;

$today = date('Y-m-d');
?>

<style>
.fecx .mte-wrap { max-width: 820px; margin: 0 auto; padding: 32px 18px 80px; font-family: var(--sans); color: var(--ink-1); }
.fecx .mte-hd { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.fecx .mte-hd h1 { font-family: var(--serif); font-size: 26px; margin: 0; }
.fecx .mte-back { font-size: 13px; color: var(--accent); text-decoration: none; }
.fecx .mte-back:hover { text-decoration: underline; }
.fecx .mte-slug { font-family: var(--mono); font-size: 11px; color: var(--ink-3); }

.fecx .mte-card { background: var(--paper); border: 1px solid var(--rule); border-radius: 14px; padding: 24px 28px; margin-bottom: 18px; }
.fecx .mte-card h2 { font-family: var(--serif); font-size: 16px; margin: 0 0 14px; color: var(--ink-1); }

.fecx .mte-field { display: block; margin-bottom: 16px; }
.fecx .mte-field:last-child { margin-bottom: 0; }
.fecx .mte-label { display: block; font-family: var(--mono); font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 6px; }
.fecx .mte-help { display: block; font-size: 12px; color: var(--ink-3); margin-top: 4px; line-height: 1.4; }

.fecx .mte-input,
.fecx .mte-select,
.fecx .mte-textarea {
  width: 100%; box-sizing: border-box;
  padding: 10px 12px;
  background: white; color: var(--ink-1);
  border: 1px solid var(--rule); border-radius: 8px;
  font: inherit; font-size: 14px;
}
.fecx .mte-input:focus, .fecx .mte-select:focus, .fecx .mte-textarea:focus {
  outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(11, 107, 98, 0.15);
}
.fecx .mte-textarea { resize: vertical; min-height: 70px; font-family: inherit; }

.fecx .mte-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 600px) { .fecx .mte-row { grid-template-columns: 1fr; } }

.fecx .mte-date-group { display: flex; gap: 8px; align-items: stretch; }
.fecx .mte-date-group .mte-input { flex: 1 1 auto; }
.fecx .mte-bump {
  padding: 0 14px;
  background: var(--bg-2); color: var(--ink-1);
  border: 1px solid var(--rule); border-radius: 8px;
  font: inherit; font-size: 12px; font-family: var(--mono);
  cursor: pointer; white-space: nowrap;
}
.fecx .mte-bump:hover { background: white; border-color: var(--accent); color: var(--accent); }

.fecx .mte-actions { display: flex; gap: 12px; align-items: center; padding-top: 8px; }
.fecx .mte-btn-save {
  padding: 10px 22px;
  background: var(--accent); color: white;
  border: none; border-radius: 8px;
  font: inherit; font-size: 14px; font-weight: 600; cursor: pointer;
}
.fecx .mte-btn-save:hover { background: #095853; }
.fecx .mte-btn-cancel { color: var(--ink-3); font-size: 13px; text-decoration: none; }
.fecx .mte-btn-cancel:hover { color: var(--ink-1); }
.fecx .mte-view-link { margin-left: auto; font-size: 12px; color: var(--accent); text-decoration: none; }
.fecx .mte-view-link:hover { text-decoration: underline; }
</style>

<div class="fecx">
<div class="mte-wrap">

  <div class="mte-hd">
    <h1>Edit: <?php echo $h($title); ?></h1>
    <a class="mte-back" href="<?php echo $listUrl; ?>">← Back to list</a>
  </div>

  <form method="post" action="<?php echo $saveUrl; ?>" id="mte-form">
    <input type="hidden" name="slug" value="<?php echo $h($slug); ?>">
    <?php echo JHTML::_('form.token'); ?>

    <!-- ============ Identity ============ -->
    <div class="mte-card">
      <h2>Identity</h2>

      <label class="mte-field">
        <span class="mte-label">Slug</span>
        <input type="text" class="mte-input" value="<?php echo $h($slug); ?>" disabled>
        <span class="mte-help">URL path: <code>/methodology/<?php echo $h($slug); ?></code>. Immutable.</span>
      </label>

      <label class="mte-field">
        <span class="mte-label">Title</span>
        <input type="text" name="title" class="mte-input" required maxlength="255"
               value="<?php echo $h($title); ?>">
        <span class="mte-help">Used as the H1 on the page and the title in the methodology hub.</span>
      </label>

      <label class="mte-field">
        <span class="mte-label">Tagline</span>
        <textarea name="tagline" class="mte-textarea" rows="2"><?php echo $h($tagline); ?></textarea>
        <span class="mte-help">One-line subtitle under the H1. Keep it short and concrete.</span>
      </label>
    </div>

    <!-- ============ Status & visibility ============ -->
    <div class="mte-card">
      <h2>Status &amp; visibility</h2>

      <div class="mte-row">
        <label class="mte-field">
          <span class="mte-label">Status</span>
          <select name="status" class="mte-select">
            <option value="live"        <?php if ($status === 'live')        echo 'selected'; ?>>Live — page renders publicly</option>
            <option value="coming_soon" <?php if ($status === 'coming_soon') echo 'selected'; ?>>Coming soon — placeholder card only</option>
            <option value="external"    <?php if ($status === 'external')    echo 'selected'; ?>>External — hub card links elsewhere</option>
            <option value="draft"       <?php if ($status === 'draft')       echo 'selected'; ?>>Draft — invisible on hub, 404 on view</option>
          </select>
        </label>

        <label class="mte-field">
          <span class="mte-label">Sort order</span>
          <input type="number" name="sort_order" class="mte-input" min="0" max="999" value="<?php echo (int)$sortOrder; ?>">
          <span class="mte-help">Lower = earlier on the hub grid.</span>
        </label>
      </div>

      <?php if ($status === 'external' || $externalUrl !== ''): ?>
      <label class="mte-field">
        <span class="mte-label">External URL</span>
        <input type="text" name="external_url" class="mte-input" maxlength="255"
               value="<?php echo $h($externalUrl); ?>" placeholder="/who-is-frank">
        <span class="mte-help">For status=external. The hub card links here instead of rendering a methodology page.</span>
      </label>
      <?php else: ?>
        <input type="hidden" name="external_url" value="">
      <?php endif; ?>
    </div>

    <!-- ============ Byline ============ -->
    <div class="mte-card">
      <h2>Byline</h2>

      <div class="mte-row">
        <label class="mte-field">
          <span class="mte-label">Author</span>
          <select name="author_slug" class="mte-select">
            <option value="">— no author —</option>
            <?php foreach ($authors as $aSlug => $a): ?>
              <option value="<?php echo $h($aSlug); ?>" <?php if ($authorSlug === $aSlug) echo 'selected'; ?>>
                <?php echo $h($a['name']); ?>
                <?php if (!empty($a['role'])): ?>(<?php echo $h($a['role']); ?>)<?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="mte-field">
          <span class="mte-label">Reviewer</span>
          <select name="reviewer_slug" class="mte-select">
            <option value="">— no reviewer —</option>
            <?php foreach ($authors as $aSlug => $a): ?>
              <option value="<?php echo $h($aSlug); ?>" <?php if ($reviewerSlug === $aSlug) echo 'selected'; ?>>
                <?php echo $h($a['name']); ?>
                <?php if (!empty($a['role'])): ?>(<?php echo $h($a['role']); ?>)<?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </div>

    <!-- ============ Version & date ============ -->
    <div class="mte-card">
      <h2>Version &amp; last update</h2>

      <div class="mte-row">
        <label class="mte-field">
          <span class="mte-label">Version</span>
          <input type="text" name="version" class="mte-input" maxlength="16"
                 value="<?php echo $h($version); ?>" placeholder="v1">
          <span class="mte-help">Free text — typically <code>v1</code>, <code>v2</code>, etc.</span>
        </label>

        <label class="mte-field">
          <span class="mte-label">Last updated</span>
          <div class="mte-date-group">
            <input type="date" name="last_updated" id="mte-last-updated" class="mte-input"
                   value="<?php echo $h($lastUpdated); ?>">
            <button type="button" class="mte-bump" onclick="mteBumpToToday()">Today</button>
          </div>
          <span class="mte-help">Date stamped on the page byline. Click Today to bump after an edit.</span>
        </label>
      </div>
    </div>

    <!-- ============ Meta & template ============ -->
    <div class="mte-card">
      <h2>Meta &amp; template</h2>

      <label class="mte-field">
        <span class="mte-label">Short description</span>
        <textarea name="short_description" class="mte-textarea" rows="2"><?php echo $h($shortDesc); ?></textarea>
        <span class="mte-help">Used on the methodology hub card. Aim for 120-160 chars.</span>
      </label>

      <label class="mte-field">
        <span class="mte-label">Description (meta description)</span>
        <textarea name="description" class="mte-textarea" rows="3"><?php echo $h($desc); ?></textarea>
        <span class="mte-help">Used in <code>&lt;meta name="description"&gt;</code> and in JSON-LD. 150-200 chars ideal.</span>
      </label>

      <label class="mte-field">
        <span class="mte-label">Template suffix</span>
        <input type="text" name="tmpl" class="mte-input" maxlength="64"
               value="<?php echo $h($tmpl); ?>" placeholder="editorial-standards">
        <span class="mte-help">Filename suffix in <code>tmpl/default_&lt;suffix&gt;.php</code>. Leave blank for status=external.</span>
      </label>
    </div>

    <!-- ============ Actions ============ -->
    <div class="mte-actions">
      <button type="submit" class="mte-btn-save">Save changes</button>
      <a class="mte-btn-cancel" href="<?php echo $listUrl; ?>">Cancel</a>
      <?php if ($status === 'live' || $externalUrl !== ''): ?>
        <a class="mte-view-link" href="<?php echo $publicUrl; ?>" target="_blank" rel="noopener">View live page →</a>
      <?php endif; ?>
    </div>
  </form>

</div>
</div>

<script>
function mteBumpToToday() {
  var d = new Date();
  var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
  var iso = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  var el = document.getElementById('mte-last-updated');
  if (el) el.value = iso;
}
</script>
