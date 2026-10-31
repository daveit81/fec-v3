<?php

namespace App\Fec\Legacy\Sef;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * sh404SEF's URLs of com_content articles, for the categories that still get new
 * ones: what its com_content plugin wrote on the live site, measured on the
 * registry on 31 Oct 2026 (every published article of these categories has its
 * row, 806 + 714 + 18):
 *
 *   catid 8   (casino reviews)  online-casinos/<alias>            Itemid 26
 *   catid 154 (blog)            blog/<alias>                      Itemid 1261
 *   catid 153 (clusters)        online-casinos/clusters/<alias>   no Itemid
 *
 * with the article's alias (not its title: "3,000x" is "3-000x", "93.5" is
 * "93-5") and the non-SEF URL stored as "index.php?option=com_content&Itemid=26
 * &catid=8&id=1972&lang=en&view=article" (option first, the rest in byte order).
 * fec:sef:check --option=com_content rebuilds every stored row with this rule.
 *
 * Why it exists: until the root switch sh404SEF writes the row the first time a
 * Joomla page links a new article; after it no public page is Joomla's, and a
 * new review, post or cluster would have no URL. The categories and their
 * Itemid are config (fec.legacy.sef_content.categories); the whole port is off
 * until fec.legacy.sef_content.enabled (Sh404sef::build()).
 */
final class ContentPlugin implements SefPlugin
{
    /** @var array<int, object|null> article id => id, alias, catid, state, publish_up, publish_down */
    private static array $articles = [];

    public static function title(array &$vars): ?array
    {
        if (($vars['view'] ?? '') !== 'article' || ! preg_match('/^(\d+)/', (string) ($vars['id'] ?? ''), $m)) {
            return null;
        }
        $article = self::article((int) $m[1]);
        if ($article === null || (int) $article->state !== 1) {
            return null;
        }
        $rule = self::rules()[(int) $article->catid] ?? null;
        $alias = trim((string) $article->alias);
        if ($rule === null || $alias === '') {
            return null;
        }

        $vars = self::canonicalQuery((int) $article->id, (int) $article->catid, $rule['itemid']);

        return [...explode('/', $rule['prefix']), $alias];
    }

    /**
     * The categories the port covers: catid => prefix (no slashes at the ends) and the
     * Itemid sh404SEF stored with their articles (null: none).
     *
     * @return array<int, array{prefix: string, itemid: int|null}>
     */
    public static function rules(): array
    {
        $out = [];
        foreach ((array) config('fec.legacy.sef_content.categories', []) as $catid => $rule) {
            $prefix = trim((string) ($rule['prefix'] ?? ''), '/');
            if ((int) $catid <= 0 || ! preg_match('#^[a-z0-9-]+(/[a-z0-9-]+)*$#', $prefix)) {
                continue;
            }
            $itemid = $rule['itemid'] ?? null;
            $out[(int) $catid] = ['prefix' => $prefix, 'itemid' => is_numeric($itemid) && (int) $itemid > 0 ? (int) $itemid : null];
        }

        return $out;
    }

    /**
     * The article's query as sh404SEF stored it: option first, then the rest in
     * byte order (Itemid before catid), "lang=en" included.
     *
     * @return array<string, string>
     */
    public static function canonicalQuery(int $id, int $catid, ?int $itemid): array
    {
        $query = ['option' => 'com_content'];
        if ($itemid !== null) {
            $query['Itemid'] = (string) $itemid;
        }

        return $query + ['catid' => (string) $catid, 'id' => (string) $id, 'lang' => 'en', 'view' => 'article'];
    }

    /** "index.php?..." of canonicalQuery(). */
    public static function nonSef(int $id, int $catid, ?int $itemid): string
    {
        return 'index.php?'.http_build_query(self::canonicalQuery($id, $catid, $itemid));
    }

    /**
     * Which covered category a request path belongs to, and the alias it asks for:
     * [catid, alias], or null. One segment after the prefix, in the spelling sh404SEF
     * stores (lower case, no trailing slash); the longest prefix wins
     * (online-casinos/clusters/x is a cluster, not a review called "clusters/x").
     *
     * @param  array<int, array{prefix: string, itemid: int|null}>  $rules
     * @return array{0: int, 1: string}|null
     */
    public static function split(string $path, array $rules): ?array
    {
        $path = ltrim($path, '/');
        uasort($rules, fn ($a, $b) => strlen($b['prefix']) <=> strlen($a['prefix']));
        foreach ($rules as $catid => $rule) {
            $prefix = $rule['prefix'].'/';
            if (! str_starts_with($path, $prefix)) {
                continue;
            }
            $alias = substr($path, strlen($prefix));

            return preg_match('#^[a-z0-9][a-z0-9-]*$#', $alias) ? [(int) $catid, $alias] : null;
        }

        return null;
    }

    /** Published now as com_content shows it: state 1, publish_up passed, publish_down not (UTC, as Joomla 2.5 stores them). */
    public static function isLive(object $article, int $now): bool
    {
        if ((int) $article->state !== 1) {
            return false;
        }
        $up = (string) ($article->publish_up ?? '');
        if ($up !== '' && ! str_starts_with($up, '0000') && strtotime($up.' UTC') > $now) {
            return false;
        }
        $down = (string) ($article->publish_down ?? '');

        return $down === '' || str_starts_with($down, '0000') || strtotime($down.' UTC') > $now;
    }

    /** The article row (cached per process: fec:sef:check reads every one of them). */
    public static function article(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }
        if (! array_key_exists($id, self::$articles)) {
            try {
                self::$articles[$id] = DB::connection('fec')->table('content')->where('id', $id)
                    ->first(['id', 'alias', 'catid', 'state', 'publish_up', 'publish_down']);
            } catch (Throwable $e) {
                error_log('sef content: reading article '.$id.' failed: '.$e->getMessage());
                self::$articles[$id] = null;
            }
        }

        return self::$articles[$id];
    }

    /** The live article of a covered category with this alias, or null. */
    public static function byAlias(int $catid, string $alias, int $now): ?object
    {
        try {
            $rows = DB::connection('fec')->table('content')
                ->where('catid', $catid)->where('alias', $alias)->where('state', 1)
                ->orderBy('id')
                ->get(['id', 'alias', 'catid', 'state', 'publish_up', 'publish_down']);
        } catch (Throwable $e) {
            error_log('sef content: looking up '.$catid.'/'.$alias.' failed: '.$e->getMessage());

            return null;
        }
        foreach ($rows as $row) {
            if (self::isLive($row, $now)) {
                return $row;
            }
        }

        return null;
    }

    /** For the tests and the check command. */
    public static function forget(): void
    {
        self::$articles = [];
    }
}
