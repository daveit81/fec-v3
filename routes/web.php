<?php

use App\Fec\Legacy\ComponentRunner;
use App\Fec\Url\Router;
use App\Http\Controllers\Desk\HomeController;
use App\Http\Controllers\Desk\LoginController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LegacyDeskController;
use App\Http\Controllers\RegistryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Phase 0c: status page and JSON health check (both behind Cloudflare Access).
// The root also receives Joomla's non-SEF entry: a request to
// /index.php?option=... reaches Laravel with the path "/" (the front
// controller is stripped from the URI), and Joomla itself treated
// /?option=... the same way. With option= the request goes to the desks.
// Only on the development hostname (APP_URL, v3.): on the live hostname "/"
// is the home, served by the public fallback (routes/stateless.php, no
// cookies) through the router like every other page.
$devHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
$liveHost = strtolower((string) parse_url((string) config('fec.site_url'), PHP_URL_HOST));
$devOnly = $devHost !== '' && $devHost !== $liveHost ? ['domain' => $devHost] : [];
Route::group($devOnly, function () use ($devOnly): void {
    Route::match(['GET', 'POST'], '/', function (Request $request, HealthController $health, LegacyDeskController $desk, ComponentRunner $runner) {
        if ($request->filled('option')) {
            return $desk->index($request, $runner);
        }
        if ($request->isMethod('POST')) {
            abort(405);
        }

        return view('status', ['report' => $health->report()]);
    })->name('status');

    // Phase 4: the home as the live hostname serves it, to look at on v3. (the status page holds "/").
    if ($devOnly !== []) {
        Route::get('/__home', function (Request $request, Router $router, ComponentRunner $runner) {
            $home = $router->home();
            abort_if($home === null, 404);

            return $runner->handle($request, $home);
        })->name('home.preview');
    }
});

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

// Ported desks: their SEF paths (fec.legacy.desk_paths); the non-SEF entry
// (/index.php?option=..., GET and POST, task= included) is the root route above.
// LegacyDeskController sends guests to the login and runs the component.
foreach ((array) config('fec.legacy.desk_paths', []) as $path) {
    Route::match(['GET', 'POST'], '/'.trim((string) $path, '/'), [LegacyDeskController::class, 'sef']);
}

// The desks' standalone scripts (fec.legacy.desk_scripts), at the path the desk pages
// call them by on the live site: /components/com_bonuses/ajax/clicks_live.php...
Route::match(['GET', 'POST'], '/components/{option}/ajax/{script}.php', [LegacyDeskController::class, 'script'])
    ->where(['option' => 'com_[a-z0-9_]+', 'script' => '[a-z0-9_]+']);
