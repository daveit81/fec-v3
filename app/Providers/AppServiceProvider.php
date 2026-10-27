<?php

namespace App\Providers;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The ported desks post Joomla-style forms: the token travels as the
        // field NAME (JHtml form.token) and the legacy controllers verify it
        // through JRequest::checkToken(), so Laravel's own check is skipped on
        // their paths. Registered here, not in bootstrap/app.php, because the
        // configuration is loaded by the time the providers boot.
        PreventRequestForgery::except(array_merge(
            ['index.php'],
            array_map(fn ($p) => trim((string) $p, '/'), (array) config('fec.legacy.desk_paths', [])),
        ));
    }
}
