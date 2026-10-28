<?php

namespace App\Fec\Legacy;

use App\Fec\Legacy\Joomla\Categories;
use App\Fec\Legacy\Joomla\CategoryNode;
use App\Fec\Legacy\Joomla\Document;
use App\Fec\Legacy\Joomla\Factory;
use App\Fec\Legacy\Joomla\Request as JRequest;
use App\Fec\Legacy\Joomla\Route;
use App\Fec\Legacy\Joomla\Uri;
use App\Fec\Url\ResolvedUrl;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * sh404SEF's page rewrite for the com_content pages: what
 * components/com_sh404sef/shPageRewrite.full.php (shDoTitleTags) did to the
 * finished page on the live site, with its com_content meta plugin
 * (components/com_sh404sef/meta_ext/com_content.php), 4.3.0.1671.
 *
 * The live copy of shDoTitleTags was edited by hand for FEC (menu item 26,
 * the home, and the categories 8 and 139 are named in it); this is that copy,
 * branch by branch, not sh404SEF's original. It runs on the document before
 * the layout instead of on the HTML after it: the title, the metas and the
 * canonical it decides are what the layout prints.
 *
 *  - Title: the meta plugin builds one from the content slugs (an article: its
 *    title, contentTitleIncludeCat = 5 puts no category in front; a category:
 *    its last two categories, "Clusters | Online Casinos", with " | page N"
 *    on a paginated list); a "Title and metas" row of jos_sh404sef_metas
 *    wins over it. The page gets it unless the request's catid is 8 (the
 *    reviews, whose override sets its own) or 139; on catid 8 a row's title
 *    still replaces the page's.
 *  - Description, keywords, robots: the row's, when set.
 *  - Canonical: on an article the plugin's canonical is JRoute of the page's
 *    own non-SEF URL (relative), a row's canonical wins. Then, on the canonicals
 *    the component put on the page: more than one are all removed
 *    (removeOtherCanonicals); with menu item 26 active and catid not 8 the home
 *    URL is inserted; with none left the page's URL (JURI::current()) is
 *    inserted, on a category only off menu item 26, with ?page= kept; else the
 *    remaining ones become the plugin's canonical. A category page with one
 *    canonical of its own keeps it.
 *
 * The site-wide "canonical" system plugin (raw-URI head link on onAfterRoute)
 * is enabled in #__extensions but none of its links reaches the live pages
 * (28 Oct 2026: the review page carries the override's tag rewritten in place,
 * the clusters index sh404SEF's at the top): it is not reproduced.
 * Not reproduced either: the home (sh404SEF's home code for the metas row:
 * phase 4 step 2), the multi-page article titles (no article has page breaks)
 * and the "featured" view (no page uses it).
 */
final class Sh404sefPage
{
    private const CAT_ALL_NESTED_CAT = 0;

    private const CAT_FIRST = 1;

    private const CAT_LAST = 2;

    private const CAT_2_FIRST = 3;

    private const CAT_2_LAST = 4;

    /**
     * Applies the rewrite to the document and returns the page's canonicals,
     * in page order (an inserted one first). Empty: the page has none.
     *
     * @return list<string>
     */
    public static function content(Document $doc, ResolvedUrl $url): array
    {
        $cfg = (array) config('fec.legacy.sh404sef', []);
        $row = Sh404sefMetas::row($url->newurl);

        $view = JRequest::getCmd('view');
        $id = JRequest::getInt('id');
        $catid = JRequest::getInt('catid');
        $layout = JRequest::getCmd('layout');
        $limit = JRequest::getInt('limit');
        $limitstart = JRequest::getInt('limitstart');
        $tmpl = JRequest::getCmd('tmpl');
        $print = JRequest::getInt('print');

        // ---- the meta plugin (meta_ext/com_content.php) ----
        $customTitle = '';
        $customRobots = ($tmpl === 'component' && $print !== 0) ? 'noindex, nofollow' : null;
        $shCanonical = '';
        if ($view !== 'featured') {
            if ($view === 'article' && $url->newurl !== null) {
                $nonSef = str_replace(['format=pdf', 'print=1'], '', $url->newurl);
                $shCanonical = Route::_($nonSef);
            }
            $title = self::contentSlugs($view, $id, $layout, $cfg);
            $pageNumber = '';
            if ($limit !== 0 && $limitstart !== 0 && $view !== 'article') {
                $shLimit = $layout === 'blog' ? (int) Factory::getApplication()->getCfg('list_limit', 20) : $limit;
                $pagenum = (int) ($limitstart / max(1, $shLimit)) + 1;
                $pattern = str_replace((string) ($cfg['pagerep'] ?? '-'), ' ', (string) ($cfg['page_text'] ?? 'page-%s'));
                $pageNumber = str_replace('%s', (string) $pagenum, $pattern)
                    .(! empty($cfg['always_append_items_per_page']) ? ($cfg['pagerep'] ?? '-').$shLimit : '');
            }
            $title = array_reverse($title);
            if ($pageNumber !== '') {
                $title[] = $pageNumber;
            }
            $sep = (string) ($cfg['page_title_separator'] ?? ' | ');
            $customTitle = ltrim(implode($sep, $title), '/'.$sep);
        }

        // ---- shDoTitleTags, as edited on the live site ----
        $rowTitle = $row !== null ? trim((string) $row->metatitle) : '';
        if ($rowTitle !== '') {
            $customTitle = (string) $row->metatitle;
        }
        if ($customTitle === '') {
            $customTitle = $doc->getTitle();
        }
        if ($customTitle !== '' && $catid !== 8 && $catid !== 139) {
            $pageTitle = htmlspecialchars(self::cleanTitle(($cfg['prepend_to_page_title'] ?? '').$customTitle.($cfg['append_to_page_title'] ?? '')), ENT_COMPAT, 'UTF-8');
            self::replaceTitle($doc, $pageTitle);
        }
        if ($rowTitle !== '' && $catid === 8) {
            // the row's title as it is, neither escaped nor protected: on the live
            // site "$1000 Free Play" came out as "00 Free Play" ($10 a backreference)
            self::replaceTitle($doc, (string) $row->metatitle);
        }

        if ($row !== null && trim((string) $row->metadesc) !== '') {
            $doc->setDescription(self::cleanDesc((string) $row->metadesc));
        }
        if ($row !== null && trim((string) $row->metakey) !== '') {
            $doc->setMetaData('keywords', self::cleanDesc((string) $row->metakey));
        }
        if ($row !== null && trim((string) $row->metarobots) !== '') {
            $customRobots = (string) $row->metarobots;
        }
        if ($customRobots !== null) {
            $doc->setMetaData('robots', $customRobots);
        }

        if ($row !== null && trim((string) $row->canonical) !== '') {
            $shCanonical = (string) $row->canonical;
        }

        // The active item as sh404SEF's router left it: the Itemid of the page's own URL
        // when the menu has it, none otherwise (classes/router.php sets it only then). The
        // layer's menu falls back to the home, as mod_menu does for its "active" class; the
        // live copy read getActive()->id, null without an Itemid, and so "not 26".
        $itemid = JRequest::getInt('Itemid');
        $menu = $itemid !== 0 && Factory::getApplication()->getMenu()->getItem($itemid) !== null ? $itemid : null;
        $page = request()->query('page');

        return self::canonicals(
            $doc->takeCanonicals(),
            $shCanonical,
            $menu,
            $catid,
            Uri::current(),
            is_scalar($page) ? (string) $page : null,
            Uri::site(),
            $cfg,
        );
    }

    /**
     * The canonical branches of the live shDoTitleTags, on the canonicals the
     * component put on the page ($existing, in page order): what the page
     * carries afterwards, an inserted one first.
     *
     * @param  list<string>  $existing
     * @param  array<string, mixed>  $cfg
     * @return list<string>
     */
    public static function canonicals(array $existing, string $shCanonical, ?int $menu, int $catid, string $base, ?string $page, string $site, array $cfg = []): array
    {
        $count = count($existing);
        if ($count > 1 && ($cfg['remove_other_canonicals'] ?? true)) {
            $existing = [];
            $count = 0;
        }
        $home = (int) ($cfg['home_menu_item'] ?? 26);

        $insert = [];
        if ($shCanonical !== '' && $menu === $home && $catid !== 8) {
            $insert[] = $site;
        } elseif ($shCanonical !== '' && $count === 0 && $menu !== $home) {
            $insert[] = $base;
        } elseif ($shCanonical === '' && $count === 0 && $menu !== $home) {
            $insert[] = $base.($page !== null ? '?page='.$page : '');
        } elseif ($shCanonical !== '' && $count === 0 && $menu === $home && $catid === 8) {
            $insert[] = $base;
        } elseif ($shCanonical !== '') {
            $existing = array_fill(0, $count, $shCanonical);
        }

        return array_values(array_merge($insert, $existing));
    }

    /**
     * sef_404::getContentSlugsArray() with the meta plugin's settings: the
     * category path and the article title, by title, never by alias.
     *
     * @param  array<string, mixed>  $cfg
     * @return list<string>
     */
    private static function contentSlugs(string $view, int $id, string $layout, array $cfg): array
    {
        try {
            switch ($view) {
                case 'category':
                    $slugs = [];
                    $insertName = $layout === 'blog' ? ! empty($cfg['insert_content_blog_name']) : ! empty($cfg['insert_content_table_name']);
                    if ($insertName) {
                        $prefix = (string) ($layout === 'blog' ? ($cfg['content_blog_name'] ?? '') : ($cfg['content_table_name'] ?? ''));
                        $prefix = $prefix !== '' ? $prefix : self::menuItemTitle();
                        if ($prefix !== '') {
                            $slugs[] = $prefix;
                        }
                    }
                    if ($id !== 0) {
                        $slugs = array_merge($slugs, self::categorySlugs($id, (int) ($cfg['include_content_cat_categories'] ?? self::CAT_2_LAST), ! empty($cfg['content_title_use_cat_alias']), $cfg));
                    } elseif (! $insertName && self::menuItemTitle() !== '') {
                        $slugs[] = self::menuItemTitle();
                    }
                    $slugs[] = '/';

                    return $slugs;
                case 'article':
                    $article = DB::connection('fec')->table('content')->where('id', $id)->first(['id', 'title', 'alias', 'catid']);
                    if ($article === null) {
                        return [];
                    }
                    $slugs = self::categorySlugs((int) $article->catid, (int) ($cfg['content_title_include_cat'] ?? self::CAT_ALL_NESTED_CAT), ! empty($cfg['content_title_use_cat_alias']), $cfg);
                    $slugs[] = ! empty($cfg['content_title_use_alias']) ? (string) $article->alias : (string) $article->title;

                    return $slugs;
                default:
                    return [];
            }
        } catch (Throwable $e) {
            error_log('sh404sef content title failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Sh404sefModelSlugs::getCategorySlugArray(): the path of the category
     * from the first level down, cut as the setting says (5 and anything
     * else unknown: none).
     *
     * @param  array<string, mixed>  $cfg
     * @return list<string>
     */
    private static function categorySlugs(int $catid, int $whichCat, bool $useAlias, array $cfg): array
    {
        $node = Categories::getInstance('Content')->get($catid);
        if (! $node instanceof CategoryNode) {
            return [];
        }
        if ((string) ($node->path ?? '') === 'uncategorised' && (int) ($node->level ?? 0) === 1) {
            $title = (int) ($cfg['slug_for_uncategorized_content'] ?? 0) === 0 ? '' : self::menuItemTitle();

            return $title === '' ? [] : [$title];
        }

        $path = [];
        $safer = 0;
        do {
            $path[] = $node;
            $node = $node->getParent();
            $isRoot = $node === null || $node->id === 'root';
            $safer++;
        } while (! $isRoot && $safer < 20);
        $path = array_reverse($path);

        switch ($whichCat) {
            case self::CAT_ALL_NESTED_CAT:
                break;
            case self::CAT_FIRST:
                $path = [array_shift($path)];
                break;
            case self::CAT_LAST:
                $path = [array_pop($path)];
                break;
            case self::CAT_2_FIRST:
                $path = array_slice($path, 0, 2);
                break;
            case self::CAT_2_LAST:
                $path = array_slice($path, -2);
                break;
            default:
                $path = [];
        }

        return array_values(array_map(fn (CategoryNode $n) => (string) ($useAlias ? $n->alias : $n->title), $path));
    }

    private static function menuItemTitle(): string
    {
        $item = Factory::getApplication()->getMenu()->getItem(JRequest::getInt('Itemid'));

        return $item !== null ? (string) ($item->title ?? '') : '';
    }

    /**
     * shCleanUpTitle(), PHP 5.6 defaults (html_entity_decode leaves the single
     * quotes encoded), with its escaping of "$nn" for the replacement below.
     */
    private static function cleanTitle(string $title): string
    {
        $title = trim(trim(stripslashes(html_entity_decode($title, ENT_COMPAT | ENT_HTML401, 'UTF-8'))), '"');

        return (string) (preg_replace('#\$([0-9]*)#u', '\\\$${1}', $title) ?? $title);
    }

    /**
     * The title tag replaced as ShlSystem_Strings::pr() replaced it in the
     * page: the new title is a preg replacement string ("$10" or "\1" are
     * backreferences, unmatched ones vanish), the page unchanged when the
     * replacement fails. Done on the tag Joomla's head renderer wrote, then
     * read back as text for the layout.
     */
    private static function replaceTitle(Document $doc, string $html): void
    {
        $tag = '<title>'.htmlspecialchars($doc->getTitle(), ENT_COMPAT, 'UTF-8').'</title>';
        $out = preg_replace('/\<\s*title\s*\>.*\<\s*\/title\s*\>/isuU', '<title>'.$html.'</title>', $tag);
        if ($out === null || $out === '' || ! preg_match('#<title>(.*)</title>#su', $out, $m)) {
            return;
        }
        $doc->setTitle(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** shCleanUpDesc() */
    private static function cleanDesc(string $desc): string
    {
        $desc = stripslashes(html_entity_decode(strip_tags($desc, '<br><br /><p></p>'), ENT_NOQUOTES | ENT_HTML401, 'UTF-8'));
        $desc = str_replace(['<br>', '<br />', '<p>', '</p>'], ' - ', $desc);
        while (str_contains($desc, ' -  - ')) {
            $desc = str_replace(' -  - ', ' - ', $desc);
        }
        $desc = str_replace(['&#39;', '&#039;'], "'", $desc);
        $desc = str_replace('"', "'", $desc);
        $desc = str_replace(["\r", "\n"], '', $desc);

        return mb_substr(trim($desc), 0, 512, 'UTF-8');
    }
}
