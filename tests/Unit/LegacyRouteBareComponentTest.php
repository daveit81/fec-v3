<?php

namespace Tests\Unit;

use App\Fec\Legacy\Joomla\Route;
use PHPUnit\Framework\TestCase;

/**
 * JRoute of the bare component: the desks' endpoint stays the non-SEF entry,
 * answered before any registry lookup (no database needed here).
 */
final class LegacyRouteBareComponentTest extends TestCase
{
    public function test_the_bare_component_is_the_non_sef_entry(): void
    {
        $this->assertSame('/index.php?option=com_authors', Route::_('index.php?option=com_authors', false));
        $this->assertSame('/index.php?option=com_atlas', Route::_('index.php?option=com_atlas', false));
        $this->assertSame('/index.php?option=com_atlas', Route::_('index.php?option=com_atlas&lang=en', false));
    }
}
