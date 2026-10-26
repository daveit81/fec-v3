<?php
defined('_JEXEC') or die;
class FecApiAuthHelper {
  public static function checkBearerToken() {
    $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (!preg_match('/Bearer\s+(.+)/i', $auth, $m)) return false;
    $token = trim($m[1]);
    $expected = '1f542c7889808aeacbf16d35f76c3d9cefb311df4e8206a79a1ffadac3cbb3fe';
    return function_exists('hash_equals') ? hash_equals($expected, $token) : ($expected === $token);
  }
}
