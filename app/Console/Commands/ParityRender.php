<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * php artisan fec:parity:render /authors
 *
 * Renders one public path through the HTTP kernel and prints
 * "STATUS:<code>" on the first line, then the HTML. fec:parity runs it in
 * a fresh process per path, because legacy code keeps per-request state in
 * PHP statics (the newsletter block's "already rendered" flag, model
 * caches) that a web request never carries over and an in-process loop would.
 */
class ParityRender extends Command
{
    protected $signature = 'fec:parity:render {path : Public path}';

    protected $description = 'Render one path as the live hostname would see it (used by fec:parity)';

    protected $hidden = true;

    public function handle(): int
    {
        $host = (string) parse_url((string) config('fec.site_url'), PHP_URL_HOST);
        $path = '/'.ltrim((string) $this->argument('path'), '/');
        $request = self::makeRequest($host, $path);
        $kernel = app(Kernel::class);
        $response = $kernel->handle($request);
        $head = 'STATUS:'.$response->getStatusCode()."\n";
        if ($response->headers->has('Location')) {
            $head .= 'LOCATION:'.$response->headers->get('Location')."\n";
        }
        $this->output->write($head.(string) $response->getContent(), false);
        $kernel->terminate($request, $response);

        return self::SUCCESS;
    }

    /**
     * The GET request of a public path as the web server hands it over:
     * REQUEST_URI and QUERY_STRING as sent (Request::create() rebuilds them
     * with http_build_query(), "a,b" becoming "a%2Cb"; JURI read them raw).
     */
    public static function makeRequest(string $host, string $path): Request
    {
        $request = Request::create('https://'.$host.$path, 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'FEC-parity/1.0',
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_CF_IPCOUNTRY' => '',
        ]);
        $request->server->set('REQUEST_URI', $path);
        $request->server->set('QUERY_STRING', (string) (parse_url('https://'.$host.$path, PHP_URL_QUERY) ?? ''));

        return $request;
    }
}
