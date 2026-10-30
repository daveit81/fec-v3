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
 * public fallback would (fxreport and other non-SEF public views). The
 * non-SEF entry is reached through the root route (routes/web.php): Laravel
 * sees /index.php?option=... as the path "/".
 */
class LegacyDeskController extends Controller
{
    public function sef(Request $request, Router $router, ComponentRunner $runner): Response
    {
        $decision = $router->route($request->getPathInfo());
        if (! $decision->url->isPage() || $decision->url->option() === null) {
            abort(404);
        }
        if (ComponentRunner::isDeskUrl($decision->url) && ! auth()->check()) {
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
        // Joomla read option, view and task from the merged request: the desks'
        // JavaScript posts them in the body as often as in the query (the bonus
        // desk posts {option, task, ...} to a bare "index.php").
        if ($request->isMethod('POST')) {
            foreach (['option', 'view', 'task'] as $k) {
                if (! isset($query[$k]) && is_string($request->input($k))) {
                    $query[$k] = (string) $request->input($k);
                }
            }
        }
        $option = $query['option'] ?? '';
        if (! preg_match('/^com_[a-z0-9_]+$/', $option)) {
            abort(404);
        }
        $url = new ResolvedUrl('page', 'index.php', $query, null, null, null, null, 'index.php?'.http_build_query($query), 'index.php');

        $task = (string) ($query['task'] ?? '');
        $publicTask = ComponentRunner::isPublicTask($option, $task) && ! ComponentRunner::isDeskUrl($url);
        if ((ComponentRunner::isDeskUrl($url) || ($task !== '' && ! $publicTask)) && ! auth()->check()) {
            if ($request->expectsJson() || ($query['format'] ?? '') === 'json') {
                return response()->json(['ok' => false, 'error' => 'login required'], 401);
            }

            return redirect()->guest(route('login'));
        }

        return $runner->handle($request, $url);
    }

    /**
     * A desk's standalone script, at the path the desk pages call it by on the
     * live site: /components/<option>/ajax/<name>.php (fec.legacy.desk_scripts).
     * A guest gets the login page, or the scripts' own JSON refusal when the
     * call is the desk's JavaScript.
     */
    public function script(Request $request, ComponentRunner $runner, string $option, string $script): Response
    {
        $allowed = (array) (config('fec.legacy.desk_scripts', [])[$option] ?? []);
        if (! in_array($script, $allowed, true)) {
            abort(404);
        }
        if (! auth()->check()) {
            if ($request->expectsJson() || $request->ajax() || $request->isMethod('POST')) {
                return response()->json(['ok' => false, 'error' => 'unauthorized'], 403);
            }

            return redirect()->guest(route('login'));
        }

        return $runner->runScript($request, $option, $script);
    }

    /**
     * A desk file outside the components, at its live path (fec.legacy.desk_files:
     * /fec-admin/fec_dashboard.php, its API, the clusters page). A guest gets the login
     * page, or a JSON refusal when the call is the dashboard's JavaScript.
     */
    public function file(Request $request, ComponentRunner $runner): Response
    {
        if (! auth()->check()) {
            if ($request->expectsJson() || $request->ajax() || $request->isMethod('POST') || str_contains($request->getPathInfo(), '_api')) {
                return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
            }

            return redirect()->guest(route('login'));
        }

        return $runner->runDeskFile($request, $request->getPathInfo());
    }
}
