<?php

namespace App\Http\Controllers;

use App\Fec\Url\PageHandler;
use App\Models\Fec\Article;
use App\Fec\Url\Router;
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
        $decision = $router->route($request->path());

        if ($decision->isRedirect()) {
            $to = (string) $decision->location;
            // Keep the visitor's query string on same-site redirects (sh404SEF does).
            if (! preg_match('#^https?://#i', $to) && ($qs = $request->getQueryString()) !== null && $qs !== '') {
                $to .= (str_contains($to, '?') ? '&' : '?').$qs;
            }

            return redirect()->to($to, $decision->status);
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
