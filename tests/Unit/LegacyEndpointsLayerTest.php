<?php

namespace Tests\Unit;

use App\Fec\Legacy\Joomla\Database;
use App\Fec\Legacy\Joomla\Log;
use App\Fec\Legacy\Joomla\Query;
use PHPUnit\Framework\TestCase;

/** Layer pieces the phase 5 endpoints meet: Joomla 2.5's JDatabaseQuery::__call, JLog without loggers. */
final class LegacyEndpointsLayerTest extends TestCase
{
    public function test_an_unknown_query_method_answers_null_as_joomla_25_did(): void
    {
        // bonus_feedback.php's get_agg(): ->setLimit(1) does not exist in Joomla 2.5, its
        // __call() answered null, the query became null and the live endpoint reports 0 votes.
        $q = (new Query(new Database('fec')))->select('yes_count, no_count')->from('`#__bonus_feedback_agg`')->where('bid=5');
        $this->assertNull(@$q->setLimit(1));
        $this->assertSame('', (string) null);
        $this->assertStringEndsWith('WHERE bid=5', (string) $q);
    }

    public function test_jlog_without_a_logger_writes_nothing(): void
    {
        // No addLogger() for "newsletter", as on the live CMS: the facade is never reached
        // (in a bare unit test it has no application and would throw).
        Log::add('Resend confirm failed for someone@example.com', Log::ERROR, 'newsletter');
        $this->assertTrue(true);
    }
}
