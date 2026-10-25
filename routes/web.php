<?php

use App\Http\Controllers\GotoController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RegistryController;
use Illuminate\Support\Facades\Route;

// Phase 0c: status page and JSON health check (both behind Cloudflare Access).
Route::get('/', function (HealthController $health) {
    return view('status', ['report' => $health->report()]);
})->name('status');

Route::get('/health', HealthController::class)->name('health');

// Phase 1 probe: ask the URL registry about a path (?path=/online-casinos/king-billy-casino).
Route::get('/__registry', RegistryController::class)->name('registry');

// Phase 1, first production route: the tracked affiliate redirect (parity with goto.php + com_shortlink).
Route::get('/goto.php', GotoController::class)->name('goto');
