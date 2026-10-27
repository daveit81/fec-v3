<?php

/*
 * The mechanical rewrites of the port, shared by scripts/lib-slice.php (the lib
 * slices) and scripts/legacy-port.php (the copied component files). Token
 * based, idempotent; every change is reported as file:line so that
 * PORT-NOTES.md can list it.
 *
 * Rewrites (FEC_PORT_REWRITES):
 *   usort( / uasort( / asort( / arsort(  ->  fec_php5_usort( ...
 *     PHP 5.6 sorted with an unstable quicksort (Zend/zend_qsort.c); PHP 8
 *     sorts stably. The legacy code slices a top N after sorting candidates
 *     by a score and walks arsort()ed maps of counts: with ties, the rows
 *     differ. App\Fec\Legacy\Php5Sort runs the algorithm of PHP 5.6.
 *     sort/rsort/ksort/krsort are left alone: equal values are not
 *     distinguishable after sort(), and keys are unique.
 *   header(  ->  fec_header(
 *     A bare header() reaches PHP's SAPI, which the runner cannot read in
 *     every context (the CLI keeps no header list: fec:parity saw the tc and
 *     ads views answer 200 where PHP had made them a 302 with a Location).
 *     fec_header() records the header on the Joomla application object, the
 *     one place the runner builds the response from; a status line
 *     ("HTTP/1.1 404 Not Found") or a response code sets the status.
 */

const FEC_PORT_REWRITES = [
    'usort' => 'fec_php5_usort',
    'uasort' => 'fec_php5_uasort',
    'asort' => 'fec_php5_asort',
    'arsort' => 'fec_php5_arsort',
    'header' => 'fec_header',
];

/**
 * Apply the rewrites to PHP source code.
 *
 * @param  list<array{line: int, from: string, to: string}>  $changes  appended to
 */
function fec_port_rewrite(string $code, array &$changes): string
{
    $tokens = token_get_all($code);
    $n = count($tokens);
    $out = '';

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (! is_array($t) || $t[0] !== T_STRING) {
            $out .= is_array($t) ? $t[1] : $t;

            continue;
        }

        $name = strtolower($t[1]);
        if (! isset(FEC_PORT_REWRITES[$name]) || ! fec_port_is_function_call($tokens, $i)) {
            $out .= $t[1];

            continue;
        }

        $to = FEC_PORT_REWRITES[$name];
        $changes[] = ['line' => $t[2], 'from' => $t[1], 'to' => $to];
        $out .= $to;
    }

    return $out;
}

/**
 * A T_STRING is a plain function call when "(" follows it and no "->", "::",
 * "function", "\" or "new" precedes it (comments and whitespace skipped).
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 */
function fec_port_is_function_call(array $tokens, int $i): bool
{
    $n = count($tokens);
    $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    for ($j = $i + 1; $j < $n; $j++) {
        if (is_array($tokens[$j]) && in_array($tokens[$j][0], $skip, true)) {
            continue;
        }
        if ($tokens[$j] !== '(') {
            return false;
        }
        break;
    }
    if ($j >= $n) {
        return false;
    }

    for ($j = $i - 1; $j >= 0; $j--) {
        if (is_array($tokens[$j]) && in_array($tokens[$j][0], $skip, true)) {
            continue;
        }
        if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NS_SEPARATOR, T_NEW], true)) {
            return false;
        }

        break;
    }

    return true;
}
