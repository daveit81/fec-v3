<?php

namespace App\Http\Controllers;

use App\Fec\Legacy\ComponentRunner;
use App\Fec\Url\ResolvedUrl;
use App\Fec\Url\Router;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The redazione's entry points to the ported desks, in the web group (session,
 * login). Two shapes, as on the live site:
 *   - SEF desk paths (/methodology-admin, /fxshield-admin, /fxshield-verify),
 *     resolved through the registry like any page;
 *   - Joomla's non-SEF entry, /index.php?option=com_x&view=...&task=..., which
 *     the desks' forms and JavaScript call (JRoute builds it when the registry
 *     has no SEF spelling), GET and POST.
 * A desk view or a task needs a logged-in user; the rest is served as the
 * public fallback would (fxreport and other non-SEF public views).
 */
class LegacyDeskController extends Controller
{
    public function sef(Request $request, Router $router, ComponentRunner $runner): Response
    {
        $decision = $router->route($request->path());
        if (! $decision->url->isPage() || $decision->url->option() === null) {
            abort(404);
        }
        if ($this->isDesk($decision->url) && ! auth()->check()) {
            return redirect()->guest(route('login'));
        }

        return $runner->handle($request, $decision->url);
    }

    public function index(Request $request, ComponentRunner $runner): Response
    {
        $query = [];
        foreach ($request->query() as $k => $v) {
            $query[(string) $k] = is_array($v) ? (string) json_encode($v) : (string) $v;
        }
        $option = $query['option'] ?? '';
        if (! preg_match('/^com_[a-z0-9_]+$/', $option)) {
            abort(404);
        }
        // Joomla's task on a POST comes in the body ("task" input) as often as in the query.
        if (! isset($query['task']) && $request->isMethod('POST') && is_string($request->input('task'))) {
            $query['task'] = (string) $request->input('task');
        }
        $url = new ResolvedUrl('page', 'index.php', $query, null, null, null, null, 'index.php?'.http_build_query($query), 'index.php');

        if (($this->isDesk($url) || ($query['task'] ?? '') !== '') && ! auth()->check()) {
            if ($request->expectsJson() || ($query['format'] ?? '') === 'json') {
                return response()->json(['ok' => false, 'error' => 'login required'], 401);
            }

            return redirect()->guest(route('login'));
        }

        return $runner->handle($request, $url);
    }

    private function isDesk(ResolvedUrl $url): bool
    {
        return in_array($url->routeKey(), (array) config('fec.legacy.desk_views', []), true);
    }
}
