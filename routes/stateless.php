<?php

use App\Http\Controllers\GotoController;
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
