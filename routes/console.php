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
