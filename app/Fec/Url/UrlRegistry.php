<?php

namespace App\Fec\Url;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The URL registry of the convivenza: sh404SEF's tables read as they are.
 *
 * jos_sh404sef_urls   oldurl = public path without leading slash (as sh404SEF
 *                     stores it, e.g. "online-casinos/king-billy-casino"),
 *                     newurl = Joomla query ("index.php?option=com_content&view=article&id=..."),
 *                     rank 0 = canonical, rank > 0 = duplicate alias of the same newurl,
 *                     empty newurl = recorded 404.
 * jos_redirection     oldurl -> newurl 301 rules kept by the desk.
 *
 * Nothing is imported or rewritten: phase 1 resolves requests straight from
 * here, so the legacy site and v3 always agree on what a path means. The
 * Router composes redirectFor() + the run-time patterns + lookup(); resolve()
 * is the registry-only composition used by the probes.
 */
final class UrlRegistry
{
    /*
    | No connection in the constructor on purpose: the container aliases
    | ConnectionInterface to the DEFAULT connection (SQLite), which would
    | silently point the registry at the wrong database when injected.
    */
    private function db(): ConnectionInterface
    {
        return DB::connection('fec');
    }

    /** Normalise a request path the way sh404SEF keys it: no leading slash, trailing slash kept as stored. */
    public static function normalize(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?? $path;
        $path = ltrim(trim($path), '/');

        return $path === 'index.php' ? '' : $path;
    }

    /** The spellings sh404SEF may have stored for one path. @return list<string> */
    public static function variants(string $path): array
    {
        $path = self::normalize($path);

        return array_values(array_unique([$path, rtrim($path, '/'), rtrim($path, '/').'/']));
    }

    /** Registry-only resolution: desk redirect, then the SEF table. */
    public function resolve(string $path): ResolvedUrl
    {
        return $this->redirectFor($path) ?? $this->lookup($path);
    }

    /** 1. desk redirects win (they are deliberate). Null when no rule matches. */
    public function redirectFor(string $path): ?ResolvedUrl
    {
        $path = self::normalize($path);

        $rule = $this->db()->table('redirection')
            ->whereIn('oldurl', self::variants($path))
            ->orderByRaw('(oldurl = ?) desc', [$path])
            ->orderBy('rank')
            ->first(['id', 'oldurl', 'newurl', 'rank']);

        if ($rule && trim((string) $rule->newurl) !== '') {
            return new ResolvedUrl('redirect', $path, [], null, $this->publicUrl((string) $rule->newurl), (int) $rule->id, (int) $rule->rank, (string) $rule->newurl, 'redirection');
        }

        return null;
    }

    /** 2. the SEF registry: page, alias, gone or miss. */
    public function lookup(string $path): ResolvedUrl
    {
        $path = self::normalize($path);

        // The request's own spelling first: sh404SEF matched the stored oldurl exactly, and the
        // table may hold both "bonuses/free-spins" and "bonuses/free-spins/" as rows of their
        // own (different queries, both rank 0); the other spelling is only the slash fallback.
        $row = $this->db()->table('sh404sef_urls')
            ->whereIn('oldurl', self::variants($path))
            ->orderByRaw('(oldurl = ?) desc', [$path])
            ->orderBy('rank')
            ->first(['id', 'oldurl', 'newurl', 'rank']);
        if (! $row) {
            return new ResolvedUrl('miss', $path);
        }

        $newurl = trim((string) $row->newurl);
        if ($newurl === '') {
            return new ResolvedUrl('gone', $path, [], null, null, (int) $row->id, (int) $row->rank, '');
        }

        $query = self::parseQuery($newurl);

        // sh404SEF answers a request that differs from the stored spelling only by the
        // trailing slash (/bonuses for the stored "bonuses/") with a 301 to the stored one.
        $stored = ltrim((string) $row->oldurl, '/');
        if ($stored !== $path && rtrim($stored, '/') === rtrim($path, '/')) {
            return new ResolvedUrl('redirect', $path, $query, null, '/'.$stored, (int) $row->id, (int) $row->rank, $newurl);
        }

        if ((int) $row->rank > 0) {
            $canonical = $this->db()->table('sh404sef_urls')
                ->where('newurl', $newurl)
                ->where('rank', 0)
                ->value('oldurl');

            return new ResolvedUrl('alias', $path, $query, $canonical !== null ? '/'.ltrim((string) $canonical, '/') : null, null, (int) $row->id, (int) $row->rank, $newurl);
        }

        // the canonical is the registry's own spelling (a trailing slash included: /bonuses/<casino>/)
        return new ResolvedUrl('page', $path, $query, '/'.ltrim((string) $row->oldurl, '/'), null, (int) $row->id, 0, $newurl);
    }

