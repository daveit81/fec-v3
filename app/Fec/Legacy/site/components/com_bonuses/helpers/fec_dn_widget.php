<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * FEC Interactive Decision Notes widget (details-page band)
 *
 * Root div + data-attributes only: the question flow, the deterministic
 * verdict engine (rule table v1.0, thresholds calibrated on the live
 * catalog) and the persistent profile all live in js/fec_dn.js. The band
 * self-hides when the record lacks the core fields (wr, wr_meth, bonus)
 * or when the bonus type is timed free play.
 *
 * Standalone helper: fec_common_lib.php is NOT touched and NOT required.
 * PHP 5.6 compatible.
 */

if (!defined('FEC_DN_VERSION')) {
  define('FEC_DN_VERSION', '1.0.3');
}

if (!function_exists('fec_dn_add_assets')) {
  function fec_dn_add_assets()
  {
    static $done = false;
    if ($done) return;
    $done = true;
    $doc  = JFactory::getDocument();
    $base = JUri::root(true) . '/components/com_bonuses/';
    $doc->addStyleSheet($base . 'css/fec_dn.css?v=' . FEC_DN_VERSION);
    $doc->addScript($base . 'js/fec_dn.js?v=' . FEC_DN_VERSION);
  }
}

if (!function_exists('fec_dn_render_widget')) {
  /**
   * Echo the widget root. $attrs keys use data-attribute spelling.
   * max-cashout must only be passed with a classified max-cashout-kind.
   */
  function fec_dn_render_widget($attrs = array())
  {
    $defaults = array(
      'type' => 'match', 'bonus' => '', 'max-bonus' => '', 'min-dep' => '',
      'wr' => '', 'wr-meth' => 'b', 'cashable' => 'yes',
      'max-cashout' => '', 'max-cashout-kind' => '',
      'max-bet' => '', 'op-status' => 'ok', 'bid' => ''
    );
    $a = array_merge($defaults, is_array($attrs) ? $attrs : array());
    $parts = array('class="fec-dn"', 'id="fecDn"');
    foreach (array_keys($defaults) as $k) {
      $parts[] = 'data-' . $k . '="' . htmlspecialchars((string)$a[$k], ENT_QUOTES, 'UTF-8') . '"';
    }
    echo '<div ' . implode(' ', $parts) . '></div>' . "\n";
  }
}