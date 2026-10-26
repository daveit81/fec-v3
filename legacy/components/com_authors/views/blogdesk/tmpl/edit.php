<?php
/**
 * Blog desk — the editor. Three things to fill (title, text, picture); the
 * "Details" fold holds what is normally derived from the text.
 */
defined('_JEXEC') or die;

$h     = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$ctx   = $this->ctx;
$post  = $this->post;
$f     = $this->form;
$isNew = ($post === null);
$deskUser = !empty($ctx['is_desk']);
$notice = $this->notice;
$saveUrl = JRoute::_('index.php?option=com_authors', false);
$bylineName = !empty($ctx['author']) ? $ctx['author']['name'] : AuthorsHelperBlogdesk::EDITORIAL_ALIAS;
if ($post) $bylineName = (string) $post['created_by_alias'];
$state = (int) $f['state'];
$pubTs = ($post && $post['publish_up'] !== '' && strpos($post['publish_up'], '0000-00-00') !== 0) ? strtotime($post['publish_up'] . ' UTC') : 0;
$statusLabel = $isNew ? 'Not saved yet' : ($state === 0 ? 'Draft' : ($pubTs > time() ? 'Scheduled' : 'Published'));
$statusCls   = $isNew ? 'new' : ($state === 0 ? 'draft' : ($pubTs > time() ? 'sched' : 'live'));
$words = str_word_count(strip_tags((string) $f['body']));
?>
<div class="fecx bdk bdk-editor">

  <header class="bdk-head">
    <div>
      <div class="bdk-eyebrow">
        <a href="<?php echo $h(AuthorsHelperBlogdesk::deskUrl()); ?>">Blog desk</a> · <?php echo $isNew ? 'New post' : 'Edit post'; ?>
      </div>
      <h1><?php echo $isNew ? 'Write' : $h($post['title']); ?></h1>
      <div class="bdk-meta">
        <span class="bdk-badge bdk-badge-<?php echo $statusCls; ?>"><?php echo $statusLabel; ?></span>
        <span>By <?php echo $h($bylineName); ?></span>
        <?php if (!$isNew && $this->lastUrl !== '' && $statusCls === 'live'): ?>
          <a href="<?php echo $h($this->lastUrl); ?>" target="_blank" rel="noopener">View post ↗</a>
        <?php endif; ?>
        <?php if (!$isNew): ?><span class="bdk-dim">/<?php echo $h($post['alias']); ?></span><?php endif; ?>
      </div>
    </div>
  </header>

  <?php if (!empty($notice)): ?>
    <div class="bdk-msg bdk-msg-<?php echo $h($notice['type']); ?>">
      <?php echo $h($notice['text']); ?>
      <?php if (!empty($notice['url']) && $notice['type'] === 'ok'): ?>
        <a href="<?php echo $h($notice['url']); ?>" target="_blank" rel="noopener">Open the post ↗</a>
      <?php endif; ?>
    </div>
  <?php elseif (!$isNew && $this->detected !== ''): ?>
    <div class="bdk-msg bdk-msg-info">Detected from the text: <?php echo $h($this->detected); ?>. Re-saving refreshes it.</div>
  <?php endif; ?>

  <form class="bdk-form" method="post" action="<?php echo $h($saveUrl); ?>" enctype="multipart/form-data" id="bdk-form" autocomplete="off">
    <input type="hidden" name="option" value="com_authors">
    <input type="hidden" name="task" value="blogdesk.save">
    <input type="hidden" name="id" value="<?php echo (int) $f['id']; ?>">
    <input type="hidden" name="<?php echo $h($this->token); ?>" value="1">

    <div class="bdk-cols">
      <div class="bdk-main-col">

        <div class="bdk-field">
          <label for="bdk-title" class="bdk-lbl">Title</label>
          <input type="text" id="bdk-title" name="title" value="<?php echo $h($f['title']); ?>" maxlength="250" required placeholder="Game by Studio: what makes it worth a spin">
          <?php if ($isNew): ?><div class="bdk-hint">The URL is made from the title and never changes afterwards.</div><?php endif; ?>
        </div>

        <div class="bdk-field">
          <div class="bdk-lbl-row">
            <label for="bdk-body" class="bdk-lbl">Text</label>
            <span class="bdk-counter"><span id="bdk-words"><?php echo number_format($words); ?></span> words · <span id="bdk-mode-label">—</span></span>
          </div>
          <textarea id="bdk-body" name="body" rows="28" spellcheck="true" placeholder="Just write. A blank line starts a new paragraph."><?php echo $h($f['body']); ?></textarea>
          <input type="hidden" name="mode" value="auto">
          <details class="bdk-help">
            <summary>How the text is read</summary>
            <ul>
              <li><b>Plain text:</b> a blank line starts a paragraph; <code>## Heading</code> starts a section (three or more sections get a table of contents); <code>**bold**</code>, <code>*italic*</code>; a line starting with <code>- </code> is a list item; a pasted URL becomes a link, or <code>[words](url)</code>.</li>
              <li><b>Pasted HTML</b> is cleaned and kept: paragraphs, headings, bold, links, lists, tables.</li>
              <li>Game facts (provider, release date, RTP, volatility, max win, grid) are read from the text when it states them once, plainly: "96.00% RTP", "high volatility", "30,000x max win", "6x5 grid", "rolled out on October 6, 2026".</li>
            </ul>
          </details>
        </div>

      </div>

      <aside class="bdk-side-col">

        <div class="bdk-card">
          <div class="bdk-card-title">Picture</div>
          <div class="bdk-image<?php echo $this->imageSrc === '' ? ' bdk-image--empty' : ''; ?>" id="bdk-image-box">
            <?php if ($this->imageSrc !== ''): ?>
              <img src="<?php echo $h($this->imageSrc); ?>" alt="" id="bdk-image-preview">
            <?php else: ?>
              <img src="" alt="" id="bdk-image-preview" hidden>
              <span class="bdk-image-empty" id="bdk-image-empty">No picture yet</span>
            <?php endif; ?>
          </div>
          <input type="file" name="image" id="bdk-image" accept="image/jpeg,image/png,image/webp,image/gif">
          <div class="bdk-hint">JPG, PNG or WebP, landscape, at least 800 px wide. Resized to 1600 px and shown as the cover and in the cards.</div>
          <?php if ($this->imageSrc !== ''): ?>
            <label class="bdk-check"><input type="checkbox" name="remove_image" value="1"> Remove the picture</label>
          <?php endif; ?>
        </div>

        <div class="bdk-card">
          <div class="bdk-card-title">Publish</div>
          <div class="bdk-field">
            <label class="bdk-lbl" for="bdk-publish-up">Date</label>
            <input type="datetime-local" id="bdk-publish-up" name="publish_up" value="<?php echo $h($f['publish_up']); ?>">
            <div class="bdk-hint">Leave empty for now. A future date schedules the post.</div>
          </div>
          <!-- One Save button and a Draft checkbox (2.7.70): a site script
               submits this form with form.submit(), which drops the clicked
               button from the POST, so the state cannot ride on a button. A
               checked checkbox is an ordinary field and always arrives. -->
          <label class="bdk-check bdk-draft-check">
            <input type="checkbox" name="task_state" value="draft" id="bdk-draft-box"<?php echo ($state === 0) ? ' checked' : ''; ?>>
            Draft &mdash; keep it off the site
          </label>
          <div class="bdk-buttons">
            <button type="submit" class="bdk-btn bdk-btn-primary" id="bdk-publish"
              data-label-publish="<?php echo ($state === 0 || $isNew) ? 'Publish' : 'Save changes'; ?>"
              data-label-draft="<?php echo ($state === 0 || $isNew) ? 'Save draft' : 'Unpublish to draft'; ?>"><?php echo ($state === 0) ? 'Save draft' : (($isNew) ? 'Publish' : 'Save changes'); ?></button>
          </div>
          <div class="bdk-hint">Tick Draft to save without publishing, or to take a published post offline.</div>
          <a class="bdk-cancel" href="<?php echo $h(AuthorsHelperBlogdesk::deskUrl()); ?>">Back to the list</a>
        </div>

        <details class="bdk-card bdk-details"<?php echo ($f['kind'] !== '' || $f['provider'] !== '' || $f['slot'] !== '') ? ' open' : ''; ?>>
          <summary class="bdk-card-title">Details <span class="bdk-dim">(normally automatic)</span></summary>
          <div class="bdk-field">
            <label class="bdk-lbl" for="bdk-kind">Type</label>
            <select id="bdk-kind" name="kind">
              <option value="">Auto-detect</option>
              <?php foreach ($this->kinds as $k => $label): ?>
                <option value="<?php echo $h($k); ?>"<?php echo $f['kind'] === $k ? ' selected' : ''; ?>><?php echo $h($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="bdk-field">
            <label class="bdk-lbl" for="bdk-provider">Provider</label>
            <select id="bdk-provider" name="provider">
              <option value="">Auto-detect</option>
              <option value="-"<?php echo $f['provider'] === '-' ? ' selected' : ''; ?>>None</option>
              <?php foreach ($this->providers as $v => $label): ?>
                <option value="<?php echo $h($v); ?>"<?php echo $f['provider'] === (string) $v ? ' selected' : ''; ?>><?php echo $h($label); ?></option>
              <?php endforeach; ?>
            </select>
            <div class="bdk-hint">Links the post to the studio page. Auto-detect reads "by Studio" in the title.</div>
          </div>
          <?php if (!empty($this->slots)): ?>
            <div class="bdk-field">
              <label class="bdk-lbl" for="bdk-slot">Verified slot</label>
              <select id="bdk-slot" name="slot">
                <option value="">Auto-detect</option>
                <option value="-"<?php echo $f['slot'] === '-' ? ' selected' : ''; ?>>None</option>
                <?php foreach ($this->slots as $gid => $label): ?>
                  <option value="<?php echo (int) $gid; ?>"<?php echo $f['slot'] === (string) $gid ? ' selected' : ''; ?>><?php echo $h($label); ?></option>
                <?php endforeach; ?>
              </select>
              <select id="bdk-slot-rel" name="slot_rel" class="bdk-sub-select" aria-label="Relation to the slot">
                <option value="">Relation: auto</option>
                <?php foreach ($this->slotRels as $v => $label): ?>
                  <option value="<?php echo $h($v); ?>"<?php echo $f['slot_rel'] === $v ? ' selected' : ''; ?>><?php echo $h($label); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="bdk-hint">Shows the verified RTP box on the post and lists the post on the slot page. Auto-detect finds the game name in the title: the game itself is "this slot", a sequel or a jackpot story is "same family".</div>
            </div>
          <?php endif; ?>
          <?php if ($deskUser): ?>
            <div class="bdk-field">
              <label class="bdk-lbl" for="bdk-byline">Byline</label>
              <select id="bdk-byline" name="byline">
                <option value=""><?php echo $isNew ? 'Default' : 'Keep: ' . $h($bylineName); ?></option>
                <option value="editorial"<?php echo $f['byline'] === 'editorial' ? ' selected' : ''; ?>>FreeExtraChips Editorial</option>
                <?php foreach ($this->bylines as $b): ?>
                  <option value="<?php echo (int) $b['id']; ?>"<?php echo $f['byline'] === (string) $b['id'] ? ' selected' : ''; ?>><?php echo $h($b['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
        </details>

      </aside>
    </div>
  </form>
</div>
