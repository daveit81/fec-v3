<?php

namespace App\Fec\Url\Patterns;

use App\Fec\Legacy\Sef\BuiltUrl;
use App\Fec\Legacy\Sef\ContentPlugin;
use App\Fec\Legacy\Sef\Sh404sef;
use App\Fec\Url\ResolvedUrl;
use App\Fec\Url\UrlPattern;
use App\Fec\Url\UrlRegistry;
use Throwable;

/**
 * /online-casinos/<alias>, /blog/<alias>, /online-casinos/clusters/<alias>: an
 * article that sh404SEF never registered (ContentPlugin's categories and rule).
 *
 * On the live site the row is written the first time a Joomla page links the
 * article, so a review or a post is reachable as soon as the directory or the
 * blog list shows it. After the root switch nothing of Joomla renders a public
 * page: this answers the article's own path even before any page of v3 linked
 * it (the editor opening it right after publishing) and records the row, as
 * JRoute does for the links (fec.legacy.sef_register).
 *
 * Only a live article (published, in its publishing window) of that category
 * with that alias, and only when the registry has no URL for it at all: an
 * article that has one (its alias changed after sh404SEF wrote it) keeps that
 * one, and this path stays a 404, as on the live site. Off unless
 * fec.legacy.sef_content.enabled.
 */
final class ContentArticle implements UrlPattern
{
    public function name(): string
    {
        return 'content_article';
    }

    public function match(string $path, UrlRegistry $registry): ?ResolvedUrl
    {
        if (! config('fec.legacy.sef_content.enabled', false)) {
            return null;
        }
        $rules = ContentPlugin::rules();
        $path = UrlRegistry::normalize($path);
        $hit = ContentPlugin::split($path, $rules);
        if ($hit === null) {
            return null;
        }
        [$catid, $alias] = $hit;

        $article = ContentPlugin::byAlias($catid, $alias, time());
        if ($article === null) {
            return null;
        }
        $id = (int) $article->id;
        $itemid = $rules[$catid]['itemid'];
        $newurl = ContentPlugin::nonSef($id, $catid, $itemid);
        if ($registry->pathFor($newurl, true) !== null) {
            return null;
        }

        if (config('fec.legacy.sef_register', false)) {
            try {
                Sh404sef::register(new BuiltUrl($path, $newurl));
            } catch (Throwable $e) {
                error_log('content_article: registering '.$path.' failed: '.$e->getMessage());
            }
        }

        return new ResolvedUrl('page', $path, ContentPlugin::canonicalQuery($id, $catid, $itemid), '/'.$path, null, null, 0, $newurl, 'pattern:'.$this->name(), [
            'article_id' => $id,
            'catid' => $catid,
        ]);
    }
}
