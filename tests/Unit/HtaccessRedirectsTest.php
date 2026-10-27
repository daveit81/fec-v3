<?php

namespace Tests\Unit;

use App\Fec\Url\HtaccessRedirects;
use PHPUnit\Framework\TestCase;

class HtaccessRedirectsTest extends TestCase
{
    private const RULES = [
        'bonuses/free-cash' => 'https://www.freeextrachips.com/bonuses/no-deposit-casinos',
        'bonuses/slots-bonuses' => 'https://www.freeextrachips.com/bonuses/',
    ];

    public function test_exact_and_segment_prefix_match_as_mod_alias(): void
    {
        $hit = HtaccessRedirects::match('/bonuses/free-cash', self::RULES);
        $this->assertSame('redirect', $hit->kind);
        $this->assertSame('htaccess', $hit->source);
        $this->assertSame('https://www.freeextrachips.com/bonuses/no-deposit-casinos', $hit->target);

        $this->assertSame('https://www.freeextrachips.com/bonuses/no-deposit-casinos/x/y', HtaccessRedirects::match('bonuses/free-cash/x/y', self::RULES)->target);
        $this->assertNull(HtaccessRedirects::match('/bonuses/free-cashier', self::RULES), 'a longer segment is another path');
        $this->assertSame('https://www.freeextrachips.com/bonuses/no-deposit-casinos/', HtaccessRedirects::match('/bonuses/free-cash/', self::RULES)->target);
        $this->assertSame('https://www.freeextrachips.com/bonuses//', HtaccessRedirects::match('/bonuses/slots-bonuses/', self::RULES)->target, 'the rest of the path is appended as is');
        $this->assertNull(HtaccessRedirects::match('/bonuses/no-deposit-casinos', self::RULES));
        $this->assertNull(HtaccessRedirects::match('/bonuses', []));
    }
}
