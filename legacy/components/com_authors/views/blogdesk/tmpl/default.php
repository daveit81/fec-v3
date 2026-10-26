<?php
/**
 * Blog desk — the author's posts.
 */
defined('_JEXEC') or die;

$h    = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$ctx  = $this->ctx;
$who  = !empty($ctx['author']) ? $ctx['author']['name'] : 'Editorial desk';
$now  = time();
$kinds = AuthorsHelperBlogdesk::kindOptions();
$filters = array('all' => 'All', 'published' => 'Published', 'scheduled' => 'Scheduled', 'draft' => 'Drafts');
$listUrl = function ($filter, $q = null) use ($h) {
    $u = AuthorsHelperBlogdesk::deskUrl('&filter=' . $filter . ($q !== null && $q !== '' ? '&q=' . urlencode($q) : ''));
    return $h($u);
};
?>
<div class="fecx bdk">

  <header class="bdk-head">
    <div>
      <div class="bdk-eyebrow">Casino Bonus Blog · Blog desk</div>
      <h1><?php echo $h($who); ?></h1>
      <p class="bdk-sub">Your posts, newest first. Write the piece, add a picture, publish: section headings, the game facts box and the rest of the page are built from the text.</p>
    </div>
    <div class="bdk-head-actions">
      <a class="bdk-btn bdk-btn-primary" href="<?php echo $h(AuthorsHelperBlogdesk::editUrl()); ?>">+ New post</a>
      <a class="bdk-btn" href="<?php echo $h(AuthorsHelper::blogIndexUrl()); ?>">View the blog</a>
    </div>
  </header>

  <?php if (!empty($this->notice)): ?>
    <div class="bdk-msg bdk-msg-<?php echo $h($this->notice['type']); ?>"><?php echo $h($this->notice['text']); ?></div>
  <?php endif; ?>

  <form class="bdk-toolbar" method="get" action="<?php echo $h(AuthorsHelperBlogdesk::deskUrl()); ?>">
    <input type="hidden" name="option" value="com_authors">
    <input type="hidden" name="view" value="blogdesk">
    <input type="hidden" name="filter" value="<?php echo $h($this->filter); ?>">
    <div class="bdk-chips">
      <?php foreach ($filters as $k => $label): ?>
        <a class="bdk-chip<?php echo $this->filter === $k ? ' active' : ''; ?>" href="<?php echo $listUrl($k, $this->search); ?>"><?php echo $h($label); ?></a>
      <?php endforeach; ?>
    </div>
    <div class="bdk-search">
      <input type="search" name="q" value="<?php echo $h($this->search); ?>" placeholder="Search titles">
      <button type="submit" class="bdk-btn">Search</button>
    </div>
    <span class="bdk-total"><?php echo number_format((int) $this->total); ?> post<?php echo (int) $this->total === 1 ? '' : 's'; ?></span>
  </form>

  <?php if (empty($this->posts)): ?>
    <div class="bdk-empty">
      <?php echo $this->search !== '' ? 'Nothing matches that search.' : 'No posts yet. Your first one is a click away.'; ?>
    </div>
  <?php else: ?>
    <div class="bdk-list">
      <?php foreach ($this->posts as $p):
        $state = (int) $p['state'];
        $pubTs = strtotime($p['publish_up'] . ' UTC');
        $isSched = ($state === 1 && $pubTs > $now);
        $badge = $state === 0 ? array('draft', 'Draft') : ($isSched ? array('sched', 'Scheduled') : array('live', 'Published'));
        $label = !empty($p['provider_name']) ? $p['provider_name'] : (isset($kinds[$p['kind']]) ? $kinds[$p['kind']] : 'News');
        $kindLabel = isset($kinds[$p['kind']]) ? $kinds[$p['kind']] : 'News';
        $thumb = AuthorsHelper::blogPostThumb($p, 112, 72);
        $editUrl = AuthorsHelperBlogdesk::editUrl((int) $p['id']);
        $viewUrl = AuthorsHelperBlogdesk::postUrl($p);
        $date = AuthorsHelper::blogDate($p['created']);
      ?>
        <div class="bdk-row">
          <a class="bdk-thumb<?php echo $thumb === '' ? ' bdk-thumb--empty' : ''; ?>" href="<?php echo $h($editUrl); ?>" aria-hidden="true"><?php echo $thumb; ?></a>
          <div class="bdk-main">
            <a class="bdk-title" href="<?php echo $h($editUrl); ?>"><?php echo $h($p['title']); ?></a>
            <div class="bdk-meta">
              <span class="bdk-badge bdk-badge-<?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span>
              <span><?php echo $h($label); ?><?php echo $label !== $kindLabel ? ' · ' . $h($kindLabel) : ''; ?></span>
              <?php if ($date !== ''): ?><span><?php echo $h($date); ?></span><?php endif; ?>
              <?php if ((int) $p['hits'] > 0): ?><span><?php echo number_format((int) $p['hits']); ?> views</span><?php endif; ?>
              <?php if (!empty($ctx['is_desk'])): ?><span class="bdk-dim"><?php echo $h($p['created_by_alias']); ?></span><?php endif; ?>
            </div>
          </div>
          <div class="bdk-actions">
            <a class="bdk-btn bdk-btn-small" href="<?php echo $h($editUrl); ?>">Edit</a>
            <?php if ($state === 1 && !$isSched): ?>
              <a class="bdk-btn bdk-btn-small bdk-btn-ghost" href="<?php echo $h($viewUrl); ?>" target="_blank" rel="noopener">View</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($this->pagination && $this->total > count($this->posts)): ?>
      <div class="bdk-pagination"><?php echo $this->pagination->getPagesLinks(); ?></div>
    <?php endif; ?>
  <?php endif; ?>

</div>
