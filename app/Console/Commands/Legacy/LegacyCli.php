<?php

namespace App\Console\Commands\Legacy;

use App\Fec\Legacy\ComponentRunner;
use Illuminate\Console\Command;

/**
 * php artisan fec:legacy:cli <name> [-- args...]
 *
 * Runs one of the crontab's legacy scripts under the compatibility layer
 * (fec.legacy.cli_scripts), with the arguments the crontab gave it, e.g.
 *   php artisan fec:legacy:cli digest -- --dry-run
 *   php artisan fec:legacy:cli digest -- --to=webmaster@freeextrachips.com --only-country=it
 *   php artisan fec:legacy:cli llmstxt
 *   php artisan fec:legacy:cli cofm -- --mode=auto
 * The script's exit code is the command's (the digest: 0 ok, 1 locked, 2 lock file,
 * 4 bootstrap, 5 nothing sent).
 */
class LegacyCli extends Command
{
    protected $signature = 'fec:legacy:cli
                            {name : digest, llmstxt, cofm (fec.legacy.cli_scripts)}
                            {args?* : the script\'s own arguments, after "--"}';

    protected $description = 'Run a legacy crontab script (weekly digest, llms.txt) under the compatibility layer';

    public function handle(ComponentRunner $runner): int
    {
        return $runner->runCliScript((string) $this->argument('name'), (array) $this->argument('args'));
    }
}
