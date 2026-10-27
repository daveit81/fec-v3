<?php

namespace Tests\Unit;

use App\Fec\Url\HtaccessRedirects;
use PHPUnit\Framework\TestCase;

/** The directives of the legacy .htaccess, each with its own matching (checked against the live site on 27 Oct 2026). */
class HtaccessRedirectsTest extends TestCase
{
    private const RULES = [
        ['rewrite', '^bonuses/no-deposit-casinos/allowed-countries/(?!(?:au|nz)/?$)', '/bonuses/no-deposit-casinos'],
        ['rewrite', '^bonuses/free-spins$', 'https://www.freeextrachips.com/bonuses/free-spins/'],
        ['match', '/bonuses/ads/(.*)', '/bonuses/ctr/$1'],
        ['match', '^/bonuses/7bit-casino/75-free-spins-id8157/?$', '/bonuses/7bit-casino/75-free-spins-id8676'],
        ['redirect', '/bonuses/currency/btc/', 'https://www.freeextrachips.com/bonuses/crypto'],
        ['redirect', '/bonuses/slots-bonuses', 'https://www.freeextrachips.com/bonuses/'],
        ['redirect', '/bonuses/free-cash', 'https://www.freeextrachips.com/bonuses/no-deposit-casinos'],
    ];

    private function target(string $path): ?string
    {
        return HtaccessRedirects::match($path, self::RULES)?->target;
    }

    public function test_redirect_is_a_whole_segment_prefix_with_the_rest_appended(): void
    {
        $hit = HtaccessRedirects::match('/bonuses/free-cash', self::RULES);
        $this->assertSame('redirect', $hit->kind);
        $this->assertSame('htaccess', $hit->source);
        $this->assertSame('https://www.freeextrachips.com/bonuses/no-deposit-casinos', $hit->target);
        $this->assertSame('https://www.freeextrachips.com/bonuses/no-deposit-casinos/x', $this->target('bonuses/free-cash/x'), 'as the live site answered /bonuses/free-cash/x');
        $this->assertSame('https://www.freeextrachips.com/bonuses/no-deposit-casinos/', $this->target('/bonuses/free-cash/'));
        $this->assertNull($this->target('/bonuses/free-cashier'), 'a longer segment is another path');
        $this->assertSame('https://www.freeextrachips.com/bonuses/crypto', $this->target('/bonuses/currency/btc/'));
        $this->assertNull($this->target('/bonuses/currency/btc'), 'a source ending with a slash needs the slash');
        $this->assertSame('https://www.freeextrachips.com/bonuses//', $this->target('/bonuses/slots-bonuses/'));
    }

    public function test_rewrite_rules(): void
    {
        $this->assertSame('https://www.freeextrachips.com/bonuses/free-spins/', $this->target('/bonuses/free-spins'));
        $this->assertNull($this->target('/bonuses/free-spins/'), 'anchored: the slash form is the page');
        $this->assertSame('/bonuses/no-deposit-casinos', $this->target('/bonuses/no-deposit-casinos/allowed-countries/de/'));
        $this->assertSame('/bonuses/no-deposit-casinos', $this->target('/bonuses/no-deposit-casinos/allowed-countries/'));
        $this->assertNull($this->target('/bonuses/no-deposit-casinos/allowed-countries/au/'), 'au and nz are pages (line 114)');
        $this->assertNull($this->target('/bonuses/no-deposit-casinos/allowed-countries/nz'));
    }

    public function test_redirect_match_with_groups(): void
    {
        $this->assertSame('/bonuses/ctr/811/5742/', $this->target('/bonuses/ads/811/5742/'));
        $this->assertSame('/bonuses/7bit-casino/75-free-spins-id8676', $this->target('/bonuses/7bit-casino/75-free-spins-id8157/'));
        $this->assertNull($this->target('/bonuses/7bit-casino/75-free-spins-id8157x'));
    }

    public function test_gone_and_forbidden_rules(): void
    {
        $rules = [
            ['gone', '^[^/]+/tag(/.*)?$'],
            ['forbidden-query', 'base64_encode[^(]*\\([^)]*\\)'],
            ['forbidden-query', '(<|%3C)([^s]*s)+cript.*(>|%3E)', 'NC'],
            ['forbidden-query', 'GLOBALS(=|\\[|\\%[0-9A-Z]{0,2})'],
            ['redirect', '/slots/tag', 'https://example.com/never'],
        ];
        $this->assertSame('gone', HtaccessRedirects::match('/slots/tag/powered-by-rival-gaming/', $rules)->kind);
        $this->assertSame('gone', HtaccessRedirects::match('/bonuses/tag', $rules)->kind);
        $this->assertNull(HtaccessRedirects::match('/slots/tags', $rules));
        $this->assertNull(HtaccessRedirects::match('/slots/a/tag', $rules), 'only the second segment');
        $this->assertSame('forbidden', HtaccessRedirects::match('/authors', $rules, 'q=base64_encode(x)')->kind);
        $this->assertSame('forbidden', HtaccessRedirects::match('/authors', $rules, 'x=%3CSCRIPT%3Ealert(1)%3C/script%3E')->kind, 'NC');
        $this->assertSame('forbidden', HtaccessRedirects::match('/authors', $rules, 'GLOBALS%5Bx%5D=1')->kind);
        $this->assertNull(HtaccessRedirects::match('/authors', $rules, 'utm_source=newsletter'));
        $this->assertNull(HtaccessRedirects::match('/authors', $rules));
    }

    public function test_nothing_else_matches(): void
    {
        $this->assertNull($this->target('/bonuses/no-deposit-casinos'));
        $this->assertNull($this->target('/bonuses'));
        $this->assertNull(HtaccessRedirects::match('/bonuses/free-cash', []));
    }
}
