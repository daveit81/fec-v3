<?php

namespace App\Fec\Legacy;

use App\Fec\Legacy\Joomla\ExitException;
use App\Fec\Legacy\Joomla\Factory;
use App\Fec\Legacy\Joomla\RedirectException;
use App\Fec\Legacy\Joomla\Request as JRequest;
use App\Fec\Legacy\Joomla\Runtime;
use App\Fec\Legacy\Joomla\Uri;
use App\Fec\Url\PageHandler;
use App\Fec\Url\Redirects;
use App\Fec\Url\ResolvedUrl;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Runs a ported Joomla component for one request, the way Joomla's
 * index.php did: seed the request variables the SEF layer produced, pick
 * the active menu item, include the component entry file (which
 * dispatches its controller and echoes the view), then hand the output
 * and the document head to the .fecx layout. Redirects and jexit() come
 * back as exceptions and become responses here.
 *
 * Registered in Dispatcher::HANDLERS per "option.view"; one instance
 * serves every view of a component.
 */
final class ComponentRunner implements PageHandler
{
    public function handle(Request $request, ResolvedUrl $url): Response
    {
        $option = (string) $url->option();
        if (! preg_match('/^com_[a-z0-9_]+$/', $option)) {
            abort(404);
        }
        $entry = Runtime::sitePath().'/components/'.$option.'/'.substr($option, 4).'.php';
        if (! is_file($entry)) {
            abort(404);
        }

        // Desk views and their tasks exist only for a logged-in member of the
        // redazione (the explicit desk routes carry the session; the public
        // fallback never does, so a desk reached that way is refused). A task on
        // a public view is not ours: the legacy .htaccess keeps task= requests on
        // Joomla, this is the second lock.
        $desk = self::isDeskUrl($url);
        $task = trim((string) $request->input('task', ''));
        if ($desk && ! auth()->check()) {
            abort(404);
        }
        if ($task !== '' && ! $desk) {
            abort(404, 'Task not available on this view');
        }

        return $this->run($request, $url, $entry, $desk || $this->needsWrites($url));
    }

    /**
     * A component task run from the console (the snapshot crons): the request
     * is the one the cron's curl made, built by the command; no session, no
     * desk lock (the task checks its own key), writing connection.
     */
    public function runTask(Request $request, ResolvedUrl $url): Response
    {
        $option = (string) $url->option();
        $entry = Runtime::sitePath().'/components/'.$option.'/'.substr($option, 4).'.php';
        if (! preg_match('/^com_[a-z0-9_]+$/', $option) || ! is_file($entry)) {
            abort(404);
        }

        return $this->run($request, $url, $entry, true);
    }

