<?php

namespace App\Http\Controllers\Desk;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * The redazione login: email + password, then a six-digit code sent to the
 * same address (valid ten minutes, one attempt per code). No package, no
 * authenticator app to lose: the mailbox is the second factor, as it is
 * for Cloudflare Access on v3. Throttled by the route.
 */
class LoginController extends Controller
{
    private const CODE_TTL_MINUTES = 10;

    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('desk.home');
        }

        return view('desk.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', strtolower(trim($data['email'])))->first();
        if ($user === null || ! Hash::check($data['password'], (string) $user->password)) {
            Log::warning('desk login: bad credentials', ['email' => $data['email'], 'ip' => $request->ip()]);

            return back()->withInput(['email' => $data['email']])->withErrors(['email' => 'Wrong email or password.']);
        }

        $code = (string) random_int(100000, 999999);
        $user->login_code = Hash::make($code);
        $user->login_code_expires_at = now()->addMinutes(self::CODE_TTL_MINUTES);
        $user->save();

        try {
            Mail::raw(
                "Your FEC v3 desk sign-in code: {$code}\n\nIt works once, for ".self::CODE_TTL_MINUTES." minutes. If you did not try to sign in, ignore this email.\n",
                function ($message) use ($user) {
                    $message->to($user->email)->subject('[FEC] Desk sign-in code '.now(config('fec.timezone'))->format('H:i'));
                }
            );
        } catch (\Throwable $e) {
            Log::error('desk login: code mail failed', ['error' => $e->getMessage()]);

            return back()->withInput(['email' => $data['email']])->withErrors(['email' => 'The code could not be sent: try again in a moment.']);
        }

        $request->session()->put('desk.pending_user_id', $user->id);
        $request->session()->put('desk.remember', $request->boolean('remember'));

        return redirect()->route('desk.code');
    }

    public function showCode(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('desk.pending_user_id')) {
            return redirect()->route('login');
        }

        return view('desk.code');
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $userId = (int) $request->session()->get('desk.pending_user_id', 0);
        $user = $userId > 0 ? User::query()->find($userId) : null;

        if ($user === null || $user->login_code === null || $user->login_code_expires_at === null || $user->login_code_expires_at->isPast()) {
            $request->session()->forget(['desk.pending_user_id', 'desk.remember']);

            return redirect()->route('login')->withErrors(['email' => 'The code has expired: sign in again.']);
        }
        if (! Hash::check($data['code'], (string) $user->login_code)) {
            // one attempt per code: a wrong code burns it
            $user->login_code = null;
            $user->login_code_expires_at = null;
            $user->save();
            $request->session()->forget(['desk.pending_user_id', 'desk.remember']);
            Log::warning('desk login: wrong code', ['user' => $user->id, 'ip' => $request->ip()]);

            return redirect()->route('login')->withErrors(['email' => 'Wrong code: sign in again.']);
        }

        $user->login_code = null;
        $user->login_code_expires_at = null;
        $user->last_login_at = now();
        $user->save();

        $remember = (bool) $request->session()->pull('desk.remember', false);
        $request->session()->forget('desk.pending_user_id');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('desk.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
