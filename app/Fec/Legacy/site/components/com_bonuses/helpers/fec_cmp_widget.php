<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * FEC Casino Comparator widget (FEC Tools No. 02)
 *
 * Static shell only: the table is rendered client side by fec_cmp.js from
 * the dataset at components/com_bonuses/data/cmp_casinos.json (built by
 * fec_cmp_build.php). Country detection happens client side via the
 * Cloudflare /cdn-cgi/trace endpoint, so the page HTML never varies per
 * visitor - no cache fragmentation.
 *
 * Standalone helper: fec_common_lib.php is NOT touched and NOT required.
 * PHP 5.6 compatible.
 */

if (!defined('FEC_CMP_VERSION')) {
  define('FEC_CMP_VERSION', '1.1.0');
}

if (!function_exists('fec_cmp_add_assets')) {
  function fec_cmp_add_assets()
  {
    static $done = false;
    if ($done) return;
    $done = true;
    $doc  = JFactory::getDocument();
    $base = JUri::root(true) . '/components/com_bonuses/';
    $doc->addStyleSheet($base . 'css/fec_cmp.css?v=' . FEC_CMP_VERSION);
    $doc->addScript($base . 'js/fec_cmp.js?v=' . FEC_CMP_VERSION);
  }
}

if (!function_exists('fec_cmp_render_widget')) {
  /**
   * Echo the widget shell. $dataUrl is the dataset URL including any
   * cache-busting query built by the caller from filemtime().
   */
  function fec_cmp_render_widget($dataUrl)
  {
    $u = htmlspecialchars($dataUrl, ENT_QUOTES, 'UTF-8');
    echo '<div class="fec-cmp" id="fecCmp" data-src="' . $u . '">' . "\n";
    echo <<<'FECCMP'
      <div class="cmp-pickers">
        <div class="cmp-pick">
          <label for="cmpS1">Casino 1</label>
          <input type="text" id="cmpS1" list="cmpList" placeholder="Type a casino name" autocomplete="off">
        </div>
        <div class="cmp-pick">
          <label for="cmpS2">Casino 2</label>
          <input type="text" id="cmpS2" list="cmpList" placeholder="Type a casino name" autocomplete="off">
        </div>
        <div class="cmp-pick">
          <label for="cmpS3">Casino 3 <small>(optional)</small></label>
          <input type="text" id="cmpS3" list="cmpList" placeholder="Type a casino name" autocomplete="off">
        </div>
        <div class="cmp-pick">
          <label for="cmpCc">Your country</label>
          <select id="cmpCc"><option value="">Not set</option></select>
        </div>
      </div>
      <datalist id="cmpList"></datalist>

      <div class="cmp-samop" id="cmpSamop" role="note"></div>
      <div class="cmp-status" id="cmpStatus">Loading casino data&hellip;</div>

      <div class="cmp-scroll">
        <table class="cmp-table" aria-label="Casino comparison" hidden>
          <thead><tr id="cmpHead"><th class="cmp-rowlabel"></th></tr></thead>
          <tbody id="cmpBody"></tbody>
        </table>
      </div>

      <p class="cmp-note">Facts reflect the FEC dataset at time of research; the casino&rsquo;s
      current terms always prevail. Restricted-country data is verified against first-party
      T&amp;C where available. 18+ &middot; Play responsibly.</p>
FECCMP;
    echo "\n</div>\n";
  }
}