<?php
/**
 * PORT: on the live site this file holds the review form's settings and the
 * Turnstile keys (it is excluded from the snapshot for that reason) and the
 * lib loads it at include time. Here the values come from the environment
 * (config fec.legacy.review): the public site key the form prints and the
 * limits the form announces. The secret key is not needed by any ported
 * page: the submission endpoint (ajax_review_submit.php) stays on Joomla
 * until phase 5, and with it the moderation settings.
 */
defined('_JEXEC') or die('Restricted access');

if (!defined('FEC_TURNSTILE_SITE_KEY')) {
	define('FEC_TURNSTILE_SITE_KEY', (string) config('fec.legacy.review.turnstile_site_key', ''));
}
if (!defined('FEC_REVIEW_MIN_LENGTH')) {
	define('FEC_REVIEW_MIN_LENGTH', (int) config('fec.legacy.review.min_length', 50));
}
if (!defined('FEC_REVIEW_MAX_LENGTH')) {
	define('FEC_REVIEW_MAX_LENGTH', (int) config('fec.legacy.review.max_length', 3000));
}
if (!defined('FEC_REVIEW_MIN_SECONDS_ON_FORM')) {
	define('FEC_REVIEW_MIN_SECONDS_ON_FORM', (int) config('fec.legacy.review.min_seconds_on_form', 5));
}