    /**
     * Public path (leading slash) for a Joomla query, when the registry knows it.
     * Exact newurl match first; then the same query modulo parameter order,
     * "lang" and "Itemid" (jos_redirection targets are written by hand and
     * rarely spell the query the way sh404SEF stored it). Null when the
     * component has no SEF URL at all (com_kunena: the forum is gone).
     */
    public function pathFor(string $newurl, bool $tolerant = true): ?string
    {
        $old = $this->db()->table('sh404sef_urls')->where('newurl', $newurl)->where('rank', 0)->value('oldurl');
        if ($old !== null) {
            return '/'.ltrim((string) $old, '/');
        }

        return $tolerant ? $this->pathForTolerant($newurl) : null;
    }

    private function pathForTolerant(string $newurl): ?string
    {
        $want = self::significant(self::parseQuery($newurl));
        if (! isset($want['option']) || $want['option'] === '') {
            return null;
        }

        // Candidates share every variable of the wanted query (decorations apart), so the
        // first pass sees the right row even for a component with thousands of URLs: with
        // option alone, com_bonuses has 17k rows and the first 500 never held the one.
        // None: a variable no stored URL carries (a visitor's "?fbclid=" on the page's own
        // query, which sh404SEF's meta plugin routed for the canonical): the identifiers alone.
        $candidates = $this->candidates($want, false);
        if ($candidates->isEmpty()) {
            $candidates = $this->candidates($want, true);
        }
        foreach ($candidates as $row) {
            if (self::significant(self::parseQuery((string) $row->newurl)) === $want) {
                return '/'.ltrim((string) $row->oldurl, '/');
            }
        }

        // Second pass: same option, view and identifiers; a catid or another
        // decoration the desk wrote differently does not make another page
        // (Joomla routes an article by id, whatever catid the link carries).
        // Variables the stored URL does not encode (edit=, q=, a filter) travel
        // on as the query string, the way sh404SEF appended non-SEF variables
        // ("/methodology-admin?edit=fxcheck"); limitstart is spelled "start".
        $identity = array_intersect_key($want, array_flip(self::IDENTITY_KEYS));
        if (count($identity) < 2) {
            return null;
        }
        foreach ($candidates as $row) {
            $have = self::significant(self::parseQuery((string) $row->newurl));
            if (array_intersect_key($have, array_flip(self::IDENTITY_KEYS)) === $identity) {
                $path = '/'.ltrim((string) $row->oldurl, '/');
                $extra = array_diff_key($want, $have, array_flip(self::DECORATION_KEYS));
                if (isset($extra['limitstart'])) {
                    $extra = ['start' => $extra['limitstart']] + $extra;
                    unset($extra['limitstart']);
                }

                // sh404SEF appended them through JURI, whose buildQuery() decodes the values
                // ("?filters=accepts_country,crypto", not "%2C")
                return $extra === [] ? $path : $path.'?'.urldecode(http_build_query($extra, '', '&'));
            }
        }

        return null;
    }

    /**
     * Rank-0 rows of the wanted option whose newurl carries each of the wanted
     * variables (decorations and leftovers apart; with $identityOnly, only the
     * identifying ones), oldest first.
     *
     * @param  array<string, string>  $want
     * @return Collection<int, object>
     */
    private function candidates(array $want, bool $identityOnly): Collection
    {
        $q = $this->db()->table('sh404sef_urls')
            ->where('rank', 0)
            ->where('newurl', 'like', '%option='.$want['option'].'%');
        foreach ($want as $key => $value) {
            if ($key === 'option' || $value === '' || in_array($key, self::DECORATION_KEYS, true) || in_array($key, self::LEFTOVER_KEYS, true) || ! preg_match('/^[A-Za-z0-9_.:-]+$/', (string) $value)) {
                continue;
            }
            if ($identityOnly && ! in_array($key, self::IDENTITY_KEYS, true)) {
                continue;
            }
            $q->where('newurl', 'like', '%'.$key.'='.$value.'%');
        }

        return $q->orderBy('id')->limit(2000)->get(['oldurl', 'newurl']);
    }

    /** Query variables that identify a page on their own (with option and view). */
    private const IDENTITY_KEYS = ['option', 'view', 'task', 'id', 'bid', 'cid', 'slug', 'topic', 'provider', 'edition', 'kind'];

