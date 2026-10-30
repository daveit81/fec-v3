<?php

namespace App\Http\Controllers;

use App\Fec\Contact\ContactForm;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * /contactus/: the contact form of v3 (App\Fec\Contact\ContactForm has the rules).
 * Web group: Laravel's CSRF token and the messages after the redirect. Spam guards:
 * a honeypot, the seconds the page stayed open (an encrypted stamp, no state kept),
 * Cloudflare Turnstile with the review form's keys, and a limit per address per hour.
 * Every message is stored (contact_messages) before it is mailed.
 */
class ContactController extends Controller
{
    public function show(Request $request): Response
    {
        $keys = $this->turnstileKeys();

        return response()->view('contact.page', [
            'topics' => ContactForm::topics((array) config('fec.contact.topics', [])),
            'stamp' => Crypt::encryptString((string) time()),
            'turnstileSiteKey' => $keys['secret'] !== '' ? $keys['site'] : '',
            'sent' => (string) $request->session()->get('contact_sent', ''),
            'canonical' => rtrim((string) config('fec.site_url'), '/').'/contactus/',
        ])->header('Cache-Control', 'no-cache, private');
    }

    public function send(Request $request): RedirectResponse
    {
        $topics = ContactForm::topics((array) config('fec.contact.topics', []));
        $back = redirect('/contactus/');

        // the honeypot: a robot fills every field; it is told what a person is told
        if (trim((string) $request->input('website', '')) !== '') {
            return $back->with('contact_sent', 'ok');
        }

        $openedAt = null;
        try {
            $openedAt = (int) Crypt::decryptString((string) $request->input('ts', ''));
        } catch (DecryptException) {
            $openedAt = null;
        }
        $timing = ContactForm::timing($openedAt, time(), (int) config('fec.contact.min_seconds', 4));
        if ($timing !== 'ok') {
            $msg = $timing === 'stale'
                ? 'This page was open for too long. Please send your message again.'
                : 'The form was sent too quickly. Please check your message and send it again.';

            return $back->withInput($request->except('ts'))->withErrors(['form' => $msg]);
        }

        [$v, $errors] = ContactForm::validate($request->all(), $topics);
        if ($errors !== []) {
            return $back->withInput($request->except('ts'))->withErrors($errors);
        }

        $ip = $this->clientIp($request);
        $ipHash = hash('sha256', $ip.'|'.config('app.key'));

        $keys = $this->turnstileKeys();
        if ($keys['secret'] !== '' && ! $this->turnstileOk((string) $request->input('cf-turnstile-response', ''), $keys['secret'], $ip)) {
            return $back->withInput($request->except('ts'))->withErrors(['form' => 'Please complete the verification below the form and send again.']);
        }

        $recent = DB::table('contact_messages')->where('ip_hash', $ipHash)
            ->where('created_at', '>=', now()->subHour())->count();
        if ($recent >= (int) config('fec.contact.per_hour', 5)) {
            return $back->withInput($request->except('ts'))->withErrors(['form' => 'You have sent several messages in the last hour. Please try again later.']);
        }

        $topic = $topics[$v['topic']];
        $country = strtoupper(substr((string) $request->header('CF-IPCountry', ''), 0, 2));
        $country = preg_match('/^[A-Z]{2}$/', $country) ? $country : '';
        $id = DB::table('contact_messages')->insertGetId([
            'topic' => $v['topic'],
            'recipient' => $topic['to'],
            'name' => $v['name'],
            'email' => $v['email'],
            'page_url' => $v['page_url'] !== '' ? $v['page_url'] : null,
            'subject' => $v['subject'],
            'message' => $v['message'],
            'country' => $country !== '' ? $country : null,
            'ip_hash' => $ipHash,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deskUrl = rtrim((string) config('app.url'), '/').'/desk/messages/'.$id;
        $body = ContactForm::mailBody($v, $topic, $country, $deskUrl);
        $from = (string) (config('fec.legacy.mailfrom') ?: config('mail.from.address'));
        try {
            Mail::raw($body, function ($m) use ($topic, $v, $from): void {
                $m->to($topic['to'])
                    ->from($from, 'FreeExtraChips contact form')
                    ->replyTo($v['email'], $v['name'])
                    ->subject('[FEC contact] '.$topic['label'].': '.$v['subject']);
            });
            DB::table('contact_messages')->where('id', $id)->update(['mailed_at' => now(), 'updated_at' => now()]);
        } catch (Throwable $e) {
            Log::error('contact form: mail of message '.$id.' to '.$topic['to'].' failed: '.$e->getMessage());
            DB::table('contact_messages')->where('id', $id)->update(['mail_error' => mb_substr($e->getMessage(), 0, 255), 'updated_at' => now()]);
        }

        return $back->with('contact_sent', $v['topic']);
    }

    /** @return array{site: string, secret: string} */
    private function turnstileKeys(): array
    {
        $file = rtrim((string) config('fec.legacy.docroot', ''), '/').'/components/com_bonuses/fec_review_config.php';
        $keys = ContactForm::turnstileKeys($file);
        if ($keys['site'] === '') {
            $keys['site'] = (string) config('fec.legacy.review.turnstile_site_key', '');
        }
        if ($keys['site'] === '') {
            $keys['secret'] = '';
        }

        return $keys;
    }

    private function turnstileOk(string $token, string $secret, string $ip): bool
    {
        if (trim($token) === '') {
            return false;
        }
        try {
            $rsp = Http::asForm()->timeout(6)->connectTimeout(3)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));

            return $rsp->ok() && (bool) $rsp->json('success');
        } catch (Throwable $e) {
            Log::warning('contact form: Turnstile verification unreachable: '.$e->getMessage());

            return false;
        }
    }

    /** The visitor's address behind Cloudflare (CF-Connecting-IP), else the connection's. */
    private function clientIp(Request $request): string
    {
        $cf = (string) $request->header('CF-Connecting-IP', '');

        return filter_var($cf, FILTER_VALIDATE_IP) ? $cf : (string) $request->ip();
    }
}
