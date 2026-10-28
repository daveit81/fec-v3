<?php

namespace Tests\Unit;

use App\Fec\Legacy\Sh404sefPage;
use PHPUnit\Framework\TestCase;

/**
 * The canonical branches of the live copy of sh404SEF's shDoTitleTags, on the
 * pages measured on 28 Oct 2026 (curl of the live heads): which canonical each
 * one carries and where it comes from.
 */
final class Sh404sefPageTest extends TestCase
{
    private const SITE = 'https://www.freeextrachips.com';

    /** @param  list<string>  $existing */
    private function canon(array $existing, string $sh, ?int $menu, int $catid, string $path, ?string $page = null): array
    {
        return Sh404sefPage::canonicals($existing, $sh, $menu, $catid, self::SITE.$path, $page, self::SITE);
    }

    public function test_review_keeps_one_canonical_rewritten_to_the_relative_route(): void
    {
        // the override's absolute custom tag, menu item 26, catid 8: rewritten in place
        $this->assertSame(['/online-casinos/king-billy-casino'], $this->canon(
            [self::SITE.'/online-casinos/king-billy-casino'], '/online-casinos/king-billy-casino', 26, 8, '/online-casinos/king-billy-casino'));
    }

    public function test_clusters_index_without_canonical_gets_the_page_url(): void
    {
        $this->assertSame([self::SITE.'/online-casinos/clusters/'], $this->canon([], '', 180, 0, '/online-casinos/clusters/'));
    }

    public function test_cluster_article_without_canonical_gets_the_page_url(): void
    {
        $this->assertSame([self::SITE.'/online-casinos/clusters/techsolutions'], $this->canon(
            [], '/online-casinos/clusters/techsolutions', 180, 153, '/online-casinos/clusters/techsolutions'));
    }

    public function test_blog_post_canonical_becomes_the_relative_route(): void
    {
        $this->assertSame(['/blog/a-post'], $this->canon([self::SITE.'/blog/a-post'], '/blog/a-post', 1261, 154, '/blog/a-post'));
    }

    public function test_category_with_its_own_canonical_keeps_it(): void
    {
        $this->assertSame([self::SITE.'/online-casinos'], $this->canon([self::SITE.'/online-casinos'], '', 180, 0, '/online-casinos'));
    }

    public function test_article_on_the_home_menu_item_outside_the_reviews_gets_the_bare_home(): void
    {
        $this->assertSame([self::SITE], $this->canon([], '/', 26, 2, '/'));
        // inserted at the top, the page's own stays below it
        $this->assertSame([self::SITE, '/x'], $this->canon(['/x'], '/x', 26, 2, '/x'));
    }

    public function test_two_canonicals_are_removed_and_the_page_url_inserted(): void
    {
        $this->assertSame([self::SITE.'/online-casinos/a'], $this->canon(['/a', self::SITE.'/a'], '/online-casinos/a', 26, 8, '/online-casinos/a'));
    }

    public function test_category_on_the_home_menu_item_without_canonical_has_none(): void
    {
        $this->assertSame([], $this->canon([], '', 26, 0, '/somewhere'));
    }

    public function test_legacy_page_parameter_is_kept_on_an_inserted_category_canonical(): void
    {
        $this->assertSame([self::SITE.'/online-casinos/clusters/?page=3'], $this->canon([], '', 180, 0, '/online-casinos/clusters/', '3'));
    }
}
