<?php

namespace Tests\Unit;

use App\Fec\Url\Sh404sefAliases;
use PHPUnit\Framework\TestCase;

/** The Location of an sh404SEF alias, as the live site sent it on 27 Oct 2026 (curl against the origin). */
class Sh404sefAliasesTest extends TestCase
{
    /** the registry rows the live lookups found: newurl => oldurl (any rank) */
    private const REGISTRY = [
        'index.php?option=com_bonuses&bid=8675&lang=en&view=details' => 'bonuses/fortunejack/150percent-free-spins-id8675',
        'index.php?option=com_bonuses&cid=290&lang=en&view=casino' => 'bonuses/dictionary/',
        'index.php?option=com_bonuses&Itemid=911&lang=en&type=sl&view=show' => 'bonuses/slots-bonuses',
        'index.php?option=com_bonuses&bid=3484&cid=923&lang=en&type=nd&view=details' => 'bonuses/casinomax/usd30-no-deposit-bonus-id3484',
        'index.php?option=com_jreviews&Itemid=911&lang=en' => 'no-deposit',
    ];

    private function target(string $newurl, string $path, string $query = ''): ?string
    {
        return Sh404sefAliases::target($newurl, $path, $query, fn (string $n) => self::REGISTRY[$n] ?? '');
    }

    public function test_a_path_target_is_sent_as_written_with_the_query_string(): void
    {
        $this->assertSame('bonuses/', $this->target('bonuses/', 'bonuses/video-poker-bonuses'));
        $this->assertSame('bonuses/?utm_source=test', $this->target('bonuses/', 'bonuses/video-poker-bonuses', 'utm_source=test'));
        $this->assertSame('bonuses/mandarin-palace/', $this->target('bonuses/mandarin-palace/', 'bonuses/the-mandarin-palace'));
    }

    public function test_a_non_sef_target_becomes_its_stored_sef_url_absolute_without_query(): void
    {
        $this->assertSame('https://www.freeextrachips.com/bonuses/fortunejack/150percent-free-spins-id8675', $this->target('index.php?option=com_bonuses&bid=8675&lang=en&view=details', 'bonuses/150percent-free-spins-id8675'));
        $this->assertSame('https://www.freeextrachips.com/bonuses/slots-bonuses', $this->target('index.php?option=com_bonuses&Itemid=911&lang=en&type=sl&view=show', 'bonuses/bonuses/'), 'a rank 2 row counts');
        $this->assertSame('https://www.freeextrachips.com/no-deposit', $this->target('index.php?option=com_jreviews&Itemid=911&lang=en', 'bonuses/no'));
        $this->assertSame('https://www.freeextrachips.com/bonuses/casinomax/usd30-no-deposit-bonus-id3484', $this->target('index.php?option=com_bonuses&bid=3484&cid=923&lang=en&type=nd&view=details', 'bonuses/casinomax/usd300-no-deposit-bonus-id3484'));
        $this->assertSame('https://www.freeextrachips.com/bonuses/dictionary/', $this->target('index.php?option=com_bonuses&cid=290&lang=en&view=casino', 'bonuses/dictionary/', 'utm_source=test'), 'the query string is dropped');
    }

    public function test_a_non_sef_target_without_a_stored_url_goes_home(): void
    {
        $this->assertSame('https://www.freeextrachips.com/', $this->target('index.php?option=com_content&id=139&lang=en&view=category', 'slots/'));
    }

    public function test_an_alias_of_the_page_itself_serves_the_page(): void
    {
        $this->assertNull($this->target('index.php?option=com_bonuses&cid=290&lang=en&view=casino', 'bonuses/dictionary/'));
        $this->assertNull($this->target('', 'bonuses/x'));
    }

    public function test_an_absolute_target_is_kept(): void
    {
        $this->assertSame('http://www.freeextrachips.com/sitemap.html', $this->target('http://www.freeextrachips.com/sitemap.html', 'ja/live-slots-tournaments.html'));
    }
}
