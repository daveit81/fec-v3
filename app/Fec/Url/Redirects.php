<?php

namespace App\Fec\Url;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Redirect responses that keep the target exactly as given. Laravel's
 * redirect()->to() trims the trailing slash, and sh404SEF's URLs end with
 * one (/bonuses/, /bonuses/<casino>/): a redirect to "/bonuses/" must say so.
 */
final class Redirects
{
    public static function to(Request $request, string $to, int $status = 302): RedirectResponse
    {
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $to)) {
            $to = $request->getSchemeAndHttpHost().'/'.ltrim($to, '/');
        }

        return redirect()->away($to, $status);
    }
}
