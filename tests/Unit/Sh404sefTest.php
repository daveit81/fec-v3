<?php

namespace Tests\Unit;

use App\Fec\Legacy\Sef\BonusesPlugin;
use App\Fec\Legacy\Sef\Sh404sef;
use PHPUnit\Framework\TestCase;

/**
 * The branches of the com_bonuses plugin port that need no database row, against
 * paths the registry holds (fec:sef:check covers the whole table on the server).
 */
class Sh404sefTest extends TestCase
{
    public function test_title_to_location_is_sh404sef_s_slug(): void
    {
        $this->assertSame('sloto-cash-casino/', Sh404sef::titleToLocation('Sloto Cash Casino/'));
        $this->assertSame('20-free-spins-id5742', Sh404sef::titleToLocation('20-Free Spins-id5742'));
        $this->assertSame('harry-s-casino', Sh404sef::titleToLocation("Harry's Casino"));
        $this->assertSame('cafe-casino', Sh404sef::titleToLocation("Caf\u{00E9} Casino"));
        $this->assertSame('bet-win', Sh404sef::titleToLocation('Bet & Win'));
        $this->assertSame('foo-bar', Sh404sef::titleToLocation('  Foo--Bar. '));
        $this->assertSame('/', Sh404sef::titleToLocation('/'));
        $this->assertSame('', Sh404sef::titleToLocation(''));
    }

    public function test_location_joins_and_collapses_slashes(): void
    {
        $this->assertSame('bonuses/', Sh404sef::location(['bonuses', '']));
        $this->assertSame('bonuses/no-deposit-casinos', Sh404sef::location(['bonuses', '', '/', 'no-deposit-casinos']));
        $this->assertSame('bonuses/yachting-casino/', Sh404sef::location(['bonuses', '', '/', 'Yachting Casino/']));
    }

    public function test_show_lists(): void
    {
        $this->assertSame('bonuses/no-deposit-casinos', $this->path('index.php?option=com_bonuses&lang=en&type=nd&view=show'));
        $this->assertSame('bonuses/free-spins', $this->path('index.php?option=com_bonuses&lang=en&type=fs&view=show'));
        $this->assertSame('bonuses/free-spins/no-deposit/', $this->path('index.php?option=com_bonuses&view=show&type=fs&subtype=nd'));
        $this->assertSame('bonuses/high-roller-bonuses', $this->path('index.php?option=com_bonuses&lang=en&type=hr&view=show'));
        $this->assertSame('bonuses/1st-deposit-match', $this->path('index.php?option=com_bonuses&lang=en&type=su&view=show'));
        $this->assertSame('bonuses/', $this->path('index.php?option=com_bonuses&view=show'));
    }

    public function test_crypto_list_is_stored_as_crypto_yes(): void
    {
        $built = Sh404sef::build('index.php?option=com_bonuses&lang=en&type=crypto&view=show', ['option' => 'com_bonuses', 'lang' => 'en', 'type' => 'crypto', 'view' => 'show']);
        $this->assertSame('bonuses/crypto', $built->path);
        $this->assertSame('index.php?option=com_bonuses&lang=en&view=show&crypto=yes', $built->nonSef);
    }

    public function test_redirect_views_tools_and_search(): void
    {
        $this->assertSame('bonuses/tc/811/5742/', $this->path('index.php?option=com_bonuses&view=tc&cid=811&bid=5742'));
        $this->assertSame('bonuses/ctr/811/5742/', $this->path('index.php?option=com_bonuses&view=ads&cid=811&bid=5742'));
        $this->assertSame('tools/wagering-calculator', $this->path('index.php?option=com_bonuses&view=wcalc'));
        $this->assertSame('tools/casino-comparator', $this->path('index.php?option=com_bonuses&view=cmp'));
        $this->assertSame('bonuses/software/rtg/', $this->path('index.php?option=com_bonuses&view=search&filter=software&fq=rtg'));
        $this->assertSame('bonuses/no-deposit-casinos/software/rtg/', $this->path('index.php?option=com_bonuses&view=search&type=nd&filter=software&fq=rtg'));
        $this->assertSame('bonuses/free-spins/wr/30x/', $this->path('index.php?option=com_bonuses&view=search&type=fs&filter=wr&fq=30&meth=x'));
    }

    public function test_desk_views_and_tasks_stay_raw(): void
    {
        foreach (['index.php?option=com_bonuses&view=add', 'index.php?option=com_bonuses&view=manage', 'index.php?option=com_bonuses&task=actions.save'] as $url) {
            $this->assertNull(Sh404sef::build($url, $this->query($url)), $url);
        }
    }

    public function test_non_sef_url_gets_lang(): void
    {
        $url = 'index.php?option=com_bonuses&view=tc&cid=811&bid=5742';
        $this->assertSame($url.'&lang=en', Sh404sef::build($url, $this->query($url))->nonSef);
        $url = 'index.php?option=com_bonuses&lang=en&type=nd&view=show';
        $this->assertSame($url, Sh404sef::build($url, $this->query($url))->nonSef);
    }

    public function test_type_labels_match_the_legacy_array(): void
    {
        $this->assertCount(36, BonusesPlugin::TYPE_LABELS);
        $this->assertSame('No Deposit Bonus', BonusesPlugin::TYPE_LABELS['nd']);
    }

    /** @return array<string, string> */
    private function query(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $out);

        return array_map('strval', $out);
    }

    private function path(string $url): ?string
    {
        return Sh404sef::build($url, $this->query($url))?->path;
    }
}
