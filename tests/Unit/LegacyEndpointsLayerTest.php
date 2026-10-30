<?php

namespace Tests\Unit;

use App\Fec\Legacy\Joomla\Database;
use App\Fec\Legacy\Joomla\Log;
use App\Fec\Legacy\Joomla\Query;
use PHPUnit\Framework\TestCase;

/** Layer pieces the phase 5 endpoints need: JDatabaseQuery::setLimit, JLog without loggers. */
final class LegacyEndpointsLayerTest extends TestCase
{
    public function test_set_limit_appends_mysqli_limit_to_a_select(): void
    {
        $q = (new Query(new Database('fec')))->select('yes_count, no_count')->from('`#__bonus_feedback_agg`')->where('bid=5')->setLimit(1);
        $this->assertStringEndsWith("WHERE bid=5 LIMIT 0, 1", (string) $q);

        $q->clear();
        $q->select('a')->from('t');
        $this->assertStringNotContainsString('LIMIT', (string) $q);
    }

    public function test_jlog_without_a_logger_writes_nothing(): void
    {
        // No addLogger() for "newsletter", as on the live CMS: the facade is never reached
        // (in a bare unit test it has no application and would throw).
        Log::add('Resend confirm failed for someone@example.com', Log::ERROR, 'newsletter');
        $this->assertTrue(true);
    }
}
