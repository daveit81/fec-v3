<?php
defined('_JEXEC') or die;
class FecApiAuthHelper {
  public static function checkBearerToken() {
    $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (!preg_match('/Bearer\s+(.+)/i', $auth, $m)) return false;
    $token = trim($m[1]);
    $expected = '<REDACTED:fecapi-token>';
    return function_exists('hash_equals') ? hash_equals($expected, $token) : ($expected === $token);
  }
}
