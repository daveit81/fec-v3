<?php

namespace Tests\Unit;

use App\Fec\Desk\JackpotsDesk;
use PHPUnit\Framework\TestCase;

/** The rules of the rewritten jackpot desk: one column, 0 or 1, and the old freshness label. */
final class JackpotsDeskTest extends TestCase
{
    public function test_published_is_zero_or_one_only(): void
    {
        $this->assertSame(1, JackpotsDesk::published('1'));
        $this->assertSame(0, JackpotsDesk::published('0'));
        $this->assertSame(1, JackpotsDesk::published(1));
        foreach (['', '2', 'yes', '01', ' 1', null, ['1'], true] as $bad) {
            $this->assertNull(JackpotsDesk::published($bad));
        }
        $this->assertSame(['published'], JackpotsDesk::EDITABLE);
    }

    public function test_a_jid_is_any_bound_string_but_empty_or_control(): void
    {
        $this->assertTrue(JackpotsDesk::validJid('1234_MegaMoolah'));
        $this->assertTrue(JackpotsDesk::validJid("0_Mega Fortune's"));
        $this->assertFalse(JackpotsDesk::validJid(''));
        $this->assertFalse(JackpotsDesk::validJid(null));
        $this->assertFalse(JackpotsDesk::validJid(['1']));
        $this->assertFalse(JackpotsDesk::validJid("1_a\nb"));
        $this->assertFalse(JackpotsDesk::validJid(str_repeat('a', 192)));
    }

    public function test_freshness_as_the_old_dashboard(): void
    {
        $now = strtotime('2026-10-30 12:00:00');
        $this->assertSame('Good', JackpotsDesk::freshness('2026-10-30 11:50:00', $now));
        $this->assertSame('Fair', JackpotsDesk::freshness('2026-10-30 00:00:00', $now));
        $this->assertSame('Fair', JackpotsDesk::freshness('2026-10-29 12:00:00', $now));
        $this->assertSame('Poor', JackpotsDesk::freshness('2026-10-29 11:59:59', $now));
        $this->assertSame('Poor', JackpotsDesk::freshness(null, $now));
        $this->assertSame('Poor', JackpotsDesk::freshness('', $now));
    }
}
