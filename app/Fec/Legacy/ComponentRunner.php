<?php

namespace App\Fec\Legacy;

use App\Fec\Legacy\Joomla\ExitException;
use App\Fec\Legacy\Joomla\Factory;
use App\Fec\Legacy\Joomla\RedirectException;
use App\Fec\Legacy\Joomla\Request as JRequest;
use App\Fec\Legacy\Joomla\Runtime;
use App\Fec\Legacy\Joomla\Uri;
use App\Fec\Url\PageHandler;
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

        Runtime::boot($option);
        Factory::useRwConnection($this->needsWrites($url));
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
        JRequest::seed($seed);
        Factory::getApplication()->getMenu()->activateFor($url->query);

        $body = '';
        $level = ob_get_level();
        ob_start();
        try {
            require $entry;
            $body = (string) ob_get_clean();
        } catch (RedirectException $e) {
            $this->discard($level);

            return redirect()->to($e->url, $e->status);
        } catch (ExitException $e) {
            // jexit(): what was echoed so far is the whole response (JSON tasks, raw output).
            $body = (string) ob_get_clean();

            return $this->raw($body, $request);
        } catch (Throwable $e) {
            $this->discard($level);
            // Joomla turned an exception carrying an HTTP code (new Exception('Author not found', 404)) into that error page.
            if ($e->getCode() >= 400 && $e->getCode() < 600) {
                abort((int) $e->getCode(), $e->getMessage());
            }
            throw $e;
        }

        $doc = Factory::getDocument();
        $format = strtolower((string) JRequest::getCmd('format', 'html'));
        $tmpl = strtolower((string) JRequest::getCmd('tmpl', ''));

        if ($format !== 'html' && $format !== '') {
            return $this->raw($body, $request);
        }
        if ($tmpl === 'component') {
            return response()->view('legacy.bare', ['doc' => $doc, 'body' => $body]);
        }

        $canonical = $doc->canonical();
        if ($canonical === null && $url->canonical !== null) {
            $canonical = Uri::site().$url->canonical;
        }

        $response = response()->view('legacy.component', [
            'doc' => $doc,
            'body' => $body,
            'canonical' => $canonical,
            'bodyClass' => '',
        ]);
        foreach (Factory::getApplication()->getHeaders() as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    private function raw(string $body, Request $request): Response
    {
        $headers = Factory::getApplication()->getHeaders();
        if (! isset($headers['Content-Type']) && json_decode($body) !== null) {
            $headers['Content-Type'] = 'application/json; charset=utf-8';
        }

        return response($body, 200, $headers);
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
