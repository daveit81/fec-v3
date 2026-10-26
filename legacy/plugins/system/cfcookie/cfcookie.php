<?php
defined('_JEXEC') or die;
jimport('joomla.plugin.plugin');

class plgSystemCfcookie extends JPlugin
{
    /**
     * Helper: scrive su log file
     */
    private function log($msg)
    {
        $logFile = JPATH_SITE . '/tmp/cfcookie_debug.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
        @file_put_contents($logFile, $line, FILE_APPEND);
    }

    /**
     * Login: imposta cookie cf_loggedin=yes
     */
    public function onUserLogin($user, $options = array())
    {
        $this->log('onUserLogin fired');
        $this->setCookie('yes', time() + 86400 * 7); // 7 giorni
        return true;
    }

    /**
     * Logout: rimuovi cookie
     */
    public function onUserLogout($user, $options = array())
    {
        $this->log('onUserLogout fired');
        $this->setCookie('', time() - 3600);
        return true;
    }

    /**
     * Su ogni request frontend: sincronizza cookie con stato reale
     */
    public function onAfterInitialise()
    {
        $app = JFactory::getApplication();
        if ($app->isAdmin()) {
            return;
        }

        $user      = JFactory::getUser();
        $hasCookie = isset($_COOKIE['cf_loggedin']) && $_COOKIE['cf_loggedin'] === 'yes';

        $this->log('onAfterInitialise - guest: ' . ($user->guest ? 'yes' : 'no') . ' - hasCookie: ' . ($hasCookie ? 'yes' : 'no') . ' - URI: ' . $_SERVER['REQUEST_URI']);

        if ($user->guest && $hasCookie) {
            $this->setCookie('', time() - 3600);
            $this->log('-> cleared cookie (guest with cookie)');
        } elseif (!$user->guest && !$hasCookie) {
            $this->setCookie('yes', time() + 86400 * 7);
            $this->log('-> set cookie (logged in without cookie)');
        }
    }

    private function setCookie($value, $expire)
    {
        $headersAlreadySent = headers_sent($file, $line);
        if ($headersAlreadySent) {
            $this->log('!!! headers already sent at ' . $file . ':' . $line);
        }

        $result = setcookie(
            'cf_loggedin',
            $value,
            $expire,
            '/',
            '',
            true,   // secure: solo HTTPS
            true    // httpOnly: non leggibile da JS
        );

        $this->log('setcookie("cf_loggedin", "' . $value . '", expire=' . $expire . ') returned: ' . ($result ? 'true' : 'false'));
    }
}