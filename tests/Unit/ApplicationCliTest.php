<?php

namespace Tests\Unit;

use App\Fec\Legacy\Joomla\ApplicationCli;
use App\Fec\Legacy\Joomla\ExitException;
use PHPUnit\Framework\TestCase;

/** JApplicationCli for cli/fec_generate_llmstxt.php: getInstance, execute, out, close(code). */
final class ApplicationCliTest extends TestCase
{
    public function test_execute_runs_the_subclass_and_close_carries_the_exit_code(): void
    {
        $app = FecTestCli::getInstance(FecTestCli::class);
        $this->assertInstanceOf(FecTestCli::class, $app);
        ob_start();
        try {
            $app->execute();
            $this->fail('close() must end the run');
        } catch (ExitException $e) {
            $this->assertSame(3, $e->getCode());
        } finally {
            $out = ob_get_clean();
        }
        $this->assertSame("OK: done\n", $out);
    }
}

class FecTestCli extends ApplicationCli
{
    public function doExecute()
    {
        $this->out('OK: done');
        $this->close(3);
    }
}
