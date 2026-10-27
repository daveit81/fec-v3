#!/usr/bin/env php
<?php
/**
 * lib-slice.php - port the legacy helper libraries by slices.
 *
 *   php scripts/lib-slice.php            regenerate every slice from app/Fec/Legacy/lib-slices.json
 *   php scripts/lib-slice.php --report   only print the closure (functions, lines, Joomla calls)
 *   php scripts/lib-slice.php --report --manifest=/tmp/trial.json   measure a trial manifest
 *
 * Reads the functions named as seeds in lib-slices.json, follows every call
 * they make into the lib files (legacy/components/com_bonuses/helpers/
 * fec_common_lib.php, fec_fxrate.php, fec_blog.php), and writes the
 * transitive closure, function by function and VERBATIM, into
 * app/Fec/Legacy/site/components/com_bonuses/helpers/<same file name>.php,
 * plus the file-scope constants of each source file. The ported views
 * require_once those paths exactly as they did under Joomla; what they get
 * is the part of the lib they use, nothing else.
 *
 * Static analysis: direct calls and function names passed as string
 * literals ('fec_x' to preg_replace_callback, array_map, call_user_func)
 * are followed; a variable function ($fn()) is not. A function reported
 * missing at run time is added to the seeds.
 *
 * Verbatim but for the port's mechanical rewrites (scripts/legacy-rewrite.inc.php):
 * the sorts of PHP 5.6 (usort -> fec_php5_usort...), reported per slice as
 * source line numbers.
 */

declare(strict_types=1);

require __DIR__.'/legacy-rewrite.inc.php';

$root = dirname(__DIR__);
$manifestFile = $root.'/app/Fec/Legacy/lib-slices.json';
// --manifest=<file>: measure a trial manifest (with --report) without touching the real one.
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--manifest=')) {
        $manifestFile = substr($arg, strlen('--manifest='));
    }
}
$legacyHelpers = $root.'/legacy/components/com_bonuses/helpers';
$outDir = $root.'/app/Fec/Legacy/site/components/com_bonuses/helpers';
$reportOnly = in_array('--report', $argv, true);

$manifest = json_decode((string) file_get_contents($manifestFile), true);
if (! is_array($manifest) || ! isset($manifest['slices'])) {
    fwrite(STDERR, "Bad manifest: $manifestFile\n");
    exit(1);
}

// The lib proper: plain functions shared by every section. The other helper files of
// com_bonuses (bonus.php, detailsconstructor.php, fec_fxrate_render.php, the arrays...) are
// component code, copied verbatim with the component, never sliced.
$sources = array_map(fn ($f) => $legacyHelpers.'/'.$f, ['fec_common_lib.php', 'fec_fxrate.php', 'fec_blog.php']);

/** @var array<string, array{file: string, start: int, end: int, docStart: ?int, calls: array<string, true>, japi: array<string, int>}> $defs */
$defs = [];
/** @var array<string, list<array{line: int, text: string}>> $constants file => define lines */
$constants = [];
/** @var array<string, list<string>> $lines file => lines */
$lines = [];

