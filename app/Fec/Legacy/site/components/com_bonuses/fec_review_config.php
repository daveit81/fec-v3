<?php
/**
 * PORT: on the live site this file holds the review form's settings and the
 * Turnstile keys (it is excluded from the snapshot for that reason) and the
 * lib loads it at include time. Here the live file itself is read first, from
 * the real docroot (fec.legacy.docroot), so the submission endpoint
 * (ajax_review_submit.php, phase 5) verifies Turnstile with the same secret
 * and notifies the same moderation address as Joomla: one file to rotate.
 * It only defines constants. Whatever it does not define (or, on a machine
 * without the live docroot, all of it) comes from the environment
 * (config fec.legacy.review): the public site key the form prints and the
 * limits the form announces.
 */
defined('_JEXEC') or die('Restricted access');

if (!defined('FEC_TURNSTILE_SITE_KEY') && !defined('FEC_TURNSTILE_SECRET_KEY')) {
	$fecLiveReviewConfig = rtrim((string) config('fec.legacy.docroot', ''), '/') . '/components/com_bonuses/fec_review_config.php';
	if (is_file($fecLiveReviewConfig) && is_readable($fecLiveReviewConfig)) {
		require_once $fecLiveReviewConfig;
	}
	unset($fecLiveReviewConfig);
}

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
