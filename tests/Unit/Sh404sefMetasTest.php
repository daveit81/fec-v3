<?php

namespace Tests\Unit;

use App\Fec\Legacy\Joomla\Route;
use App\Fec\Legacy\Sh404sefMetas;
use PHPUnit\Framework\TestCase;

/**
 * The key sh404SEF looked a "Title and metas" row up with: the registry's
 * query with the request's own variables on top (the directory variants
 * measured on 29 Oct 2026: "/online-casinos?sort=newest" has the plugin's
 * title "Online Casinos" and no description on the live site, the plain
 * "/online-casinos" the row's).
 */
final class Sh404sefMetasTest extends TestCase
{
    private const DIRECTORY = 'index.php?option=com_content&Itemid=180&id=135&lang=en&view=category';

    private const HOME = 'index.php?option=com_content&Itemid=26&id=1&lang=en&view=article';

    public function test_a_request_without_a_query_string_keeps_the_registry_spelling(): void
    {
        $this->assertSame(self::DIRECTORY, Sh404sefMetas::lookupKey(self::DIRECTORY, []));
        $this->assertSame(self::DIRECTORY, Sh404sefMetas::currentNonSefUrl(self::DIRECTORY, []));
    }

    public function test_tracking_variables_do_not_change_the_key(): void
    {
        $this->assertSame(self::DIRECTORY, Sh404sefMetas::lookupKey(self::DIRECTORY, ['utm_source' => 'nl', 'gclid' => 'x']));
        $this->assertSame(self::DIRECTORY, Sh404sefMetas::lookupKey(self::DIRECTORY, ['UTM_Medium' => 'mail']));
    }

    public function test_the_request_own_variables_join_the_key_sorted(): void
    {
        $this->assertSame(
            'index.php?option=com_content&Itemid=180&id=135&lang=en&sort=newest&view=category',
            Sh404sefMetas::lookupKey(self::DIRECTORY, ['sort' => 'newest'])
        );
        $this->assertSame(
            'index.php?option=com_content&Itemid=180&filters=accepts_country%2Ccrypto&id=135&lang=en&view=category',
            Sh404sefMetas::lookupKey(self::DIRECTORY, ['filters' => 'accepts_country,crypto', 'utm_source' => 'x'])
        );
        // fbclid is not a tracking variable for sh404SEF
        $this->assertSame(
            'index.php?option=com_content&Itemid=180&fbclid=abc&id=135&lang=en&view=category',
            Sh404sefMetas::lookupKey(self::DIRECTORY, ['fbclid' => 'abc'])
        );
    }

    public function test_the_current_non_sef_url_is_the_merged_query_decoded(): void
    {
        $this->assertSame(
            'index.php?option=com_content&Itemid=180&id=135&lang=en&view=category&filters=accepts_country,crypto',
            Sh404sefMetas::currentNonSefUrl(self::DIRECTORY, ['filters' => 'accepts_country,crypto'])
        );
        // a request variable of the same name wins
        $this->assertSame(
            'index.php?option=com_content&Itemid=180&id=135&lang=en&view=row',
            Sh404sefMetas::currentNonSefUrl(self::DIRECTORY, ['view' => 'row'])
        );
    }

    public function test_the_home_with_a_query_string_is_not_the_home_for_sh404sef(): void
    {
        $this->assertSame(self::HOME, Sh404sefMetas::lookupKey(self::HOME, [], true));
        $this->assertSame('index.php?fbclid=abc', Sh404sefMetas::currentNonSefUrl(self::HOME, ['fbclid' => 'abc'], true));
        $this->assertSame('index.php?fbclid=abc&lang=en', Sh404sefMetas::lookupKey(self::HOME, ['fbclid' => 'abc'], true));
        // the tracking variable strips the "?" with it: shSortUrl leaves the string as it is
        $this->assertSame('index.php&lang=en', Sh404sefMetas::lookupKey(self::HOME, ['utm_source' => 'nl'], true));
    }

    public function test_sort_url_puts_option_first(): void
    {
        $this->assertSame(
            'index.php?option=com_jreviews&lang=en&url=tag%2Flanguages%2Fen',
            Sh404sefMetas::sortUrl('index.php?url=tag/languages/en&lang=en&option=com_jreviews')
        );
        $this->assertSame('index.php?a=1&b=2', Sh404sefMetas::sortUrl('index.php?b=2&a=1'));
        $this->assertSame('index.php&lang=en', Sh404sefMetas::sortUrl('index.php&lang=en'));
    }

    public function test_jroute_looks_a_link_up_as_sh404sef_spelled_it(): void
    {
        $withLang = new \ReflectionMethod(Route::class, 'withLang');
        // the details view's casino link: "lang=en" added, sorted, as the stored newurl of cashy-casino
        $this->assertSame(
            'index.php?option=com_bonuses&cid=1243&lang=en&view=casino',
            Sh404sefMetas::sortUrl($withLang->invoke(null, 'index.php?option=com_bonuses&cid=1243&view=casino'))
        );
        $this->assertSame(
            'index.php?option=com_content&Itemid=180&id=8&lang=en&view=category',
            Sh404sefMetas::sortUrl($withLang->invoke(null, 'index.php?option=com_content&amp;view=category&amp;id=8&amp;Itemid=180#top'))
        );
    }

    public function test_jroute_query_strings_are_decoded_as_jur_built_them(): void
    {
        $this->assertSame('filters=accepts_country,crypto&sort=newest', Route::buildQuery(['filters' => 'accepts_country,crypto', 'sort' => 'newest']));
    }
}
