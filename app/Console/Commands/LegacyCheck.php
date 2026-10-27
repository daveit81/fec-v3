<?php

namespace App\Console\Commands;

use App\Fec\Legacy\Joomla\ExitException;
use App\Fec\Legacy\Joomla\RedirectException;
use App\Fec\Legacy\Joomla\Runtime;
use App\Fec\Legacy\LegacyErrors;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan fec:legacy:check com_authors
 *
 * Includes every class file of a ported component (controller, controllers/,
 * models/, views/X/view.*.php, helpers/, router.php) against the
 * compatibility layer. A legacy override that PHP 8 refuses ("must be
 * compatible with ...") or a file that fails to parse shows up here, before
 * a visitor finds it. Run after every port or re-copy.
 */
class LegacyCheck extends Command
{
    protected $signature = 'fec:legacy:check {component : com_authors, com_bonuses, ...}';

    protected $description = 'Declare every class of a ported legacy component against the compatibility layer';

    public function handle(): int
    {
        $component = (string) $this->argument('component');
        if (! preg_match('/^com_[a-z0-9_]+$/', $component)) {
            $this->error('Component name must look like com_x');

            return self::INVALID;
        }

        Runtime::boot($component);
        foreach (['REQUEST_URI' => '/', 'QUERY_STRING' => '', 'REQUEST_METHOD' => 'GET', 'HTTP_HOST' => (string) parse_url((string) config('fec.site_url'), PHP_URL_HOST), 'SERVER_NAME' => (string) parse_url((string) config('fec.site_url'), PHP_URL_HOST), 'HTTPS' => 'on', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'fec:legacy:check'] as $k => $v) {
            $_SERVER[$k] ??= $v;
        }
        $base = Runtime::sitePath().'/components/'.$component;
        if (! is_dir($base)) {
            $this->error('Not ported: '.$base);

            return self::FAILURE;
        }

        $files = array_merge(
            array_filter([$base.'/controller.php', $base.'/router.php'], 'is_file'),
            glob($base.'/helpers/*.php') ?: [],
            glob($base.'/controllers/*.php') ?: [],
            glob($base.'/models/*.php') ?: [],
            glob($base.'/views/*/view.*.php') ?: [],
        );

        // every PHP file of the component must at least parse under this PHP
        $parseFailed = 0;
        foreach (array_merge($files, glob($base.'/views/*/tmpl/*.php') ?: [], glob($base.'/renders/*.php') ?: [], glob($base.'/modules/*.php') ?: [], [$base.'/'.substr($component, 4).'.php']) as $file) {
            if (! is_file($file)) {
                continue;
            }
            $out = [];
            $code = 0;
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $out, $code);
            if ($code !== 0) {
                $parseFailed++;
                $this->line('  <error>PARSE</error> '.substr($file, strlen(Runtime::sitePath()) + 1).' - '.trim(implode(' ', $out)));
            }
        }

        $failed = 0;
        foreach ($files as $file) {
            $rel = substr($file, strlen(Runtime::sitePath()) + 1);
            // A helper that declares classes or functions is included (PHP checks the
            // declarations against the layer); template code (arrays, echoes, code that
            // expects a view's variables) is only parsed, it cannot run outside a request.
            if (! preg_match('/\b(abstract\s+|final\s+)?(class|interface|trait)\s+\w|\bfunction\s+&?\w+\s*\(/', (string) php_strip_whitespace($file))) {
                $this->line('  <comment>lint</comment> '.$rel.' (template code: parsed only)');

                continue;
            }
            // Many legacy view files are a class followed by template code that runs at
            // include time and expects a request: notices are collected as in the runner,
            // a redirect/exit is normal, any other runtime error is reported but is not a
            // declaration problem (those are fatal and show above).
            $level = ob_get_level();
            LegacyErrors::begin('legacy:check '.$rel);
            try {
                ob_start();
                require_once $file;
                $this->line('  <info>ok</info>   '.$rel);
            } catch (RedirectException|ExitException $e) {
                $this->line('  <info>ok</info>   '.$rel.' (template code ended the request)');
            } catch (Throwable $e) {
                $this->line('  <comment>warn</comment> '.$rel.' - template code at include time: '.$e->getMessage());
            } finally {
                LegacyErrors::end();
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
            }
        }

        $classes = array_values(array_filter(get_declared_classes(), fn ($c) => preg_match('/^'.ucfirst(substr($component, 4)).'(Controller|View|Model)/', $c)));
        $failed += $parseFailed;
        $this->line(sprintf('%d files, %d legacy classes declared%s.', count($files), count($classes), $failed ? ", $failed FAILED" : ''));

        // Joomla globals the code reaches for at run time (not at include time): every
        // J* constant and J* class named in the component must exist in the layer.
        $missing = $this->missingGlobals(array_merge($files, glob($base.'/views/*/tmpl/*.php') ?: [], glob($base.'/renders/*.php') ?: [], glob($base.'/modules/*.php') ?: []));
        foreach ($missing as $name => $where) {
            $failed++;
            $this->line('  <error>FAIL</error> '.$name.' is not provided by the compatibility layer ('.$where.')');
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<string>  $files
     * @return array<string, string> name => first file using it
     */
    private function missingGlobals(array $files): array
    {
        $missing = [];
        foreach ($files as $file) {
            $code = (string) php_strip_whitespace($file); // comments out
            $rel = substr($file, strlen(Runtime::sitePath()) + 1);
            // global constants: JREQUEST_ALLOWRAW, JPATH_SITE, ... (not class constants, not PHP's JSON_*)
            preg_match_all('/(?<![:\\w$\x27"])(J[A-Z]+_[A-Z_]+)\b/', $code, $m); // not a quoted language key
            foreach (array_unique($m[1]) as $const) {
                if (str_starts_with($const, 'JSON_') || str_starts_with($const, 'JPEG_')) {
                    continue;
                }
                if (! defined($const) && ! isset($missing[$const])) {
                    $missing[$const] = $rel;
                }
            }
            // classes: JFactory::, new JDate(, JTable::getInstance(...)
            preg_match_all('/(?<![\\w$])(?:new\s+)?(J[A-Z][A-Za-z]+)\s*(?:::|\()/', $code, $m);
            foreach (array_unique($m[1]) as $class) {
                if (! class_exists($class) && ! isset($missing[$class])) {
                    $missing[$class] = $rel;
                }
            }
        }

        return $missing;
    }
}
