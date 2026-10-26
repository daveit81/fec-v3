<?php
defined('_JEXEC') or die;

/**
 * Methodology default layout — fallback.
 *
 * Normally never reached: the view sets a topic-specific layout suffix
 * (e.g. 'fxcheck' → default_fxcheck.php). This file exists only so a
 * misconfigured topic (missing 'tmpl' key in the topic registry) doesn't
 * throw "Layout default not found" but renders a clear 404 instead.
 */

$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$topic = isset($this->topic) ? (string)$this->topic : '';
?>
<div class="fecx me-page">
  <section class="au-hero">
    <div class="wrap">
      <div class="me-hero-card">
        <div class="me-hero-body">
          <div class="eyebrow">Methodology</div>
          <h1 class="me-title">Topic not available</h1>
          <p class="me-tagline">The methodology topic <code><?php echo $h($topic); ?></code> doesn&rsquo;t have a published page yet.</p>
          <p><a href="<?php echo JRoute::_('index.php?option=com_authors&view=methodology&topic=fxcheck'); ?>">View the FXCheck&trade; methodology</a></p>
        </div>
      </div>
    </div>
  </section>
</div>