    private function run(Request $request, ResolvedUrl $url, string $entry, bool $rw): Response
    {
        $option = (string) $url->option();
        Runtime::boot($option);
        Factory::useRwConnection($rw);
        if (! defined('FEC_OB_BASE')) {
            define('FEC_OB_BASE', ob_get_level() + 1);
        }
        Uri::reset();
        Joomla\Route::reset();

        // What Joomla's router + sh404SEF had put in the request: the Joomla
        // query of the resolved URL (option, view, slug, Itemid, ...) on top of
        // the real query string. Query string wins for limitstart/tab/etc.,
        // the registry wins for the routing variables.
        $seed = $url->query;
        foreach ($request->query() as $k => $v) {
            if (! isset($seed[$k]) || in_array($k, ['limitstart', 'limit', 'tab', 'active', 'edition', 'preview', 'tmpl', 'format', 'layout', 'task'], true)) {
                $seed[$k] = is_array($v) ? json_encode($v) : (string) $v;
            }
        }
        // sh404SEF renamed limitstart to "start" in every paginated URL: a visitor arrives with start=N.
        if (isset($seed['start']) && ! isset($seed['limitstart']) && is_numeric($seed['start'])) {
            $seed['limitstart'] = (string) (int) $seed['start'];
        }
        JRequest::seed($seed);
        Factory::getApplication()->getMenu()->activateFor($url->query);
        $this->syncServerGlobals($request);

        $body = '';
        $level = ob_get_level();
        ob_start();
        LegacyErrors::begin($url->routeKey() ?? $option);
        try {
            require $entry;
            $body = (string) ob_get_clean();
        } catch (RedirectException $e) {
            $this->discard($level);
            // Joomla kept the enqueued messages ("Topic saved.") in the session
            // and showed them on the page after the redirect: same here, when
            // the request has a session (desks); public pages never enqueue.
            $queue = Factory::getApplication()->getMessageQueue();
            if ($queue !== [] && $request->hasSession()) {
                $request->session()->flash('legacy.messages', $queue);
            }

            return Redirects::to($request, $e->url, $e->status);
        } catch (ExitException $e) {
            // jexit(): what was echoed so far is the whole response (JSON tasks, raw output).
            $body = (string) ob_get_clean();

            return $this->raw($body, $request);
        } catch (Throwable $e) {
            $this->discard($level);
            // A component exception carrying an HTTP code (new Exception('Author not
            // found', 404)) became Joomla's system error page on the live site
            // (templates/system/error.php); the sh404SEF 404 page is for paths the
            // registry does not know, which never reach a component.
            if ($e->getCode() >= 400 && $e->getCode() < 600) {
                return response()->view('legacy.system-error', ['code' => (int) $e->getCode(), 'message' => $e->getMessage()], (int) $e->getCode());
            }
            throw $e;
        } finally {
            LegacyErrors::end();
        }

        $doc = Factory::getDocument();
        $format = strtolower((string) JRequest::getCmd('format', 'html'));
        $tmpl = strtolower((string) JRequest::getCmd('tmpl', ''));

        // A view that redirected with a bare header("Location: ...") (the tc and ads
        // views of com_bonuses) and went on rendering: PHP answered 302 with that
        // header and the body behind it. Same here, the header as set (an empty
        // tc link gave an empty Location on the live site too).
        $location = $this->locationHeader();
        if ($location !== null) {
            $status = http_response_code();
            $status = is_int($status) && $status >= 300 && $status < 400 ? $status : 302;
            if (http_response_code() !== 200 && http_response_code() !== false) {
                http_response_code(200);
            }

            return response($body, $status, ['Location' => $location] + Factory::getApplication()->getHeaders());
        }

        if ($format !== 'html' && $format !== '') {
            return $this->raw($body, $request);
        }
        // sh404SEF's per-page title and metas, applied last on the live site (after the
        // component and the template), keyed on the page's own non-SEF URL.
        Sh404sefMetas::apply($doc, $url->newurl);

        if ($tmpl === 'component') {
            return response()->view('legacy.bare', ['doc' => $doc, 'body' => $body]);
        }

        $canonical = $doc->canonical();
        if ($canonical === null && $url->canonical !== null) {
            $canonical = Uri::site().$url->canonical;
        }

        $messages = Factory::getApplication()->getMessageQueue();
        if ($request->hasSession()) {
            $messages = array_merge((array) $request->session()->get('legacy.messages', []), $messages);
        }

        $response = response()->view('legacy.component', [
            'doc' => $doc,
            'body' => $body,
            'canonical' => $canonical,
            'bodyClass' => '',
            'messages' => $messages,
        ]);
        foreach (Factory::getApplication()->getHeaders() as $name => $value) {
            $response->headers->set($name, $value);
        }

        // Development hostname: make the legacy asset paths point at the live docroot.
        $live = strtolower((string) parse_url((string) config('fec.site_url'), PHP_URL_HOST));
        if ($live !== '' && strtolower($request->getHost()) !== $live) {
            $response->setContent(DevAssets::absolutize((string) $response->getContent(), (string) config('fec.asset_base')));
        }

        return $response;
    }

