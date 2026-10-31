<?php

namespace Tests\Unit;

use App\Fec\Desk\LinksDesk;
use PHPUnit\Framework\TestCase;

/** The rules of the affiliate links desk (jos_shortlink behind /goto.php). */
final class LinksDeskTest extends TestCase
{
    public function test_a_new_phrase(): void
    {
        $this->assertSame('betory', LinksDesk::phrase(' betory '));
        $this->assertSame('King_Billy-2.io', LinksDesk::phrase('King_Billy-2.io'));
        $this->assertSame(str_repeat('a', 100), LinksDesk::phrase(str_repeat('a', 100)));
        foreach (['', ' ', '-x', '.x', 'a b', 'a/b', 'a?b', 'a&b', "a\nb", 'caf'."\xC3\xA9", str_repeat('a', 101), null, ['a'], 12] as $bad) {
            $this->assertNull(LinksDesk::phrase($bad), var_export($bad, true));
        }
    }

    public function test_a_destination(): void
    {
        $this->assertSame('https://betorytrackers.com/djsto2lgd', LinksDesk::link(' https://betorytrackers.com/djsto2lgd '));
        $this->assertSame('https://bwredir.com/3dZK?p=%2Fcasino%2F', LinksDesk::link('https://bwredir.com/3dZK?p=%2Fcasino%2F'));
        $this->assertSame('http://tracking.casiredirect.com/click?pid=471&offer_id=584', LinksDesk::link('http://tracking.casiredirect.com/click?pid=471&offer_id=584'));
        $this->assertSame('/bonuses/', LinksDesk::link('/bonuses/'));
        foreach (['', 'betory.com/x', '//evil.example/x', 'javascript:alert(1)', 'ftp://x.example/a', 'https://', 'https://exa mple.com/', "https://example.com/\nx", '42', 'https://example.com/'.str_repeat('a', 250), null] as $bad) {
            $this->assertNull(LinksDesk::link($bad), var_export($bad, true));
        }
    }

    public function test_a_description_is_one_line(): void
    {
        $this->assertSame('Betory, welcome deal', LinksDesk::description("  Betory,\r\n  welcome\tdeal "));
        $this->assertSame(255, mb_strlen(LinksDesk::description(str_repeat("\u{e9}", 300))));
        $this->assertSame('', LinksDesk::description(null));
    }

    public function test_phrases_in_a_text(): void
    {
        $this->assertSame(['betory'], LinksDesk::phrasesIn('https://www.freeextrachips.com/goto.php?link=betory'));
        $this->assertSame(['kingbilly'], LinksDesk::phrasesIn('/goto.php?link=kingbilly&utm=x'));
        $this->assertSame(['a b'], LinksDesk::phrasesIn('goto.php?link=a%20b'));
        $html = '<p><a href="/goto.php?x=1&amp;link=one">One</a> and <a href=\'https://www.freeextrachips.com/goto.php?link=two#top\'>Two</a>'
            .' again <a href="/goto.php?link=one">One</a></p>';
        $this->assertSame(['one', 'two'], LinksDesk::phrasesIn($html));
        $this->assertSame([], LinksDesk::phrasesIn('https://casino.example/?link=nope'));
        $this->assertSame([], LinksDesk::phrasesIn('/goto.php?link='));
        // the parameters goto.php reads, in order (com_shortlink's "paramname")
        $this->assertSame(['b'], LinksDesk::phrasesIn('/goto.php?go=b&link=a', ['go', 'link']));
        $this->assertSame(['a'], LinksDesk::phrasesIn('/goto.php?link=a', ['go', 'link']));
    }

    public function test_key_go_url_host_when(): void
    {
        $this->assertSame('kingbilly', LinksDesk::key('KingBilly'));
        $this->assertSame('https://www.freeextrachips.com/goto.php?link=betory', LinksDesk::goUrl('https://www.freeextrachips.com/', 'betory'));
        $this->assertSame('https://www.freeextrachips.com/goto.php?link=a%20b', LinksDesk::goUrl('https://www.freeextrachips.com', 'a b'));
        $this->assertSame('betorytrackers.com', LinksDesk::host('https://BetoryTrackers.com/djsto2lgd'));
        $this->assertSame('this site', LinksDesk::host('/bonuses/'));
        $this->assertSame('', LinksDesk::when('0000-00-00 00:00:00'));
        $this->assertSame('', LinksDesk::when(null));
        $this->assertSame('2026-10-30 14:06', LinksDesk::when('2026-10-30 14:06:13'));
    }

    public function test_sorts_and_filters(): void
    {
        $this->assertSame(['id', 'desc'], LinksDesk::SORTS['newest']);
        $this->assertSame(['all', 'unused', 'idle', 'never'], LinksDesk::SHOWS);
    }
}
