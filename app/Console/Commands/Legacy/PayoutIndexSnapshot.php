<?php

namespace App\Console\Commands\Legacy;

/**
 * php artisan fec:payout-index:snapshot [--edition=YYYY-MM] [--force] [--dry]
 *
 * The monthly freeze of the FEC Payout Index (com_authors task
 * payoutindex.snapshot), as the cron called it on the 1st at 06:10 (crontab clock, America/New_York):
 * default edition = last month; --force rebuilds an existing edition;
 * --dry computes and renders a preview card without inserting anything.
 * Writes jos_fec_payout_index_editions, the card PNG under
 * public_html/images/payout-index, the sh404SEF record of the edition
 * path, and mails FEC_PI_NOTIFY_TO through the lib.
 */
class PayoutIndexSnapshot extends SnapshotCommand
{
    protected $signature = 'fec:payout-index:snapshot
                            {--edition= : Edition YYYY-MM (default: last month)}
                            {--force : Rebuild an edition that exists}
                            {--dry : Compute and render a preview card, insert nothing}';

    protected $description = 'Freeze the monthly Payout Index edition (legacy task payoutindex.snapshot)';

    public function handle(): int
    {
        return $this->runLegacyTask('com_authors', 'payoutindex.snapshot', [
            'edition' => (string) $this->option('edition'),
            'force' => $this->option('force') ? '1' : '',
            'dry' => $this->option('dry') ? '1' : '',
        ]);
    }
}
