<?php

namespace App\Console\Commands\Legacy;

/**
 * php artisan fec:licence-watch:snapshot [--edition=YYYY-MM] [--force]
 *
 * The quarterly freeze of the Licence Watch register into a draft edition
 * (com_authors task licencewatch.snapshot), as the cron called it on the
 * 1st of January, April, July and October at 06:20 (crontab clock): default edition =
 * the current month; --force refreezes a draft (never a published edition).
 * Writes jos_fec_licence_watch_editions and the sh404SEF record of the
 * edition path, and mails FEC_PI_NOTIFY_TO when a draft was created.
 */
class LicenceWatchSnapshot extends SnapshotCommand
{
    protected $signature = 'fec:licence-watch:snapshot
                            {--edition= : Edition YYYY-MM (default: this month)}
                            {--force : Refreeze an existing draft}';

    protected $description = 'Freeze the quarterly Licence Watch draft edition (legacy task licencewatch.snapshot)';

    public function handle(): int
    {
        return $this->runLegacyTask('com_authors', 'licencewatch.snapshot', [
            'edition' => (string) $this->option('edition'),
            'force' => $this->option('force') ? '1' : '',
        ]);
    }
}
