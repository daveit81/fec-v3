<?php

namespace Tests\Unit;

use App\Fec\Legacy\Php5Sort;
use PHPUnit\Framework\TestCase;

/**
 * The expectations are what php-5.6.40 produces (Zend/zend_qsort.c compiled
 * and run on the same inputs): elements the comparator cannot tell apart are
 * not kept in order, which is what the ported code's top-N slices rely on.
 */
class Php5SortTest extends TestCase
{
    public function test_equal_elements_come_out_reversed(): void
    {
        foreach ([2 => 'BA', 3 => 'CBA', 4 => 'DCBA', 5 => 'EDCBA'] as $n => $expected) {
            $rows = [];
            for ($i = 0; $i < $n; $i++) {
                $rows[] = ['score' => 1.0, 'id' => chr(65 + $i)];
            }
            Php5Sort::usort($rows, fn ($a, $b) => $a['score'] <=> $b['score']);
            $this->assertSame($expected, implode('', array_column($rows, 'id')), "$n equal elements");
        }
    }

    public function test_usort_sorts_and_renumbers(): void
    {
        $rows = [5 => 3, 7 => 1, 9 => 2];
        Php5Sort::usort($rows, fn ($a, $b) => $a <=> $b);
        $this->assertSame([1, 2, 3], $rows);
    }

    public function test_ties_follow_zend_qsort_not_a_stable_sort(): void
    {
        // keys 2,0,2,1,0,2,1,0 (ids 0..7): the order php 5.6 gives
        $keys = [2, 0, 2, 1, 0, 2, 1, 0];
        $rows = [];
        foreach ($keys as $id => $k) {
            $rows[] = ['k' => $k, 'id' => $id];
        }
        Php5Sort::usort($rows, fn ($a, $b) => $a['k'] <=> $b['k']);
        $this->assertSame([0, 0, 0, 1, 1, 2, 2, 2], array_column($rows, 'k'));
        $this->assertSame([7, 1, 4, 6, 3, 2, 0, 5], array_column($rows, 'id'), 'php 5.6 order; a stable sort gives 1,4,7,3,6,0,2,5');
    }

    public function test_comparator_result_is_reduced_to_its_sign_after_an_int_cast(): void
    {
        $rows = [['s' => 0.9], ['s' => 0.5], ['s' => 0.7]];
        // a float difference below 1 is 0 to PHP 5.6: every pair ties, three tied elements reverse
        Php5Sort::usort($rows, fn ($a, $b) => $b['s'] - $a['s']);
        $this->assertSame([0.7, 0.5, 0.9], array_column($rows, 's'));
    }

    public function test_arsort_keeps_keys_and_orders_ties_as_php_5_6(): void
    {
        $clicks = [101 => 5, 102 => 9, 103 => 5, 104 => 7, 105 => 5];
        Php5Sort::arsort($clicks);
        $this->assertSame([9, 7, 5, 5, 5], array_values($clicks));
        $this->assertSame([102, 104, 105, 103, 101], array_keys($clicks));
    }

    public function test_asort_flags(): void
    {
        $s = ['b' => 'img12', 'a' => 'IMG10', 'c' => 'img2'];
        Php5Sort::asort($s, SORT_NATURAL | SORT_FLAG_CASE);
        $this->assertSame(['img2', 'IMG10', 'img12'], array_values($s));

        $t = ['x' => '10', 'y' => '9', 'z' => '100'];
        Php5Sort::asort($t, SORT_STRING);
        $this->assertSame(['10', '100', '9'], array_values($t));
        Php5Sort::asort($t);
        $this->assertSame(['9', '10', '100'], array_values($t));
    }

    public function test_uasort_keeps_keys(): void
    {
        $m = ['a' => 2, 'b' => 1, 'c' => 2];
        Php5Sort::uasort($m, fn ($x, $y) => $x <=> $y);
        $this->assertSame(['b' => 1, 'c' => 2, 'a' => 2], $m);
    }

    public function test_small_arrays(): void
    {
        $empty = [];
        Php5Sort::usort($empty, fn ($a, $b) => 0);
        $this->assertSame([], $empty);
        $one = ['k' => 'v'];
        Php5Sort::usort($one, fn ($a, $b) => 0);
        $this->assertSame(['v'], $one);
        Php5Sort::asort($one);
        $this->assertSame([0 => 'v'], $one);
    }
}