    private function raw(string $body, Request $request): Response
    {
        $headers = Factory::getApplication()->getHeaders() + $this->phpHeaders();
        if (! isset($headers['Content-Type']) && json_decode($body) !== null) {
            $headers['Content-Type'] = 'application/json; charset=utf-8';
        }
        // The status the legacy code set with http_response_code() (403 bad key, 500 failure).
        $status = http_response_code();
        $status = is_int($status) && $status >= 100 && $status < 600 ? $status : 200;
        if ($status !== 200) {
            http_response_code(200);
        }
        // header("Location: ...") then close() (the ads view): PHP made that a 302.
        $location = $this->locationHeader();
        if ($location !== null) {
            $headers['Location'] = $location;
            if ($status < 300 || $status >= 400) {
                $status = 302;
            }
        }

        return response($body, $status, $headers);
    }

    /**
     * The Location header the component set, with JResponse::setHeader() or a
     * bare header(): removed from PHP's own list (it must not be sent twice) and
     * returned as set, '' included; null when there is none.
     */
    private function locationHeader(): ?string
    {
        $location = null;
        foreach (Factory::getApplication()->getHeaders() as $name => $value) {
            if (strcasecmp($name, 'Location') === 0) {
                $location = (string) $value;
            }
        }
        if (function_exists('headers_list')) {
            foreach (headers_list() as $line) {
                if (stripos($line, 'Location:') === 0) {
                    $location = trim(substr($line, strlen('Location:')));
                    header_remove('Location');
                }
            }
        }

        return $location;
    }

    /**
     * Headers the legacy code set with header() (JSON tasks, markdown
     * downloads): copied into the response and removed from PHP's own list.
     *
     * @return array<string, string>
     */
    private function phpHeaders(): array
    {
        $out = [];
        if (! function_exists('headers_list')) {
            return $out;
        }
        foreach (headers_list() as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);
            if (in_array(strtolower($name), ['content-type', 'content-disposition', 'content-length', 'cache-control', 'pragma', 'x-robots-tag', 'expires'], true)) {
                $out[$name] = trim($value);
                header_remove($name);
            }
        }

        return $out;
    }

    /**
     * A desk (non-public) URL: its view is in fec.legacy.desk_views, or, when
     * the URL carries a task and no view (index.php?option=com_authors&task=
     * methodologyadmin.save, the desks' save and JSON calls), the controller
     * named by the task is a desk view.
     */
    public static function isDeskUrl(ResolvedUrl $url): bool
    {
        $option = (string) $url->option();
        $view = (string) ($url->view() ?? '');
        $task = (string) ($url->query['task'] ?? '');
        if ($view === '' && str_contains($task, '.')) {
            $view = strtolower((string) strstr($task, '.', true));
        }

        return $option !== '' && $view !== ''
            && in_array($option.'.'.$view, (array) config('fec.legacy.desk_views', []), true);
    }

    /**
     * The legacy code reads $_SERVER directly (REQUEST_URI for pagination links,
     * HTTP_REFERER, REMOTE_ADDR, HTTPS...). In a web request the superglobal
     * already is the request; in the console (fec:parity:render, the snapshot
     * commands) it is the shell's, so the request's values are copied in.
     */
    private function syncServerGlobals(Request $request): void
    {
        $always = ['REQUEST_URI', 'QUERY_STRING', 'REQUEST_METHOD', 'HTTP_HOST', 'SERVER_NAME', 'PATH_INFO', 'HTTPS', 'SERVER_PORT', 'HTTP_USER_AGENT', 'HTTP_REFERER', 'REMOTE_ADDR'];
        foreach ($request->server->all() as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }
            if (! array_key_exists($key, $_SERVER) || in_array($key, $always, true)) {
                $_SERVER[$key] = $value;
            }
        }
        if (! isset($_SERVER['HTTPS']) && $request->isSecure()) {
            $_SERVER['HTTPS'] = 'on';
        }
    }

    private function discard(int $level): void
    {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
    }

    /** Desk views write; public views never do. The list grows with the desks ported. */
    private function needsWrites(ResolvedUrl $url): bool
    {
        return in_array($url->routeKey(), (array) config('fec.legacy.writing_views', []), true);
    }
}
