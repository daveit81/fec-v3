<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * scripts/legacy-rewrite.inc.php: the mechanical rewrites of the port
 * (usort/asort -> fec_php5_*, header -> fec_header, exit/die -> jexit).
 */
final class LegacyRewriteTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/scripts/legacy-rewrite.inc.php';
    }

    /** @return array{0: string, 1: list<string>} rewritten code and the "from" names changed */
    private function rewrite(string $code): array
    {
        $changes = [];
        $out = fec_port_rewrite($code, $changes);

        return [$out, array_map(fn (array $c) => $c['from'], $changes)];
    }

    public function test_sort_and_header_calls_are_rewritten(): void
    {
        [$out, $changed] = $this->rewrite('<?php usort($a, "cmp"); arsort($m); header("X: 1"); $o->header(); Foo::usort($x); function header() {}');

        $this->assertSame('<?php fec_php5_usort($a, "cmp"); fec_php5_arsort($m); fec_header("X: 1"); $o->header(); Foo::usort($x); function header() {}', $out);
        $this->assertSame(['usort', 'arsort', 'header'], $changed);
    }

    public function test_exit_statements_become_jexit(): void
    {
        $code = <<<'PHP'
<?php
if (!$ok) {
    http_response_code(403);
    exit;
}
if ($x) exit('bye');
function out($arr, $code = 200) { echo json_encode($arr); exit; }
?>
<p>html</p>
<?php exit; ?>
<?php else: die(2); endif;
PHP;
        $expected = <<<'PHP'
<?php
if (!$ok) {
    http_response_code(403);
    jexit();
}
if ($x) jexit('bye');
function out($arr, $code = 200) { echo json_encode($arr); jexit(); }
?>
<p>html</p>
<?php jexit(); ?>
<?php else: jexit(2); endif;
PHP;
        [$out, $changed] = $this->rewrite($code);

        $this->assertSame($expected, $out);
        $this->assertSame(['exit', 'exit', 'exit', 'exit', 'die'], $changed);
    }

    public function test_the_jexec_guards_are_left_alone(): void
    {
        $code = "<?php\ndefined('_JEXEC') or die('Restricted access');\ndefined('_JEXEC') or die;\nis_file(\$f) || exit(1);\n";
        [$out, $changed] = $this->rewrite($code);

        $this->assertSame($code, $out);
        $this->assertSame([], $changed);
    }

    public function test_rewrites_are_idempotent(): void
    {
        $code = "<?php\nfec_php5_usort(\$a, 'c'); fec_header('X: 1'); jexit(); jexit('m');\n";
        [$out, $changed] = $this->rewrite($code);

        $this->assertSame($code, $out);
        $this->assertSame([], $changed);
    }
}
