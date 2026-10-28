<?php

namespace Tests\Unit;

use App\Fec\Legacy\Joomla\Registry;
use PHPUnit\Framework\TestCase;

/**
 * JRegistry::loadString() as Joomla 2.5 read the params and metadata columns:
 * JSON, or the INI reader for anything that is not wrapped in braces.
 */
final class RegistryTest extends TestCase
{
    public function test_json_is_read_as_json(): void
    {
        $r = new Registry('{"robots":"","author":"Ann","rights":"","xreference":""}');
        $this->assertSame('Ann', $r->get('author'));
        $this->assertNull($r->get('robots')); // an empty value is the default, as in JRegistry::get()
    }

    public function test_old_ini_metadata_is_read_as_ini(): void
    {
        // jos_content id 88 (River Nile Casino), a Joomla 1.5 leftover
        $r = new Registry("html_title=\nmeta_description=\nmeta_keywords=\nrobots=index, follow\ngoogbot=\ncopyright=");
        $this->assertSame('index, follow', $r->get('robots'));
        $this->assertNull($r->get('html_title'));
    }

    public function test_ini_values_are_typed_as_joomla_typed_them(): void
    {
        $a = Registry::iniToArray("; comment\n[section]\nshow_title=1\nratio=1.5\non=true\noff=false\nquoted=\"a\\tb\"\nbad key=x\n=nokey");
        $this->assertSame(['show_title' => 1, 'ratio' => 1.5, 'on' => true, 'off' => false, 'quoted' => "a\tb"], $a);
    }
}