    /** Variables a link may carry that never reach the SEF URL (Joomla routes an article by id). */
    private const DECORATION_KEYS = ['catid'];

    /** Variables sh404SEF never stored in newurl: they ride on as the query string of the SEF path. */
    private const LEFTOVER_KEYS = ['limitstart', 'start', 'limit', 'page', 'tmpl', 'format', 'layout', 'preview', 'edit', 'q', 'return', 'n', 'v3test', 'filters', 'sort'];

    /**
     * The query variables that identify a page: everything but "lang" and
     * "Itemid", sorted by key, so two spellings of one URL compare equal.
     *
     * @param  array<string, string>  $query
     * @return array<string, string>
     */
    public static function significant(array $query): array
    {
        unset($query['lang'], $query['Itemid']);
        // Joomla's "id=12:alias" (JRoute with the article alias) is the row with id=12.
        foreach (['id', 'catid'] as $key) {
            if (isset($query[$key]) && preg_match('/^(\d+):/', (string) $query[$key], $m)) {
                $query[$key] = $m[1];
            }
        }
        ksort($query);

        return $query;
    }

    /** @return array<string, string> option/view/id/... from "index.php?option=...". */
    public static function parseQuery(string $newurl): array
    {
        $qs = parse_url($newurl, PHP_URL_QUERY) ?? (str_contains($newurl, '=') ? $newurl : '');
        parse_str((string) $qs, $out);

        return array_map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v, $out);
    }

    /**
     * Record a SEF URL a plugin port built, as sh404SEF's sefGetLocation() did the
     * first time it generated one: a new pair gets rank 0 and the "automatic" date
     * (0000-00-00); a path another query already owns gets the next rank (the
     * duplicate rows Joomla serves as aliases); a recorded 404 for that path is
     * upgraded to the pair. The only write of the public side besides the click
     * log, on the writing connection whatever the page runs on.
     */
    public function register(string $oldurl, string $newurl): void
    {
        $oldurl = ltrim($oldurl, '/');
        if ($oldurl === '' || $newurl === '') {
            return;
        }
        $rw = DB::connection('fec_rw');
        $rows = $rw->table('sh404sef_urls')
            ->where('oldurl', $oldurl)
            ->orderBy('rank')
            ->get(['id', 'newurl', 'rank', 'dateadd']);

        foreach ($rows as $row) {
            if ((string) $row->newurl === $newurl) {
                return; // already there (another request got in first)
            }
        }

        if ($rows->isEmpty()) {
            $rw->table('sh404sef_urls')->insert(['oldurl' => $oldurl, 'newurl' => $newurl, 'rank' => 0, 'dateadd' => '0000-00-00']);

            return;
        }

        $first = $rows->first();
        if (trim((string) $first->newurl) === '') {
            $rw->table('sh404sef_urls')->where('id', $first->id)->update(['newurl' => $newurl, 'dateadd' => '0000-00-00']);

            return;
        }

        $auto = (string) $first->dateadd === '0000-00-00' || $first->dateadd === null;
        $rw->table('sh404sef_urls')->insert([
            'oldurl' => $oldurl,
            'newurl' => $newurl,
            'rank' => (int) $rows->max('rank') + 1,
            'dateadd' => $auto ? '0000-00-00' : date('Y-m-d'),
        ]);
    }

    /** A redirect target as sh404SEF stores it may be a path or a full URL; keep absolute ones. */
    private function publicUrl(string $newurl): string
    {
        if (preg_match('#^https?://#i', $newurl)) {
            return $newurl;
        }
        if (str_starts_with($newurl, 'index.php')) {
            return $this->pathFor($newurl) ?? '/'.$newurl;
        }

        return '/'.ltrim($newurl, '/');
    }

    /**
     * Counts of canonical URLs per option/view: the map of the work per phase.
     *
     * @return list<object{option: string, view: string, urls: int}>
     */
    public function stats(): array
    {
        $rows = $this->db()->table('sh404sef_urls')
            ->where('rank', 0)
            ->where('newurl', '<>', '')
            ->get(['newurl']);

        $counts = [];
        foreach ($rows as $row) {
            $q = self::parseQuery((string) $row->newurl);
            $key = ($q['option'] ?? '(none)').'|'.($q['view'] ?? ($q['task'] ?? '(none)'));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);

        $out = [];
        foreach ($counts as $key => $n) {
            [$option, $view] = explode('|', $key, 2);
            $out[] = (object) ['option' => $option, 'view' => $view, 'urls' => $n];
        }

        return $out;
    }
}
