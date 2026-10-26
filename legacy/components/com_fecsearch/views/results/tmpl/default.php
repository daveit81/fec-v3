<?php
defined('_JEXEC') or die;
$q = isset($this->q) ? (string)$this->q : '';
?>
<div class="fec-sr" data-fecsearch data-q="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
  <h1 class="fec-sr__h1">Search results</h1>
  <?php if ($q !== ''): ?>
    <div class="fec-sr__sub">Query: <strong><?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?></strong></div>
  <?php endif; ?>

  <div class="fec-sr__tabs" role="tablist" aria-label="Search tabs">
    <button type="button" class="fec-sr__tab is-active" data-tab="bonuses" role="tab" aria-selected="true">Bonuses <span data-count="bonuses"></span></button>
    <button type="button" class="fec-sr__tab" data-tab="casinos" role="tab" aria-selected="false">Casino Reviews <span data-count="casinos"></span></button>
  </div>

  <div class="fec-sr__list" data-list></div>

  <div class="fec-sr__footer">
    <button type="button" class="fec-sr__more" data-load-more>Load more</button>
  </div>
</div>

<script src="<?php echo JURI::root(true); ?>/media/com_fecsearch/js/results.js"></script>
