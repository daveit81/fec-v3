<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Phase 0c smoke test: proves that v3 runs on PHP 8.4, that the framework
 * database works and that the live FEC catalogue (connection "fec") is
 * readable. Counts only, no column names: the legacy schema is read as is.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $report = $this->report();

        return response()->json($report, $report['ok'] ? 200 : 503);
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $framework = $this->probe(fn () => DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));

        $fec = $this->probe(function () {
            $db = DB::connection('fec');

            return [
                'server' => $db->selectOne('SELECT VERSION() AS v')->v,
                'database' => $db->getDatabaseName(),
                'tables' => [
                    'bonuses' => $db->table('bonuses')->count(),
                    'bonus_clicks' => $db->table('bonus_clicks')->count(),
                    'content' => $db->table('content')->count(),
                    'jreviews_content' => $db->table('jreviews_content')->count(),
                    'sh404sef_urls' => $db->table('sh404sef_urls')->count(),
                    'redirection' => $db->table('redirection')->count(),
                ],
            ];
        });

        return [
            'ok' => $framework['ok'] && $fec['ok'],
            'app' => config('app.name'),
            'env' => config('app.env'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'time' => now()->toIso8601String(),
            'framework_db' => $framework,
            'fec_db' => $fec,
        ];
    }

    /**
     * Run a check and never let it throw: the page must render even when a
     * database is down, so the failure itself is the information.
     *
     * @return array<string, mixed>
     */
    private function probe(callable $check): array
    {
        try {
            return ['ok' => true, 'result' => $check()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
