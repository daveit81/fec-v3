<?php
/**
 * 403 — blog desk. A guest gets the login link (the desk is the normal
 * front-end login, nothing else); a logged-in user without a profile or
 * desk rights gets a dead end.
 */
defined('_JEXEC') or die;
$ctx = $this->ctx;
?>
<div class="fecx bdk">
  <div class="bdk-403">
    <div class="bdk-eyebrow">Casino Bonus Blog</div>
    <h1>Blog desk</h1>
    <?php if (empty($ctx['logged'])): ?>
      <p>Log in with your FreeExtraChips account to write and edit your posts.</p>
      <p><a class="bdk-btn" href="<?php echo htmlspecialchars(AuthorsHelperBlogdesk::loginUrl(), ENT_QUOTES, 'UTF-8'); ?>">Log in</a></p>
    <?php else: ?>
      <p>This account has no author profile on the blog. Ask the editorial desk to link it.</p>
      <p><a href="<?php echo JURI::root(true); ?>/">Back to homepage</a></p>
    <?php endif; ?>
  </div>
</div>
