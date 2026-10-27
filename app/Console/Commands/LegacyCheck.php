<?php

namespace App\Console\Commands;

use App\Fec\Legacy\Joomla\Runtime;
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

        $failed = 0;
        foreach ($files as $file) {
            $rel = substr($file, strlen(Runtime::sitePath()) + 1);
            try {
                require_once $file;
                $this->line('  <info>ok</info>   '.$rel);
            } catch (Throwable $e) {
                $failed++;
                $this->line('  <error>FAIL</error> '.$rel.' - '.$e->getMessage());
            }
        }

        $classes = array_values(array_filter(get_declared_classes(), fn ($c) => preg_match('/^'.ucfirst(substr($component, 4)).'(Controller|View|Model)/', $c)));
        $this->line(sprintf('%d files, %d legacy classes declared%s.', count($files), count($classes), $failed ? ", $failed FAILED" : ''));

        // Joomla globals the code reaches for at run time (not at include time): every
        // J* constant and J* class named in the component must exist in the layer.
        $missing = $this->missingGlobals(array_merge($files, glob($base.'/views/*/tmpl/*.php') ?: []));
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
            preg_match_all('/(?<![:\\w$])(J[A-Z]+_[A-Z_]+)\b/', $code, $m);
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
