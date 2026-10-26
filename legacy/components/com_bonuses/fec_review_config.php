<?php
defined('_JEXEC') or die('Restricted access');

/* ============================================================
 * FEC REVIEW SUBSYSTEM — config constants
 * Loaded by fec_common_lib.php via require_once.
 * ============================================================ */

// Cloudflare Turnstile (https://dash.cloudflare.com/?to=/:account/turnstile)
// SITE_KEY = pubblica, embedded nel HTML del form
// SECRET_KEY = privata, server-side only, MAI in HTML
define('FEC_TURNSTILE_SITE_KEY',   '0x4AAAAAADVaSLRpYSVp_lOY');
define('FEC_TURNSTILE_SECRET_KEY', '0x4AAAAAADVaSEJDdVzoNHVEPOZdqG6_xwI');

// Email a cui mandare notifiche di nuove review in queue
define('FEC_REVIEW_MOD_EMAIL', 'moderation@freeextrachips.com');

// Rate limiting
define('FEC_REVIEW_MAX_PER_IP_24H',        3); // max review da uno stesso IP in 24h
define('FEC_REVIEW_MAX_PER_IP_CASINO_24H', 1); // max review da uno stesso IP per casino in 24h
define('FEC_REVIEW_MIN_SECONDS_ON_FORM',   5); // soglia minima tra page load e submit

// Body length validation
define('FEC_REVIEW_MIN_LENGTH',   50);
define('FEC_REVIEW_MAX_LENGTH', 3000);