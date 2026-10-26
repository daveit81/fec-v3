<?php

namespace App\Fec\Legacy;

use App\Fec\Legacy\Joomla\Runtime;

/**
 * The template-side pieces of the legacy lib that every page of the shell
 * renders (newsletter block in the footer, newsletter popup, channel
 * links): the "template" slice of fec_common_lib.php, loaded on demand by
 * the Blade partials. Same functions, same output as templates/fecv2/
 * index.php, signatures included (JFactory::getConfig()->get('secret') is
 * fec.legacy.secret, the Joomla secret, so /subscribe.php accepts them).
 */
final class TemplateLib
{
    private static bool $loaded = false;

    public static function load(): bool
    {
        if (! self::$loaded) {
            Runtime::aliases();
            $file = Runtime::sitePath().'/components/com_bonuses/helpers/fec_common_lib.php';
            if (is_file($file)) {
                require_once $file;
            }
            self::$loaded = true;
        }

        return function_exists('fec_render_email_capture_block');
    }

    /** @param  array<string, mixed>  $opts */
    public static function newsletterBlock(string $source, array $opts = []): string
    {
        return self::load() ? (string) fec_render_email_capture_block($source, $opts) : '';
    }

    /** @param  array<string, mixed>  $opts */
    public static function newsletterPopup(array $opts = []): string
    {
        return self::load() && function_exists('fec_render_email_capture_popup') ? (string) fec_render_email_capture_popup($opts) : '';
    }

    public static function telegramUrl(string $source = ''): string
    {
        return self::load() && function_exists('fec_telegram_url') ? (string) fec_telegram_url($source) : (string) config('fec.social.telegram');
    }

    public static function discordUrl(string $source = ''): string
    {
        return self::load() && function_exists('fec_discord_url') ? (string) fec_discord_url($source) : (string) config('fec.social.discord');
    }
}