foreach ($sources as $file) {
    $code = (string) file_get_contents($file);
    $lines[$file] = explode("\n", $code);
    $tokens = token_get_all($code);
    $n = count($tokens);
    $depth = 0;
    $fnDepth = null;

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        $s = is_array($t) ? $t[1] : $t;

        // file-scope define() lines
        if ($fnDepth === null && is_array($t) && $t[0] === T_STRING && $t[1] === 'define') {
            $constants[$file][$t[2]] = ['line' => $t[2], 'text' => $lines[$file][$t[2] - 1]];
        }

        if (is_array($t) && $t[0] === T_FUNCTION) {
            $j = $i + 1;
            while ($j < $n && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $j++;
            }
            if ($j < $n && $tokens[$j] === '&') {
                $j++;
                while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
            }
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $name = $tokens[$j][1];
                $start = $t[2];
                // doc comment right before the function (whitespace allowed)
                $docStart = null;
                $p = $i - 1;
                while ($p >= 0 && is_array($tokens[$p]) && $tokens[$p][0] === T_WHITESPACE) {
                    $p--;
                }
                if ($p >= 0 && is_array($tokens[$p]) && in_array($tokens[$p][0], [T_DOC_COMMENT, T_COMMENT], true)) {
                    $docStart = $tokens[$p][2];
                }
                // body range
                $k = $j;
                $d = 0;
                $started = false;
                $end = $start;
                for (; $k < $n; $k++) {
                    $tk = $tokens[$k];
                    $ks = is_array($tk) ? $tk[1] : $tk;
                    if ($ks === '{' || (is_array($tk) && in_array($tk[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                        $d++;
                        $started = true;
                    } elseif ($ks === '}') {
                        $d--;
                        if ($started && $d === 0) {
                            $end = lineOf($tokens, $k);
                            break;
                        }
                    }
                }
                $calls = [];
                $japi = [];
                $body = array_slice($tokens, $j, $k - $j + 1);
                $bn = count($body);
                for ($b = 0; $b < $bn; $b++) {
                    $bt = $body[$b];
                    // a function name passed as a string (preg_replace_callback, array_map,
                    // call_user_func, is_callable...) is a call too
                    if (is_array($bt) && $bt[0] === T_CONSTANT_ENCAPSED_STRING && preg_match('/^[\'"](fec_[a-z0-9_]+)[\'"]$/i', $bt[1], $sm)) {
                        $calls[$sm[1]] = true;

                        continue;
                    }
                    if (! is_array($bt) || $bt[0] !== T_STRING) {
                        continue;
                    }
                    $c = $b + 1;
                    while ($c < $bn && is_array($body[$c]) && $body[$c][0] === T_WHITESPACE) {
                        $c++;
                    }
                    $next = $body[$c] ?? null;
                    $nextS = is_array($next) ? $next[1] : $next;
                    $p = $b - 1;
                    while ($p >= 0 && is_array($body[$p]) && $body[$p][0] === T_WHITESPACE) {
                        $p--;
                    }
                    $prev = $body[$p] ?? null;
                    $prevS = is_array($prev) ? $prev[1] : $prev;
                    if ($nextS === '(' && $prevS !== '->' && $prevS !== '::' && ! (is_array($prev) && $prev[0] === T_FUNCTION)) {
                        $calls[$bt[1]] = true;
                    } elseif ($nextS === '::' && preg_match('/^J[A-Z]/', $bt[1])) {
                        $dd = $c + 1;
                        while ($dd < $bn && is_array($body[$dd]) && $body[$dd][0] === T_WHITESPACE) {
                            $dd++;
                        }
                        $m = $body[$dd] ?? null;
                        $key = $bt[1].'::'.(is_array($m) ? $m[1] : '?');
                        $japi[$key] = ($japi[$key] ?? 0) + 1;
                    } elseif (preg_match('/^J[A-Z]/', $bt[1]) && is_array($prev) && $prev[0] === T_NEW) {
                        $japi['new '.$bt[1]] = ($japi['new '.$bt[1]] ?? 0) + 1;
                    }
                }
                if (! isset($defs[$name])) {
                    $defs[$name] = ['file' => $file, 'start' => $start, 'end' => $end, 'docStart' => $docStart, 'calls' => $calls, 'japi' => $japi];
                }
                if ($fnDepth === null) {
                    $fnDepth = $depth;
                }
                $i = $j;
            }
        }

        if ($s === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++;
        } elseif ($s === '}') {
            $depth--;
            if ($fnDepth !== null && $depth === $fnDepth) {
                $fnDepth = null;
            }
        }
    }
}

// File-scope statements of each source (code outside every function and outside the
// `if (!function_exists('x')) { function x() {...} }` wrappers, define() lines apart):
// the lib publishes the arrays it was included with ($countries_array, $currency,
// $bonustype) into $GLOBALS for its functions. They are copied verbatim, in order.
/** @var array<string, list<array{start: int, end: int}>> file => line ranges */
$fileScope = [];
foreach ($sources as $file) {
    $src = $lines[$file];
    $total = count($src);
    $inFunction = array_fill(1, $total, false);
    foreach ($defs as $d) {
        if ($d['file'] !== $file) {
            continue;
        }
        for ($l = $d['docStart'] ?? $d['start']; $l <= $d['end']; $l++) {
            $inFunction[$l] = true;
        }
        // the function_exists wrapper around it (blank and comment lines may sit in between)
        $p = ($d['docStart'] ?? $d['start']) - 1;
        $inComment = false;
        while ($p >= 1) {
            $line = trim($src[$p - 1]);
            if ($line === '' || str_starts_with($line, '//') || str_starts_with($line, '*') || str_starts_with($line, '/*') || str_ends_with($line, '*/')) {
                if (str_starts_with($line, '/*')) {
                    $inComment = false;
                } elseif (str_ends_with($line, '*/') && ! str_starts_with($line, '/*')) {
                    $inComment = true;
                }
                $p--;

                continue;
            }
            if ($inComment) {
                $p--;

                continue;
            }
            break;
        }
        if ($p >= 1 && preg_match('/^\s*if\s*\(\s*!\s*function_exists\s*\(/', $src[$p - 1])) {
            for ($l = $p; $l < ($d['docStart'] ?? $d['start']); $l++) {
                $inFunction[$l] = true;
            }
            $q = $d['end'] + 1;
            while ($q <= $total && trim($src[$q - 1]) === '') {
                $q++;
            }
            if ($q <= $total && trim($src[$q - 1]) === '}') {
                $inFunction[$q] = true;
            }
        }
    }
    // `if (!defined('X')) { define(...); }` and `if (!function_exists('x')) { function ... }`
    // blocks (the latter may wrap several functions): the defines are emitted as constants and
    // the functions on their own, the wrapper is not a statement of its own
    $toks = token_get_all((string) file_get_contents($file));
    $tn = count($toks);
    for ($i = 0; $i < $tn; $i++) {
        if (! is_array($toks[$i]) || $toks[$i][0] !== T_IF) {
            continue;
        }
        $look = '';
        for ($j = $i + 1; $j < min($tn, $i + 8); $j++) {
            $look .= is_array($toks[$j]) ? $toks[$j][1] : $toks[$j];
        }
        if (! preg_match('/^\s*\(\s*!\s*(defined|function_exists)\s*\(/', $look)) {
            continue;
        }
        $startLine = $toks[$i][2];
        $depth = 0;
        $opened = false;
        for ($j = $i + 1; $j < $tn; $j++) {
            $sj = is_array($toks[$j]) ? $toks[$j][1] : $toks[$j];
            if ($sj === '{') {
                $depth++;
                $opened = true;
            } elseif ($sj === '}') {
                $depth--;
                if ($opened && $depth === 0) {
                    for ($l = $startLine; $l <= lineOf($toks, $j); $l++) {
                        $inFunction[$l] = true;
                    }
                    break;
                }
            } elseif (! $opened && $sj === ';') {
                // single-statement form: if (!defined(...)) define(...);
                for ($l = $startLine; $l <= lineOf($toks, $j); $l++) {
                    $inFunction[$l] = true;
                }
                break;
            }
        }
    }
    // lines that carry code (a token other than whitespace, comment, open tag)
    $hasCode = array_fill(1, $total, false);
    $cur = 1;
    foreach (token_get_all((string) file_get_contents($file)) as $t) {
        if (! is_array($t)) {
            // a one-character token ('{', '}', ';', ...) sits on the current line
            $hasCode[$cur] = true;

            continue;
        }
        $cur = $t[2] + substr_count($t[1], "\n");
        if (in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML], true)) {
            continue;
        }
        $hasCode[$t[2]] = true;
        for ($l = $t[2]; $l <= $cur; $l++) {
            $hasCode[$l] = true;
        }
    }
    // tokens are per line start; a multi-line statement's continuation lines also carry code
    $range = null;
    for ($l = 1; $l <= $total; $l++) {
        $isScope = $hasCode[$l] && ! $inFunction[$l] && ! isset($constants[$file][$l]);
        if ($isScope) {
            if ($range === null) {
                $range = ['start' => $l, 'end' => $l];
            } else {
                $range['end'] = $l;
            }
        } elseif ($range !== null && (! $hasCode[$l] || $inFunction[$l] || isset($constants[$file][$l]))) {
            // a blank/comment line inside a statement keeps the range open only if the statement continues
            if (! $hasCode[$l] && $l < $total && $hasCode[$l + 1] && ! $inFunction[$l + 1] && ! isset($constants[$file][$l + 1])) {
                continue;
            }
            $fileScope[$file][] = $range;
            $range = null;
        }
    }
    if ($range !== null) {
        $fileScope[$file][] = $range;
    }
}

