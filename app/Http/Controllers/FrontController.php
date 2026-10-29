<?php

namespace App\Http\Controllers;

use App\Fec\Url\PageHandler;
use App\Fec\Url\Redirects;
use App\Fec\Url\Router;
use App\Fec\Url\WwwRedirect;
use App\Models\Fec\Article;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fallback route: every GET that no explicit route claimed. Asks the Router
 * what the path is and either redirects, hands the request to the handler of
 * a ported view, or answers 404.
 *
 * On the development hostname (anything but fec.site_url, i.e. v3. behind
 * Cloudflare Access) a 404 carries the routing decision, so a path can be
 * checked in the browser before its section is ported. On the live hostname
 * the 404 is bare: the legacy .htaccess routes only switched paths here.
 */
class FrontController extends Controller
{
    public function __invoke(Request $request, Router $router): Response
    {
        $response = $this->respond($request, $router);
        // Joomla's anonymous pages: "Cache-Control: no-cache" and "Pragma: no-cache", no public caching.
        if (! $response->headers->has('Pragma') && ! $response->isRedirection()) {
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Cache-Control', 'no-cache');
        }

        return $response;
    }

    private function respond(Request $request, Router $router): Response
    {
        // getPathInfo() keeps the trailing slash (path() trims it): the registry stores
        // "bonuses/" and "bonuses" apart, and sh404SEF redirected between the two.
        // The raw query string, as the web server's RewriteCond and sh404SEF saw it
        // (getQueryString() normalises the order and the encoding).
        $rawQuery = (string) $request->server->get('QUERY_STRING', '');
        $decision = $router->route($request->getPathInfo(), $rawQuery !== '' ? $rawQuery : null, (string) $request->userAgent());

        // sh404SEF's start-up: the bare domain goes to www (after the web server's own rules,
        // before any alias or URL lookup), path and query string as sent.
        if ($decision->url->source !== 'htaccess') {
            $www = WwwRedirect::target(
                (string) config('fec.site_url'),
                $request->getHost(),
                $request->getMethod(),
                (string) $request->server->get('REQUEST_URI', '/'),
                (string) $request->headers->get('X-Requested-With', ''),
            );
            if ($www !== null) {
                return response('', 301, ['Location' => $www]);
            }
        }

        // What the web server itself answered for these paths (LiteSpeed's own error pages).
        if (in_array($decision->action, ['gone', 'forbidden'], true)) {
            $text = $decision->status === 410 ? 'Gone' : 'Forbidden';

            return response('<!DOCTYPE html><html><head><title>'.$decision->status.' '.$text.'</title></head><body><h1>'.$decision->status.'</h1><h2>'.$text.'</h2></body></html>', $decision->status, ['Content-Type' => 'text/html']);
        }

        if ($decision->isRedirect()) {
            $to = (string) $decision->location;
            // An sh404SEF alias: the Location exactly as the live site sends it (relative
            // when the alias says so, the query string already dealt with).
            if (! empty($decision->url->meta['raw_location'])) {
                return response('', $decision->status, ['Location' => $to]);
            }
            // Keep the visitor's query string on same-site redirects (sh404SEF does). The
            // web server's own redirects (mod_alias, RewriteRule [R]) append it to any target,
            // absolute or not, unless the target carries a query of its own.
            $qs = $rawQuery !== '' ? $rawQuery : null;
            if ($qs !== null && $decision->url->source === 'htaccess') {
                if (! str_contains($to, '?')) {
                    $to .= '?'.$qs;
                }
            } elseif ($qs !== null && ! preg_match('#^https?://#i', $to)) {
                $to .= (str_contains($to, '?') ? '&' : '?').$qs;
            }

            return Redirects::to($request, $to, $decision->status);
        }

        if ($decision->action === 'render' && $decision->handler !== null) {
            /** @var PageHandler $handler */
            $handler = app($decision->handler);

            return $handler->handle($request, $decision->url);
        }

        if ($this->isDevHost($request)) {
            return response()->view('router.unported', ['decision' => $decision, 'path' => '/'.$request->path()], 404);
        }

        return $this->notFound($request);
    }

    /** The 404 the live site serves for a path the registry does not know: sh404SEF's "404" article in the shell. */
    private function notFound(Request $request): Response
    {
        $article = null;
        $id = (int) config('fec.legacy.not_found_article_id', 0);
        if ($id > 0) {
            try {
                $article = Article::query()->find($id);
            } catch (\Throwable $e) {
                $article = null;
            }
        }

        return response()->view('legacy.not-found', [
            'article' => $article,
            'canonical' => rtrim((string) config('fec.site_url'), '/').'/'.ltrim($request->path(), '/'),
        ], 404);
    }

    private function isDevHost(Request $request): bool
    {
        $live = strtolower((string) parse_url((string) config('fec.site_url'), PHP_URL_HOST));

        return $live === '' || strtolower($request->getHost()) !== $live;
    }
}
