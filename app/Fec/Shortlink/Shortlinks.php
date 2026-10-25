<?php

namespace App\Fec\Shortlink;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * jos_shortlink: phrase => affiliate URL (com_shortlink's table, read as is).
 * The hit counter is cosmetic (jos_bonus_clicks is the tracker of record)
 * and is kept consistent with what com_shortlink wrote: counter + 1,
 * last_call = now.
 */
final class Shortlinks
{
    /** @return object{id: int, phrase: string, link: string, target: string}|null */
    public function find(string $phrase): ?object
    {
        if ($phrase === '' || strlen($phrase) > 100) {
            return null;
        }

        return DB::connection('fec')->table('shortlink')
            ->where('phrase', $phrase)
            ->first(['id', 'phrase', 'link', 'target']);
    }

    /** Best effort, never blocks the click. */
    public function hit(string $phrase): void
    {
        try {
            DB::connection('fec_rw')->table('shortlink')
                ->where('phrase', $phrase)
                ->update(['counter' => DB::raw('counter + 1'), 'last_call' => DB::raw('NOW()')]);
        } catch (Throwable $e) {
            Log::warning('Shortlinks: counter update failed', ['phrase' => $phrase, 'error' => $e->getMessage()]);
        }
    }
}
