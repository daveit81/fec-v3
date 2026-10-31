<?php

namespace Tests\Unit;

use App\Fec\Legacy\Sef\ContentPlugin;
use App\Fec\Legacy\Sef\Sh404sef;
use PHPUnit\Framework\TestCase;

/** The URLs of new articles after the root switch: sh404SEF's rule for reviews, blog and clusters. */
final class ContentSefTest extends TestCase
{
    /** @return array<int, array{prefix: string, itemid: int|null}> */
    private function rules(): array
    {
        return [
            8 => ['prefix' => 'online-casinos', 'itemid' => 26],
            154 => ['prefix' => 'blog', 'itemid' => 1261],
            153 => ['prefix' => 'online-casinos/clusters', 'itemid' => null],
        ];
    }

    public function test_the_stored_non_sef_url_as_sh404sef_wrote_it(): void
    {
        // registry rows of 31 Oct 2026 (ids 1972, 1971, 1965)
        $this->assertSame('index.php?option=com_content&Itemid=26&catid=8&id=1972&lang=en&view=article', ContentPlugin::nonSef(1972, 8, 26));
        $this->assertSame('index.php?option=com_content&Itemid=1261&catid=154&id=1971&lang=en&view=article', ContentPlugin::nonSef(1971, 154, 1261));
        $this->assertSame('index.php?option=com_content&catid=153&id=1965&lang=en&view=article', ContentPlugin::nonSef(1965, 153, null));
    }

    public function test_the_path_is_prefix_and_alias(): void
    {
        $this->assertSame('online-casinos/betory-casino', Sh404sef::location(['online-casinos', 'betory-casino']));
        $this->assertSame(
            'blog/six-feet-under-by-nolimit-city-the-3-000x-booster-and-1-in-93-5-million-odds',
            Sh404sef::location(['blog', 'six-feet-under-by-nolimit-city-the-3-000x-booster-and-1-in-93-5-million-odds'])
        );
        $this->assertSame('online-casinos/clusters/saphirix', Sh404sef::location([...explode('/', 'online-casinos/clusters'), 'saphirix']));
    }

    public function test_split_a_request_path(): void
    {
        $r = $this->rules();
        $this->assertSame([8, 'betory-casino'], ContentPlugin::split('online-casinos/betory-casino', $r));
        $this->assertSame([8, 'betory-casino'], ContentPlugin::split('/online-casinos/betory-casino', $r));
        $this->assertSame([153, 'saphirix'], ContentPlugin::split('online-casinos/clusters/saphirix', $r));
        $this->assertSame([154, 'gator-hunters-2-by-nolimit-city'], ContentPlugin::split('blog/gator-hunters-2-by-nolimit-city', $r));
        // not one of ours, or not one segment in sh404SEF's spelling
        foreach (['online-casinos', 'online-casinos/', 'online-casinos/betory-casino/', 'online-casinos/Betory-Casino',
            'online-casinos/a/b', 'blog/', 'blogs/x', 'bonuses/x', 'online-casinos/clusters/', 'online-casinos/-x', 'blog/x.html'] as $path) {
            $this->assertNull(ContentPlugin::split($path, $r), $path);
        }
    }

    public function test_live_means_published_and_in_its_window(): void
    {
        $now = strtotime('2026-11-10 12:00:00 UTC');
        $a = fn (int $state, string $up, string $down) => (object) ['state' => $state, 'publish_up' => $up, 'publish_down' => $down];
        $this->assertTrue(ContentPlugin::isLive($a(1, '2026-11-10 11:00:00', '0000-00-00 00:00:00'), $now));
        $this->assertTrue(ContentPlugin::isLive($a(1, '0000-00-00 00:00:00', ''), $now));
        $this->assertTrue(ContentPlugin::isLive($a(1, '2026-11-01 00:00:00', '2026-12-01 00:00:00'), $now));
        $this->assertFalse(ContentPlugin::isLive($a(1, '2026-11-10 13:00:00', '0000-00-00 00:00:00'), $now));
        $this->assertFalse(ContentPlugin::isLive($a(1, '2026-11-01 00:00:00', '2026-11-10 11:59:59'), $now));
        $this->assertFalse(ContentPlugin::isLive($a(0, '2026-11-01 00:00:00', ''), $now));
        $this->assertFalse(ContentPlugin::isLive($a(-2, '2026-11-01 00:00:00', ''), $now));
    }
}
