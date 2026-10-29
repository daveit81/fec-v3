<?php

namespace Tests\Unit;

use App\Fec\Url\RootSwitch;
use PHPUnit\Framework\TestCase;

/** The root switch: what Joomla keeps, and the block that sends the rest to v3. */
final class RootSwitchTest extends TestCase
{
    private function sample(): RootSwitch
    {
        $rows = [
            ['online-casinos', 'index.php?option=com_content&Itemid=180&id=135&lang=en&view=category'],
            ['online-casinos/operators', 'index.php?option=com_atlas&Itemid=1210&lang=en&view=operators'],
            ['dashboards/atlas-dashboard', 'index.php?option=com_atlas&Itemid=1213&lang=en&view=dashboard'],
            ['contactus/', 'index.php?option=com_contactus&Itemid=1001&lang=en&view=contactus'],
            ['methodology-admin', 'index.php?option=com_authors&Itemid=1200&lang=en&view=methodologyadmin'],
            ['methodology', 'index.php?option=com_authors&Itemid=1201&lang=en&view=methodologyhub'],
            ['bonuses/', 'index.php?option=com_bonuses&Itemid=21&lang=en&view=show'],
            ['bonuses/desk', 'index.php?option=com_bonuses&Itemid=22&lang=en&view=home'],
            ['jpots/', 'index.php?option=com_jackpots&Itemid=30&lang=en&view=jackpots'],
            ['log-in/', 'index.php?option=com_users&Itemid=31&lang=en&view=login'],
        ];
        $rules = [['gone', '^(log-in|jpots)(/.*)?$']];

        return RootSwitch::fromRows($rows, ['com_contactus'], ['com_atlas.dashboard', 'com_authors.methodologyadmin', 'com_bonuses.home'], ['administrator', 'index.php', 'dashboards'], $rules);
    }

    public function test_the_kept_paths_are_the_shortest_exclusive_prefixes(): void
    {
        $switch = $this->sample();
        // dashboards/atlas-dashboard is under the fixed "dashboards"; bonuses/desk shares "bonuses";
        // a kept path with nothing of its own below it stays exact
        $this->assertSame(['administrator', 'dashboards', 'index.php'], $switch->prefixes);
        $this->assertSame(['bonuses/desk', 'contactus', 'methodology-admin'], $switch->exact);
    }

    public function test_the_registry_urls_are_counted_by_what_v3_does_with_them(): void
    {
        $stats = $this->sample()->stats;
        $this->assertSame(1, $stats[RootSwitch::V3]['com_atlas.operators'][0]);
        $this->assertSame(1, $stats[RootSwitch::JOOMLA]['com_atlas.dashboard'][0]);
        $this->assertSame(['jpots/'], $stats['htaccess gone']['com_jackpots.jackpots'][1]);
        $this->assertArrayNotHasKey(RootSwitch::NOT_PORTED, $stats);
    }

    public function test_keeps(): void
    {
        $switch = $this->sample();
        $this->assertFalse($switch->keeps('/'));
        $this->assertTrue($switch->keeps('/', '', 'POST'));
        $this->assertFalse($switch->keeps('/blog/', '', 'HEAD'));
        $this->assertFalse($switch->keeps('/', 'fbclid=x'));
        $this->assertTrue($switch->keeps('/', 'option=com_jackpots&task=data.update'));
        $this->assertTrue($switch->keeps('/online-casinos', 'x=1&task=save'));
        $this->assertFalse($switch->keeps('/online-casinos', 'subtask=x'));
        $this->assertTrue($switch->keeps('/index.php/foo'));
        $this->assertTrue($switch->keeps('/dashboards/atlas-dashboard'));
        $this->assertTrue($switch->keeps('/contactus/'));
        $this->assertFalse($switch->keeps('/contactusx'));
        $this->assertFalse($switch->keeps('/contactus/x'));
        $this->assertTrue($switch->keeps('/bonuses/desk/'));
        $this->assertFalse($switch->keeps('/bonuses/desk/x'));
        $this->assertFalse($switch->keeps('/bonuses/'));
        $this->assertFalse($switch->keeps('/methodology'));
        $this->assertTrue($switch->keeps('/methodology-admin'));
    }

    public function test_the_block(): void
    {
        $block = implode("\n", $this->sample()->block(false, '2026-10-29'));
        $this->assertStringContainsString("RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$\nRewriteCond %{QUERY_STRING} !(^|&)(task|option)=\nRewriteRule ^$ /v3/index.php [L]", $block);
        $this->assertSame(2, substr_count($block, 'RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$'));
        $this->assertStringContainsString('RewriteCond %{REQUEST_URI} !^/(administrator|dashboards|index\.php)(/|$)', $block);
        $this->assertStringContainsString('RewriteCond %{REQUEST_URI} !^/(bonuses/desk|contactus|methodology\-admin)/?$', $block);
        $this->assertStringContainsString("RewriteCond %{REQUEST_FILENAME} !-d\nRewriteCond %{REQUEST_URI}", $block);
        $this->assertStringEndsWith("RewriteRule ^ /v3/index.php [L]\n</IfModule>\n", $block);
        $this->assertStringNotContainsString('v3test', $block);

        $test = implode("\n", $this->sample()->block(true, '2026-10-29'));
        $this->assertSame(2, substr_count($test, 'RewriteCond %{QUERY_STRING} (^|&)v3test=1(&|$)'));
    }

    public function test_only_what_joomla_s_catch_all_took_goes_to_v3(): void
    {
        $this->assertTrue(RootSwitch::catchAll('/'));
        $this->assertTrue(RootSwitch::catchAll('/bonuses/'));
        $this->assertTrue(RootSwitch::catchAll('/online-casinos/x'));
        $this->assertTrue(RootSwitch::catchAll('/no_deposit.html'));
        $this->assertTrue(RootSwitch::catchAll('/wp-login.php'));
        $this->assertTrue(RootSwitch::catchAll('/blog/feed/rss'));
        $this->assertTrue(RootSwitch::catchAll('/x.FEED'));
        $this->assertFalse(RootSwitch::catchAll('/.env'));
        $this->assertFalse(RootSwitch::catchAll('/images/missing.jpg'));
        $this->assertFalse(RootSwitch::catchAll('/bonuses/goldbet.io-casino'));
        $this->assertTrue(RootSwitch::catchAll('/bonuses/goldbet.io-casino/'));
        $this->assertFalse(RootSwitch::catchAll('/online-casinos/craps%2Ecom-casino'), 'decoded, as REQUEST_URI');

        $block = implode("\n", $this->sample()->block(false, '2026-10-29'));
        $this->assertStringContainsString("RewriteCond %{QUERY_STRING} !(^|&)(task|option)=\nRewriteCond %{REQUEST_URI} (/[^.]*|\\.(php|html?|feed|pdf|vcf|raw))$ [NC]\nRewriteCond %{REQUEST_FILENAME} !-f", $block);
    }

    public function test_status_classes(): void
    {
        $this->assertSame('200', RootSwitch::statusClass(304));
        $this->assertSame('30x', RootSwitch::statusClass(302));
        $this->assertSame('30x', RootSwitch::statusClass(301));
        $this->assertSame('404', RootSwitch::statusClass(404));
        $this->assertSame('5xx', RootSwitch::statusClass(503));
    }
}
