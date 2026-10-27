<?php

namespace App\Fec\Legacy;

/**
 * The array sorts of PHP 5.6, for the ported Joomla-era code.
 *
 * PHP 5 sorted with Zend/zend_qsort.c: a plain quicksort (pivot = the middle
 * element, swapped to the front, two-way partition) that is not stable, so
 * elements the comparator cannot tell apart come out in an order that depends
 * on their count and positions (two equal elements are swapped, three are
 * reversed...). PHP 8 sorts stably. The legacy code sorts candidates by a
 * score and slices a top N, or walks an arsort()ed map of counts: with ties,
 * the live site (PHP 5.6) and v3 would show different rows. These functions
 * are zend_qsort ported step by step (validated against the C source of
 * php-5.6.40 on random inputs); the port scripts rewrite usort/uasort/asort/
 * arsort calls in the ported code to them (scripts/legacy-rewrite.inc.php).
 *
 * The comparator's result is reduced to its sign after a cast to int, as
 * php_array_user_compare did (a float 0.5 counts as 0). asort/arsort compare
 * with PHP's own operators (SORT_REGULAR, SORT_NUMERIC, SORT_STRING,
 * SORT_NATURAL, SORT_FLAG_CASE): the comparison semantics are the current
 * engine's, only the algorithm is PHP 5's.
 */
final class Php5Sort
{
    /** usort(): values re-indexed. */
    public static function usort(array &$array, callable $compare): bool
    {
        $pairs = self::pairs($array);
        self::qsort($pairs, self::userCompare($compare));
        $array = array_column($pairs, 1);

        return true;
    }

    /** uasort(): keys kept. */
    public static function uasort(array &$array, callable $compare): bool
    {
        $pairs = self::pairs($array);
        self::qsort($pairs, self::userCompare($compare));
        $array = self::fromPairs($pairs);

        return true;
    }

    /** asort(): keys kept, ascending by value. */
    public static function asort(array &$array, int $flags = SORT_REGULAR): bool
    {
        $pairs = self::pairs($array);
        self::qsort($pairs, self::dataCompare($flags, false));
        $array = self::fromPairs($pairs);

        return true;
    }

    /** arsort(): keys kept, descending by value. */
    public static function arsort(array &$array, int $flags = SORT_REGULAR): bool
    {
        $pairs = self::pairs($array);
        self::qsort($pairs, self::dataCompare($flags, true));
        $array = self::fromPairs($pairs);

        return true;
    }

    /** @return list<array{0: int|string, 1: mixed}> */
    private static function pairs(array $array): array
    {
        $pairs = [];
        foreach ($array as $k => $v) {
            $pairs[] = [$k, $v];
        }

        return $pairs;
    }

    /** @param  list<array{0: int|string, 1: mixed}>  $pairs */
    private static function fromPairs(array $pairs): array
    {
        $out = [];
        foreach ($pairs as [$k, $v]) {
            $out[$k] = $v;
        }

        return $out;
    }

    /** php_array_user_compare: the callback's result as a long, then its sign. */
    private static function userCompare(callable $compare): \Closure
    {
        return static function (array $a, array $b) use ($compare): int {
            $r = $compare($a[1], $b[1]);
            if (! is_int($r)) {
                $r = is_scalar($r) || $r === null ? (int) $r : 0;
            }

            return $r < 0 ? -1 : ($r > 0 ? 1 : 0);
        };
    }

    /** php_array_data_compare (and its reverse) for the sort flags our code uses. */
    private static function dataCompare(int $flags, bool $reverse): \Closure
    {
        $type = $flags & ~SORT_FLAG_CASE;
        $nocase = ($flags & SORT_FLAG_CASE) !== 0;
        $cmp = match ($type) {
            SORT_NUMERIC => static fn ($a, $b): int => (float) $a <=> (float) $b,
            SORT_STRING => $nocase
                ? static fn ($a, $b): int => strcasecmp((string) $a, (string) $b)
                : static fn ($a, $b): int => strcmp((string) $a, (string) $b),
            SORT_NATURAL => $nocase
                ? static fn ($a, $b): int => strnatcasecmp((string) $a, (string) $b)
                : static fn ($a, $b): int => strnatcmp((string) $a, (string) $b),
            default => static fn ($a, $b): int => $a <=> $b,
        };

        return static function (array $a, array $b) use ($cmp, $reverse): int {
            $r = $cmp($a[1], $b[1]);
            $r = $r < 0 ? -1 : ($r > 0 ? 1 : 0);

            return $reverse ? -$r : $r;
        };
    }

    /**
     * zend_qsort_r() of php-5.6.40, over a list instead of a byte buffer: the
     * pivot offset "(end - begin) >> 1" rounded down to a whole element is the
     * middle index; every swap, comparison (and its argument order) and the
     * explicit partition stack are kept as they were.
     *
     * @param  list<mixed>  $v
     * @param  callable(mixed, mixed): int  $cmp
     */
    public static function qsort(array &$v, callable $cmp): void
    {
        $n = count($v);
        if ($n < 2) {
            return;
        }

        $beginStack = [0];
        $endStack = [$n - 1];
        for ($loop = 0; $loop >= 0; $loop--) {
            $begin = $beginStack[$loop];
            $end = $endStack[$loop];

            while ($begin < $end) {
                $pivot = $begin + intdiv($end - $begin, 2);
                [$v[$begin], $v[$pivot]] = [$v[$pivot], $v[$begin]];

                $seg1 = $begin + 1;
                $seg2 = $end;

                while (true) {
                    for (; $seg1 < $seg2 && $cmp($v[$begin], $v[$seg1]) > 0; $seg1++) {
                    }
                    for (; $seg2 >= $seg1 && $cmp($v[$seg2], $v[$begin]) > 0; $seg2--) {
                    }
                    if ($seg1 >= $seg2) {
                        break;
                    }
                    [$v[$seg1], $v[$seg2]] = [$v[$seg2], $v[$seg1]];
                    $seg1++;
                    $seg2--;
                }

                [$v[$begin], $v[$seg2]] = [$v[$seg2], $v[$begin]];

                if (($seg2 - $begin) <= ($end - $seg2)) {
                    if ($seg2 + 1 < $end) {
                        $beginStack[$loop] = $seg2 + 1;
                        $endStack[$loop++] = $end;
                    }
                    $end = $seg2 - 1;
                } else {
                    if ($seg2 - 1 > $begin) {
                        $beginStack[$loop] = $begin;
                        $endStack[$loop++] = $seg2 - 1;
                    }
                    $begin = $seg2 + 1;
                }
            }
        }
    }
}
