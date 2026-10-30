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
| in the crontab's clock: America/New_York, fec.legacy.cron_timezone): the
| Payout Index on the 1st at 06:10, the Licence Watch draft on the 1st of
| January, April, July and October at 06:20.
| Enabled by FEC_LEGACY_SCHEDULE_SNAPSHOTS once those crontab lines are gone.
*/
if (config('fec.legacy.schedule_snapshots')) {
    Schedule::command('fec:payout-index:snapshot')
        ->monthlyOn(1, '06:10')
        ->timezone(config('fec.legacy.cron_timezone', 'America/New_York'))
        ->withoutOverlapping()
        ->onOneServer()
        ->appendOutputTo(storage_path('logs/payout-index-snapshot.log'));

    Schedule::command('fec:licence-watch:snapshot')
        ->cron('20 6 1 1,4,7,10 *')
        ->timezone(config('fec.legacy.cron_timezone', 'America/New_York'))
        ->withoutOverlapping()
        ->onOneServer()
        ->appendOutputTo(storage_path('logs/licence-watch-snapshot.log'));
}

/*
| com_jackpots data.update, which the crontab reached through the web
| (/home/freeextr/bin/jackpots_update.sh): fec:jackpots:update at the same
| crontab expression (FEC_JACKPOTS_UPDATE_CRON) in the crontab's clock.
| Enabled by FEC_LEGACY_SCHEDULE_JACKPOTS once that crontab line is gone.
*/
if (config('fec.legacy.schedule_jackpots') && config('fec.legacy.jackpots_update_cron') !== '') {
    Schedule::command('fec:jackpots:update')
        ->cron(config('fec.legacy.jackpots_update_cron'))
        ->timezone(config('fec.legacy.cron_timezone', 'America/New_York'))
        ->withoutOverlapping(120)
        ->onOneServer()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/jackpots-update.log'));
}

/*
| The crontab's legacy scripts run by fec:legacy:cli, at the crontab's
| expressions in its clock: the weekly digest on Tuesday at 02:00, llms.txt
| every hour. Each enabled by its flag once its crontab line is gone.
*/
if (config('fec.legacy.schedule_digest')) {
    Schedule::command('fec:legacy:cli digest')
        ->cron('0 2 * * 2')
        ->timezone(config('fec.legacy.cron_timezone', 'America/New_York'))
        ->withoutOverlapping(240)
        ->onOneServer()
        ->appendOutputTo(storage_path('logs/newsletter-digest.log'));
}
if (config('fec.legacy.schedule_llmstxt')) {
    Schedule::command('fec:legacy:cli llmstxt')
        ->cron('0 * * * *')
        ->timezone(config('fec.legacy.cron_timezone', 'America/New_York'))
        ->withoutOverlapping(30)
        ->onOneServer()
        ->appendOutputTo(storage_path('logs/llms-txt.log'));
}

/*
| The casino of the month (fec-admin/fec_cofm_cron.php, which the crontab
| reached with wget): mode=auto every day at 23:05 (propose on the last day
| of the month, publish on the 1st, by PHP's UTC date: at 23:05 in New York
| that is already the next day), mode=publish on the 1st at 00:05.
*/
if (config('fec.legacy.schedule_cofm')) {
    Schedule::command('fec:legacy:cli cofm -- --mode=auto')
        ->cron('5 23 * * *')
        ->timezone(config('fec.legacy.cron_timezone', 'America/New_York'))
        ->withoutOverlapping(60)
        ->onOneServer()
        ->appendOutputTo(storage_path('logs/cofm.log'));

    Schedule::command('fec:legacy:cli cofm -- --mode=publish')
        ->cron('5 0 1 * *')
        ->timezone(config('fec.legacy.cron_timezone', 'America/New_York'))
        ->withoutOverlapping(60)
        ->onOneServer()
        ->appendOutputTo(storage_path('logs/cofm.log'));
}
