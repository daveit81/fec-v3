<?php

namespace App\Fec\Url;

use Illuminate\Database\ConnectionInterface;
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

        $row = $this->db()->table('sh404sef_urls')
            ->whereIn('oldurl', self::variants($path))
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

        if ((int) $row->rank > 0) {
            $canonical = $this->db()->table('sh404sef_urls')
                ->where('newurl', $newurl)
                ->where('rank', 0)
                ->value('oldurl');

            return new ResolvedUrl('alias', $path, $query, $canonical !== null ? '/'.ltrim((string) $canonical, '/') : null, null, (int) $row->id, (int) $row->rank, $newurl);
        }

        return new ResolvedUrl('page', $path, $query, '/'.$path, null, (int) $row->id, 0, $newurl);
    }

    /** Public path (leading slash) for a Joomla query, when the registry knows it. */
    public function pathFor(string $newurl): ?string
    {
        $old = $this->db()->table('sh404sef_urls')->where('newurl', $newurl)->where('rank', 0)->value('oldurl');

        return $old === null ? null : '/'.ltrim((string) $old, '/');
    }

    /** @return array<string, string> option/view/id/... from "index.php?option=...". */
    public static function parseQuery(string $newurl): array
    {
        $qs = parse_url($newurl, PHP_URL_QUERY) ?? (str_contains($newurl, '=') ? $newurl : '');
        parse_str((string) $qs, $out);

        return array_map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v, $out);
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
