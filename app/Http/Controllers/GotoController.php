<?php

namespace App\Http\Controllers;

use App\Fec\Clicks\ClickLogger;
use App\Fec\Clicks\Subid;
use App\Fec\Shortlink\Shortlinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /goto.php?link={phrase}: the tracked affiliate redirect, first v3 route
 * (phase 1). Same observable behaviour as the legacy goto.php +
 * com_shortlink chain, in one hop:
 *
 *   1. log the click in jos_bonus_clicks (slug path, landing-page subid)
 *   2. resolve the phrase in jos_shortlink, bump its counter
 *   3. 302 to the affiliate URL, with the subid appended only for programs
 *      enabled in Subid::programMap() (empty => URL byte-identical)
 *
 * Unknown or missing phrase: 302 to config fec.goto.fallback, click still
 * logged with the slug (as the legacy did). Never cached.
 */
class GotoController extends Controller
{
    public function __invoke(Request $request, ClickLogger $clicks, Shortlinks $shortlinks): RedirectResponse
    {
        $phrase = '';
        foreach (config('fec.goto.params', ['link']) as $param) {
            $value = trim((string) $request->query($param, ''));
            if ($value !== '') {
                $phrase = $value;
                break;
            }
        }

        $referer = (string) $request->header('Referer', '');
        $subid = Subid::fromSrc($referer);

        if ($phrase !== '') {
            $clicks->log($request, 0, 0, ['slug' => $phrase, 'src_page' => $referer, 'subid' => $subid]);
        }

        $target = (string) config('fec.goto.fallback', '/');
        $row = $phrase !== '' ? $shortlinks->find($phrase) : null;
        if ($row && preg_match('#^https?://#i', trim((string) $row->link))) {
            $target = Subid::append(trim((string) $row->link), $subid);
            $shortlinks->hit($phrase);
        }

        return redirect()->away($target, 302)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
