<?php

use App\Http\Controllers\Desk\HomeController;
use App\Http\Controllers\Desk\LoginController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LegacyDeskController;
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
// routes/stateless.php: public pages set no cookies. Everything below needs
// the session: the redazione login and the ported desks.

// Phase 2: redazione login (password + one-time code by email).
Route::middleware('throttle:10,1')->group(function (): void {
    Route::get('/desk/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/desk/login', [LoginController::class, 'login'])->name('login.post');
    Route::get('/desk/code', [LoginController::class, 'showCode'])->name('desk.code');
    Route::post('/desk/code', [LoginController::class, 'verifyCode'])->name('desk.code.post');
});
Route::post('/desk/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/desk', HomeController::class)->middleware('auth')->name('desk.home');

// Ported desks: their SEF paths (fec.legacy.desk_paths) and Joomla's non-SEF
// entry, which the desk forms and scripts call (GET and POST, task= included).
// LegacyDeskController sends guests to the login and runs the component.
foreach ((array) config('fec.legacy.desk_paths', []) as $path) {
    Route::match(['GET', 'POST'], '/'.trim((string) $path, '/'), [LegacyDeskController::class, 'sef']);
}
Route::match(['GET', 'POST'], '/index.php', [LegacyDeskController::class, 'index'])->name('legacy.index');
