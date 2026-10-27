<?php

namespace App\Console\Commands\Legacy;

use App\Fec\Legacy\ComponentRunner;
use App\Fec\Url\ResolvedUrl;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Base of the commands that run a key-gated task of a ported component the
 * way its cron did (curl on "index.php?option=...&task=...&key=..."): the
 * request is built here, the key comes from the editions config file outside
 * the docroot (define FEC_PI_CRON_KEY), the component answers JSON and sets
 * the HTTP status (403 bad key, 500 failure), which becomes the exit code.
 */
abstract class SnapshotCommand extends Command
{
    /** @param  array<string, string>  $params  task parameters besides option, task and key */
    protected function runLegacyTask(string $option, string $task, array $params): int
    {
        $key = $this->cronKey();
        if ($key === null) {
            $this->error('No FEC_PI_CRON_KEY in '.$this->editionsConfig().': the task would refuse everything.');

            return self::FAILURE;
        }

        $query = ['option' => $option, 'task' => $task] + array_filter($params, fn ($v) => $v !== null && $v !== '') + ['key' => $key];
        $host = (string) parse_url((string) config('fec.site_url'), PHP_URL_HOST);
        $request = Request::create('https://'.$host.'/index.php?'.http_build_query($query), 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'FEC-v3-scheduler/1.0',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        app()->instance('request', $request);
        $url = new ResolvedUrl('page', 'index.php', $query, null, null, null, null, 'index.php?'.http_build_query($query), 'index.php');

        $response = app(ComponentRunner::class)->runTask($request, $url);
        $status = $response->getStatusCode();
        $body = (string) $response->getContent();
        $json = json_decode($body, true);

        if (is_array($json)) {
            $this->line(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line($body);
        }
        if ($status !== 200 || ! is_array($json) || empty($json['ok'])) {
            $this->error('HTTP '.$status.(is_array($json) && isset($json['error']) ? ' - '.$json['error'] : ''));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function editionsConfig(): string
    {
        return (string) config('fec.legacy.editions_config', '/home/freeextr/conf/fec_payout_index.php');
    }

    /** FEC_PI_CRON_KEY from the editions config (the file the legacy tasks read too). */
    protected function cronKey(): ?string
    {
        $file = $this->editionsConfig();
        if (! is_readable($file)) {
            return null;
        }
        if (! defined('FEC_PI_CRON_KEY')) {
            include_once $file;
        }
        if (! defined('FEC_PI_CRON_KEY')) {
            return null;
        }
        $key = trim((string) constant('FEC_PI_CRON_KEY'));

        return strlen($key) >= 16 ? $key : null;
    }
}
