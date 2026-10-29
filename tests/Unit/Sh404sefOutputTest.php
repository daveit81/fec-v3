<?php

namespace Tests\Unit;

use App\Fec\Legacy\Sh404sefOutput;
use PHPUnit\Framework\TestCase;

/**
 * sh404SEF's canonical survives the core "sef" plugin: the placeholder looks
 * like a URL scheme, which the plugin's regex skips.
 */
final class Sh404sefOutputTest extends TestCase
{
    public function test_a_relative_canonical_comes_back_as_written(): void
    {
        Sh404sefOutput::reset();
        $token = Sh404sefOutput::protect('reviews/online-casino/casinouk.html');
        $html = '<link href="'.$token.'" rel="canonical" /><a href="relative/link">x</a>';

        // the core plugin's rule: relative src/href get the base, a scheme is left alone
        $protocols = '[a-zA-Z0-9]+:';
        $html = (string) preg_replace('#(src|href|poster)="(?!/|'.$protocols.'|\#|\')([^"]*)"#m', '$1="/$2"', $html);

        $this->assertSame(
            '<link href="reviews/online-casino/casinouk.html" rel="canonical" /><a href="/relative/link">x</a>',
            Sh404sefOutput::restore($html)
        );
    }

    public function test_the_href_is_escaped_as_the_layout_escapes(): void
    {
        Sh404sefOutput::reset();
        $token = Sh404sefOutput::protect('/online-casinos/a?x=1&amp;y=2');
        $this->assertSame('href="/online-casinos/a?x=1&amp;amp;y=2"', Sh404sefOutput::restore('href="'.$token.'"'));
        $this->assertSame('nothing to do', Sh404sefOutput::restore('nothing to do'));
    }
}