function lineOf(array $tokens, int $k): int
{
    for ($x = $k; $x >= 0; $x--) {
        if (is_array($tokens[$x])) {
            $line = $tokens[$x][2];
            // count newlines inside the token itself
            return $line + substr_count($tokens[$x][1], "\n");
        }
    }

    return 1;
}

// closure over every slice
$needed = [];   // function => list of slice names
$missing = [];
foreach ($manifest['slices'] as $slice => $seeds) {
    $queue = $seeds;
    $seen = [];
    while ($queue) {
        $f = array_shift($queue);
        if (isset($seen[$f])) {
            continue;
        }
        if (! isset($defs[$f])) {
            $missing[$slice][] = $f;

            continue;
        }
        $seen[$f] = true;
        $needed[$f][] = $slice;
        foreach (array_keys($defs[$f]['calls']) as $callee) {
            if (isset($defs[$callee]) && ! isset($seen[$callee])) {
                $queue[] = $callee;
            }
        }
    }
}

// nested definitions: a function defined inside another needed function's body
foreach ($needed as $f => $slices) {
    foreach ($needed as $g => $_) {
        if ($f !== $g && $defs[$f]['file'] === $defs[$g]['file'] && $defs[$f]['start'] > $defs[$g]['start'] && $defs[$f]['end'] <= $defs[$g]['end']) {
            fwrite(STDERR, "WARNING: $f is defined inside $g; it is copied with $g and skipped standalone\n");
            unset($needed[$f]);
        }
    }
}

