<?php

namespace Tests\Unit;

use App\Fec\Url\WwwRedirect;
use PHPUnit\Framework\TestCase;

/** sh404SEF's "enforce www": the bare domain answers 301 to www (live access log, 29 Oct 2026). */
final class WwwRedirectTest extends TestCase
{
    private const SITE = 'https://www.freeextrachips.com';

    public function test_the_bare_domain_goes_to_www_with_path_and_query_as_sent(): void
    {
        $this->assertSame('https://www.freeextrachips.com/bonuses/desert-nights/', WwwRedirect::target(self::SITE, 'freeextrachips.com', 'GET', '/bonuses/desert-nights/'));
        $this->assertSame('https://www.freeextrachips.com/online-casinos?filters=a,b&utm_source=x', WwwRedirect::target(self::SITE, 'FreeExtraChips.com', 'HEAD', '/online-casinos?filters=a,b&utm_source=x'));
        $this->assertSame('https://www.freeextrachips.com/', WwwRedirect::target(self::SITE, 'freeextrachips.com', 'GET', '/'));
    }

    public function test_never_on_www_or_another_host(): void
    {
        $this->assertNull(WwwRedirect::target(self::SITE, 'www.freeextrachips.com', 'GET', '/'));
        $this->assertNull(WwwRedirect::target(self::SITE, 'v3.freeextrachips.com', 'GET', '/'));
        $this->assertNull(WwwRedirect::target('https://freeextrachips.com', 'freeextrachips.com', 'GET', '/'));
    }

    public function test_what_can_redirect_from_refused(): void
    {
        $this->assertNull(WwwRedirect::target(self::SITE, 'freeextrachips.com', 'POST', '/'));
        $this->assertNull(WwwRedirect::target(self::SITE, 'freeextrachips.com', 'GET', '/authors?tmpl=component&view=fxreport'));
        $this->assertNull(WwwRedirect::target(self::SITE, 'freeextrachips.com', 'GET', '/x?no_html=1'));
        $this->assertNull(WwwRedirect::target(self::SITE, 'freeextrachips.com', 'GET', '/index2.php'));
        $this->assertNull(WwwRedirect::target(self::SITE, 'freeextrachips.com', 'GET', '/blog/', 'XMLHttpRequest'));
    }
}
