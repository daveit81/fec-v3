<?php

namespace Tests\Unit;

use App\Fec\Url\Router;
use PHPUnit\Framework\TestCase;

/** What the router looks up of a requested path. */
final class RouterPathTest extends TestCase
{
    public function test_an_encoded_hash_ends_the_path_as_for_sh404sef(): void
    {
        $this->assertSame('online-casinos/tipbet-casino', Router::withoutEncodedFragment('online-casinos/tipbet-casino%23cd-search'));
        $this->assertSame('bonuses/free-spins', Router::withoutEncodedFragment('bonuses/free-spins%23cd-primary-nav'));
        $this->assertSame('online-casinos', Router::withoutEncodedFragment('online-casinos%23Cd'));
        $this->assertSame('bonuses/x/', Router::withoutEncodedFragment('bonuses/x/'));
        $this->assertSame('', Router::withoutEncodedFragment('%23top'));
    }
}
