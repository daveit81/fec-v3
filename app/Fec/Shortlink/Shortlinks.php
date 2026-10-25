<?php

namespace App\Fec\Shortlink;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * jos_shortlink: phrase => target (com_shortlink's table, read as is).
 * com_shortlink (ShortlinkControllerBase::display) does, on every found
 * phrase: counter + 1 and last_call = JFactory::getDate()->toMySQL() (UTC),
 * then header("Location: ...") = 302. Target rules, kept here as is:
 *   numeric link      -> the article with that id (routed by the legacy site)
 *   absolute URL      -> as is
 *   anything else     -> relative to the site (routed by the legacy site)
 *   unknown phrase    -> site base URL
 * The counter is cosmetic (jos_bonus_clicks is the tracker of record).
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

    /** Where a found shortlink sends the visitor (com_shortlink's three cases). */
    public function target(object $row): string
    {
        $link = trim((string) $row->link);
        $site = rtrim((string) config('fec.site_url'), '/');

        if ($link !== '' && ctype_digit($link) && (int) $link > 0) {
            // article id: the legacy router turns this into the SEF URL (301)
            return $site.'/index.php?option=com_content&view=article&id='.(int) $link;
        }
        if (preg_match('#^(https?|ftp|file)://|^mailto:#i', $link)) {
            return $link;
        }

        return $site.'/'.ltrim($link, '/');
    }

    /** Best effort, never blocks the click. */
    public function hit(string $phrase): void
    {
        try {
            DB::connection('fec_rw')->table('shortlink')
                ->where('phrase', $phrase)
                ->update(['counter' => DB::raw('counter + 1'), 'last_call' => gmdate('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            Log::warning('Shortlinks: counter update failed', ['phrase' => $phrase, 'error' => $e->getMessage()]);
        }
    }
}
