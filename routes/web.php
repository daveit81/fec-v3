<?php

use App\Fec\Legacy\ComponentRunner;
use App\Fec\Url\Router;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Desk\HomeController;
use App\Http\Controllers\Desk\JackpotsController;
use App\Http\Controllers\Desk\LinksController;
use App\Http\Controllers\Desk\LoginController;
use App\Http\Controllers\Desk\MessagesController;
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

// Phase 5: the contact form of /contactus/, rewritten for v3 (com_contactus and com_contact retire):
// one form, the topic picks the mailbox (fec.contact.topics), a copy of every message in the desk.
// Web group: Laravel's CSRF token and the messages after the redirect.
Route::get('/contactus', [ContactController::class, 'show'])->name('contact');
Route::post('/contactus', [ContactController::class, 'send'])->middleware('throttle:20,1')->name('contact.send');
Route::middleware('auth')->group(function (): void {
    Route::get('/desk/messages', [MessagesController::class, 'index'])->name('desk.messages');
    Route::get('/desk/messages/{id}', [MessagesController::class, 'show'])->whereNumber('id')->name('desk.messages.show');
    Route::post('/desk/messages/{id}/status', [MessagesController::class, 'status'])->whereNumber('id')->name('desk.messages.status');
});

// Phase 5: the jackpot desk, rewritten (com_jackpots' desk wrote any column with no token;
// here only "published", see App\Fec\Desk\JackpotsDesk).
Route::middleware('auth')->group(function (): void {
    Route::get('/desk/jackpots', [JackpotsController::class, 'index'])->name('desk.jackpots');
    Route::post('/desk/jackpots/published', [JackpotsController::class, 'publish'])->name('desk.jackpots.publish');
});

// Phase 5: the affiliate links of /goto.php (jos_shortlink), managed in the Joomla backend by
// com_shortlink; written for v3 (App\Fec\Desk\LinksDesk). goto.php itself is routes/stateless.php.
Route::middleware('auth')->group(function (): void {
    Route::get('/desk/links', [LinksController::class, 'index'])->name('desk.links');
    Route::get('/desk/links/new', [LinksController::class, 'create'])->name('desk.links.create');
    Route::post('/desk/links', [LinksController::class, 'store'])->name('desk.links.store');
    Route::get('/desk/links/{id}', [LinksController::class, 'edit'])->whereNumber('id')->name('desk.links.edit');
    Route::post('/desk/links/{id}', [LinksController::class, 'update'])->whereNumber('id')->name('desk.links.update');
    Route::post('/desk/links/{id}/delete', [LinksController::class, 'destroy'])->whereNumber('id')->name('desk.links.destroy');
});

// Ported desks: their SEF paths (fec.legacy.desk_paths); the non-SEF entry
// (/index.php?option=..., GET and POST, task= included) is the root route above.
// LegacyDeskController sends guests to the login and runs the component.
foreach ((array) config('fec.legacy.desk_paths', []) as $path) {
    Route::match(['GET', 'POST'], '/'.trim((string) $path, '/'), [LegacyDeskController::class, 'sef']);
}

// The desks' standalone scripts (fec.legacy.desk_scripts), at the path the desk pages
// call them by on the live site: /components/com_bonuses/ajax/clicks_live.php...
// Only the desk's own names: the web routes are matched before the stateless ones, and
// /components/com_bonuses/ajax/bonus_feedback.php is a public script (fec.legacy.public_scripts).
$deskScripts = array_merge([], ...array_values(array_map(fn ($v) => (array) $v, (array) config('fec.legacy.desk_scripts', []))));
Route::match(['GET', 'POST'], '/components/{option}/ajax/{script}.php', [LegacyDeskController::class, 'script'])
    ->where(['option' => 'com_[a-z0-9_]+', 'script' => $deskScripts === [] ? '(?!)' : implode('|', array_map(fn ($n) => preg_quote((string) $n, '/'), $deskScripts))]);

// The desk files outside the components (fec.legacy.desk_files): the review dashboard
// of fec-admin/, its JSON API, the clusters page, at their live paths.
foreach ((array) config('fec.legacy.desk_files', []) as $deskFile) {
    Route::match(['GET', 'POST'], '/'.ltrim((string) $deskFile, '/'), [LegacyDeskController::class, 'file']);
}
