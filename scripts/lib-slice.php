#!/usr/bin/env php
<?php
/**
 * lib-slice.php - port the legacy helper libraries by slices.
 *
 *   php scripts/lib-slice.php            regenerate every slice from app/Fec/Legacy/lib-slices.json
 *   php scripts/lib-slice.php --report   only print the closure (functions, lines, Joomla calls)
 *
 * Reads the functions named as seeds in lib-slices.json, follows every call
 * they make into the legacy helper files (legacy/components/com_bonuses/
 * helpers/*.php and com_authors/helpers/authors.php), and writes the
 * transitive closure, function by function and VERBATIM, into
 * app/Fec/Legacy/site/components/com_bonuses/helpers/<same file name>.php,
 * plus the file-scope constants of each source file. The ported views
 * require_once those paths exactly as they did under Joomla; what they get
 * is the part of the lib they use, nothing else.
 *
 * Static analysis only: dynamic calls (call_user_func, $fn()) are not
 * followed. A function reported missing at run time is added to the seeds.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$manifestFile = $root.'/app/Fec/Legacy/lib-slices.json';
$legacyHelpers = $root.'/legacy/components/com_bonuses/helpers';
$outDir = $root.'/app/Fec/Legacy/site/components/com_bonuses/helpers';
$reportOnly = in_array('--report', $argv, true);

$manifest = json_decode((string) file_get_contents($manifestFile), true);
if (! is_array($manifest) || ! isset($manifest['slices'])) {
    fwrite(STDERR, "Bad manifest: $manifestFile\n");
    exit(1);
}

$sources = glob($legacyHelpers.'/*.php') ?: [];
$sources[] = $root.'/legacy/components/com_authors/helpers/authors.php';

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
    foreach ($fns as $f) {
        $d = $defs[$f];
        $out[] = '// --- '.$f.'  (source lines '.$d['start'].'-'.$d['end'].'; slices: '.implode(', ', array_unique($needed[$f])).')';
        $from = $d['docStart'] ?? $d['start'];
        for ($l = $from; $l <= $d['end']; $l++) {
            $out[] = $src[$l - 1] ?? '';
        }
        $out[] = '';
    }
    file_put_contents($outDir.'/'.$base, implode("\n", $out)."\n");
    echo "written: ".substr($outDir, strlen($root) + 1)."/$base\n";
}
