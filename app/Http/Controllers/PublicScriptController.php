<?php

namespace App\Http\Controllers;

use App\Fec\Legacy\ComponentRunner;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The live site's public standalone endpoints (fec.legacy.public_scripts), at their
 * own paths: /r.php, /subscribe.php, /components/com_bonuses/ajax/bonus_feedback.php,
 * /components/com_bonuses/ajax_review_submit.php. Stateless group: no Laravel session
 * or CSRF cookie; the scripts keep their own defences (HMAC signatures, honeypots,
 * Turnstile, rate limits) and their own cookie (fec_voter).
 */
class PublicScriptController extends Controller
{
    public function __invoke(Request $request, ComponentRunner $runner): Response
    {
        return $runner->runPublicScript($request, $request->getPathInfo());
    }
}
