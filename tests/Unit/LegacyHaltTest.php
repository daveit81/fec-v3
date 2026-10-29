<?php

namespace Tests\Unit;

use App\Fec\Legacy\Joomla\ExitException;
use App\Fec\Legacy\Joomla\RedirectException;
use Exception;
use PHPUnit\Framework\TestCase;

/** exit and redirect() ended the request on the live site: no legacy catch (Exception) stops them. */
final class LegacyHaltTest extends TestCase
{
    public function test_a_redirect_passes_a_legacy_catch(): void
    {
        $this->expectException(RedirectException::class);
        try {
            throw new RedirectException('https://www.freeextrachips.com/blog', 301);
        } catch (Exception $e) {
            $this->fail('the legacy catch saw the redirect');
        }
    }

    public function test_an_exit_passes_a_legacy_catch(): void
    {
        $this->expectException(ExitException::class);
        try {
            throw new ExitException('done');
        } catch (Exception $e) {
            $this->fail('the legacy catch saw the exit');
        }
    }
}
