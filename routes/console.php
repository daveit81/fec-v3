<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled jobs
|--------------------------------------------------------------------------
|
| The crontab of freeextr runs `artisan schedule:run` every minute with
| alt-php 8.4; everything timed lives here. Times are Europe/Rome.
|
| Desk Lint: every night at 05:40, after the parsers of the night and before
| the desk opens; news only, full recap on the day set in config/fec.php.
|
*/

Schedule::command('fec:desk-lint --notify')
    ->dailyAt('05:40')
    ->timezone('Europe/Rome')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

/*
| The two snapshot tasks of com_authors, as the crontab of freeextr called
| them on Joomla (lines "payoutindex.snapshot" and "licencewatch.snapshot",
| server clock = UTC): the Payout Index on the 1st at 06:10, the Licence
| Watch draft on the 1st of January, April, July and October at 06:20.
| Enabled by FEC_LEGACY_SCHEDULE_SNAPSHOTS once those crontab lines are gone.
*/
if (config('fec.legacy.schedule_snapshots')) {
    Schedule::command('fec:payout-index:snapshot')
        ->monthlyOn(1, '06:10')
        ->timezone('UTC')
        ->withoutOverlapping()
        ->onOneServer()
        ->appendOutputTo(storage_path('logs/payout-index-snapshot.log'));

    Schedule::command('fec:licence-watch:snapshot')
        ->cron('20 6 1 1,4,7,10 *')
        ->timezone('UTC')
        ->withoutOverlapping()
        ->onOneServer()
        ->appendOutputTo(storage_path('logs/licence-watch-snapshot.log'));
}
