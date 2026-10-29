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

        $agent = [['forbidden-agent', 'AppEngine']];
        $this->assertSame('forbidden', HtaccessRedirects::match('/authors', $agent, '', 'AppEngine-Google; (+http://code.google.com/appengine)')->kind);
        $this->assertNull(HtaccessRedirects::match('/authors', $agent, '', 'Mozilla/5.0'));
    }

    public function test_the_root_rules_answer_in_the_file_order(): void
    {
        // a slice of fec.legacy.htaccess_redirects as of 29 Oct 2026, in its order
        $rules = [
            ['gone', '^(online-casinos/(software|banking|countries|languages|currencies|type)|casino-reviews|reviews|countries)(/.*)?$'],
            ['rewrite', '^blog-v2/(.+)$', '/blog/$1'],
            ['gone', '^casino-bonus-blog/(tags|blogger|teamblog|archive|dashboard|login|register)(/.*)?$'],
            ['rewrite', '^casino-bonus-blog/(.+)$', '/blog/$1'],
            ['rewrite', '^(.*)\\.html$', '/$1'],
            ['gone', '^(log-in|login|create-an-account|view-user-profile|cb-[^/]+|jpots)(/.*)?$'],
            ['redirect', '/reviews...', 'https://www.freeextrachips.com/'],
            ['redirect', '/casino-bonus-blog/blogger/listings/peterg', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/online-casinos/craps.com-casino', 'https://www.freeextrachips.com/sitemap'],
            ['redirect', '/home', 'https://www.freeextrachips.com/'],
        ];
        $kind = fn (string $path) => HtaccessRedirects::match($path, $rules)?->kind;
        $to = fn (string $path) => HtaccessRedirects::match($path, $rules)?->target;

        // the dead sections before the .html rule: casinouk's old canonical is a 410 at once
        $this->assertSame('gone', $kind('/reviews/online-casino/casinouk.html'));
        $this->assertSame('gone', $kind('/online-casinos/software/netent'));
        $this->assertNull($kind('/online-casinos/softwares'));
        // a RewriteRule that answers wins over a Redirect of the same path (13 Oct: 410)
        $this->assertSame('gone', $kind('/casino-bonus-blog/blogger/listings/peterg'));
        $this->assertSame('/blog/a-post', $to('/casino-bonus-blog/a-post'));
        $this->assertSame('/blog/x', $to('/blog-v2/x'));
        $this->assertSame('/no_deposit', $to('/no_deposit.html'));
        $this->assertSame('gone', $kind('/log-in'));
        $this->assertSame('gone', $kind('/cb-profile'));
        $this->assertSame('gone', $kind('/jpots/mega-moolah'));
        $this->assertNull($kind('/logins'));
        $this->assertSame('https://www.freeextrachips.com/sitemap', $to('/online-casinos/craps.com-casino'));
        $this->assertSame('https://www.freeextrachips.com/', $to('/home'));
        $this->assertNull($kind('/homepage'));
    }

    public function test_nothing_else_matches(): void
    {
        $this->assertNull($this->target('/bonuses/no-deposit-casinos'));
        $this->assertNull($this->target('/bonuses'));
        $this->assertNull(HtaccessRedirects::match('/bonuses/free-cash', []));
    }
}
