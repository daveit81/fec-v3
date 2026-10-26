<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\RegistryController;
use Illuminate\Support\Facades\Route;

// Phase 0c: status page and JSON health check (both behind Cloudflare Access).
Route::get('/', function (HealthController $health) {
    return view('status', ['report' => $health->report()]);
})->name('status');

Route::get('/health', HealthController::class)->name('health');

// Phase 1 probe: what the router decides for a path (?path=/online-casinos/king-billy-casino).
Route::get('/__registry', RegistryController::class)->name('registry');

// /goto.php and the public fallback (the URL registry router) live in
// routes/stateless.php: public pages set no cookies. Desk views, which need
// the session, get explicit routes in this file when they are ported.