// group by source file, ordered by line
$byFile = [];
foreach ($needed as $f => $slices) {
    $byFile[$defs[$f]['file']][] = $f;
}
foreach ($byFile as $file => &$fns) {
    usort($fns, fn ($a, $b) => $defs[$a]['start'] <=> $defs[$b]['start']);
}
unset($fns);

$totalLines = 0;
$japiAll = [];
foreach ($byFile as $file => $fns) {
    $fileLines = 0;
    foreach ($fns as $f) {
        $fileLines += $defs[$f]['end'] - $defs[$f]['start'] + 1;
        foreach ($defs[$f]['japi'] as $k => $v) {
            $japiAll[$k] = ($japiAll[$k] ?? 0) + $v;
        }
    }
    $totalLines += $fileLines;
    printf("%-22s %3d functions %5d lines\n", basename($file), count($fns), $fileLines);
}
foreach ($fileScope as $file => $ranges) {
    $n = array_sum(array_map(fn ($r) => $r['end'] - $r['start'] + 1, $ranges));
    if ($n > 0) {
        printf("%-22s file-scope statements: %d lines (%s)\n", basename($file), $n, implode(', ', array_map(fn ($r) => $r['start'].($r['end'] > $r['start'] ? '-'.$r['end'] : ''), $ranges)));
    }
}
printf("Total: %d functions, %d lines. Joomla calls inside: %s\n", count($needed), $totalLines,
    $japiAll ? implode(', ', array_map(fn ($k, $v) => "$k x$v", array_keys($japiAll), $japiAll)) : 'none');
foreach ($missing as $slice => $fns) {
    printf("Slice %s: seeds not found: %s\n", $slice, implode(', ', $fns));
}
if ($reportOnly) {
    exit(0);
}

@mkdir($outDir, 0755, true);
$snapshot = @parse_ini_file($root.'/legacy/SNAPSHOT.txt') ?: [];
foreach ($byFile as $file => $fns) {
    $src = $lines[$file];
    $base = basename($file);
    $out = [];
    $out[] = '<?php';
    $out[] = '/*';
    $out[] = ' * GENERATED by scripts/lib-slice.php from legacy/'.substr($file, strlen($root.'/legacy/')).' - do not edit.';
    $out[] = ' * Legacy snapshot: '.($snapshot['snapshot_at'] ?? '?').' (lib md5 '.($snapshot['fec_common_lib_md5'] ?? '?').').';
    $out[] = ' * The functions below are copied verbatim, in source order; each header names';
    $out[] = ' * the original lines and the slices (ported sections) that need it.';
    $out[] = ' * Only the port rewrites apply (scripts/legacy-rewrite.inc.php): usort/uasort/asort/arsort';
    $out[] = ' * run as fec_php5_* (the sorts of PHP 5.6), header() as fec_header().';
    $out[] = ' */';
    $out[] = '';
    if (! empty($constants[$file])) {
        $out[] = '// File-scope constants of the source file.';
        ksort($constants[$file]);
        foreach ($constants[$file] as $c) {
            $out[] = trim($c['text']);
        }
        $out[] = '';
    }
    if (! empty($fileScope[$file])) {
        $out[] = '// File-scope statements of the source file (verbatim, in order): what the lib did when included.';
        foreach ($fileScope[$file] as $r) {
            $out[] = '// --- source lines '.$r['start'].($r['end'] > $r['start'] ? '-'.$r['end'] : '');
            for ($l = $r['start']; $l <= $r['end']; $l++) {
                $out[] = $src[$l - 1] ?? '';
            }
        }
        $out[] = '';
    }
    foreach ($fns as $f) {
        $d = $defs[$f];
        $out[] = '// --- '.$f.'  (source lines '.$d['start'].'-'.$d['end'].'; slices: '.implode(', ', array_unique($needed[$f])).')';
        $from = $d['docStart'] ?? $d['start'];
        for ($l = $from; $l <= $d['end']; $l++) {
            $out[] = $src[$l - 1] ?? '';
        }
        $out[] = '';
    }
    $changes = [];
    $code = fec_port_rewrite(implode("\n", $out)."\n", $changes);
    file_put_contents($outDir.'/'.$base, $code);
    echo "written: ".substr($outDir, strlen($root) + 1)."/$base\n";
    foreach ($changes as $c) {
        // name the function the rewritten line belongs to (nearest "// --- name" header above it)
        $fn = '(file scope)';
        for ($l = $c['line'] - 1; $l >= 0; $l--) {
            if (preg_match('/^\/\/ --- (\w+)  \(source lines/', $out[$l] ?? '', $m)) {
                $fn = $m[1].'()';
                break;
            }
        }
        printf("  rewrite %s: %s( -> %s( in %s\n", $c['line'], $c['from'], $c['to'], $fn);
    }
}
