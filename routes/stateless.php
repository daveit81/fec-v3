<?php

use App\Http\Controllers\FrontController;
use App\Http\Controllers\GotoController;
use App\Http\Controllers\PublicScriptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Stateless routes (no "web" middleware group)
|--------------------------------------------------------------------------
|
| Registered from bootstrap/app.php outside the web group: no session, no
| CSRF, no cookies. For endpoints served under www.freeextrachips.com that
| must not touch the visitor's cookie jar, such as the tracked redirect.
|
*/

// Phase 1, first production route: the tracked affiliate redirect (parity with goto.php + com_shortlink).
Route::get('/goto.php', GotoController::class)->name('goto');

// Phase 5: the live site's public standalone endpoints, verbatim at their own paths
// (fec.legacy.public_scripts: newsletter clicks and sign-ups, bonus votes, reader reviews).
foreach ((array) config('fec.legacy.public_scripts', []) as $script) {
    Route::match(['GET', 'HEAD', 'POST'], '/'.ltrim((string) $script, '/'), PublicScriptController::class);
}

// Phase 5: com_contact's page (contact 1) retires with the new contact form (Davey, 30 Oct 2026).
Route::permanentRedirect('/freeextrachips-team', '/contactus/');

// Phase 1 kernel: every other GET goes through the URL registry and the
// run-time patterns (App\Fec\Url\Router) to a ported view or a 404. Public
// pages: no session, no CSRF cookie, like the live site's anonymous pages.
// Laravel matches fallback routes last, whatever file registers them.
Route::fallback(FrontController::class)->name('front');
