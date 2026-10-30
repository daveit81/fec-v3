<?php

namespace App\Fec\Contact;

/**
 * The rules of the contact form of /contactus/ (phase 5: written for v3, the two
 * Joomla contact components retire). One form, one topic per message, each topic
 * mailed to its own mailbox (config fec.contact.topics). Pure functions here, so
 * the controller only wires the request, the database and the mailer.
 */
final class ContactForm
{
    public const NAME_MAX = 100;

    public const EMAIL_MAX = 254;

    public const SUBJECT_MAX = 150;

    public const URL_MAX = 500;

    public const MESSAGE_MIN = 20;

    public const MESSAGE_MAX = 5000;

    /**
     * The topics as configured, each with its label, recipient, hint and page-url rule.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, array{label: string, to: string, url: string, hint: string}>
     */
    public static function topics(array $config): array
    {
        $out = [];
        foreach ($config as $key => $t) {
            if (! is_array($t) || ! preg_match('/^[a-z][a-z0-9_]{0,31}$/', (string) $key)) {
                continue;
            }
            $to = trim((string) ($t['to'] ?? ''));
            if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $url = (string) ($t['url'] ?? 'none');
            $out[(string) $key] = [
                'label' => (string) ($t['label'] ?? $key),
                'to' => $to,
                'url' => in_array($url, ['required', 'optional', 'none'], true) ? $url : 'none',
                'hint' => (string) ($t['hint'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * The values of a submitted form, cleaned, or the errors (field => message).
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, array{label: string, to: string, url: string, hint: string}>  $topics
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    public static function validate(array $input, array $topics): array
    {
        $v = [];
        $errors = [];

        $topic = self::line($input['topic'] ?? '');
        if (! isset($topics[$topic])) {
            $errors['topic'] = 'Choose what your message is about.';
        }
        $v['topic'] = $topic;

        $name = self::line($input['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Tell us your name.';
        } elseif (mb_strlen($name) > self::NAME_MAX) {
            $errors['name'] = 'Your name is too long.';
        }
        $v['name'] = $name;

        $email = self::line($input['email'] ?? '');
        if ($email === '' || strlen($email) > self::EMAIL_MAX || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter an email address we can answer.';
        }
        $v['email'] = $email;

        $url = self::line($input['page_url'] ?? '');
        $rule = $topics[$topic]['url'] ?? 'none';
        if ($rule === 'none') {
            $url = '';
        } elseif ($url === '' && $rule === 'required') {
            $errors['page_url'] = 'Paste the address of the page this is about.';
        } elseif ($url !== '' && (strlen($url) > self::URL_MAX || ! preg_match('#^https?://#i', $url) || ! filter_var($url, FILTER_VALIDATE_URL))) {
            $errors['page_url'] = 'The page address must start with http:// or https://.';
        }
        $v['page_url'] = $url;

        $subject = self::line($input['subject'] ?? '');
        if ($subject === '') {
            $errors['subject'] = 'Give your message a subject.';
        } elseif (mb_strlen($subject) > self::SUBJECT_MAX) {
            $errors['subject'] = 'The subject is too long.';
        }
        $v['subject'] = $subject;

        $message = self::text($input['message'] ?? '');
        $len = mb_strlen($message);
        if ($len < self::MESSAGE_MIN) {
            $errors['message'] = 'Your message is too short: tell us a little more.';
        } elseif ($len > self::MESSAGE_MAX) {
            $errors['message'] = 'Your message is too long (5,000 characters at most).';
        }
        $v['message'] = $message;

        return [$v, $errors];
    }

    /** One line of text: no control characters (a header injection needs a line break), spaces collapsed. */
    public static function line(mixed $value): string
    {
        $s = is_string($value) ? $value : '';
        $s = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);

        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    /** A message body: line breaks kept (at most two in a row), other control characters dropped, no markup. */
    public static function text(mixed $value): string
    {
        $s = is_string($value) ? str_replace(["\r\n", "\r"], "\n", $value) : '';
        $s = (string) preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', '', $s);
        $s = strip_tags($s);
        $s = (string) preg_replace("/\n{3,}/", "\n\n", $s);

        return trim($s);
    }

    /**
     * The verdict on how long the form stayed open: "ok", "fast" (a robot), "stale" (an
     * old page: reload it) or "bad" (no or forged stamp).
     */
    public static function timing(?int $openedAt, int $now, int $minSeconds, int $maxSeconds = 86400): string
    {
        if ($openedAt === null || $openedAt <= 0 || $openedAt > $now + 60) {
            return 'bad';
        }
        $age = $now - $openedAt;
        if ($age < $minSeconds) {
            return 'fast';
        }

        return $age > $maxSeconds ? 'stale' : 'ok';
    }

    /**
     * The Turnstile keys the review form uses, read (never run: it starts with Joomla's
     * _JEXEC guard) from the live fec_review_config.php: one pair of keys for the site.
     *
     * @return array{site: string, secret: string}
     */
    public static function turnstileKeys(string $file): array
    {
        $keys = ['site' => '', 'secret' => ''];
        $src = is_file($file) && is_readable($file) ? (string) file_get_contents($file) : '';
        if (preg_match_all("/define\\(\\s*'(FEC_TURNSTILE_SITE_KEY|FEC_TURNSTILE_SECRET_KEY)'\\s*,\\s*'([^']*)'\\s*\\)/", $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $d) {
                $keys[$d[1] === 'FEC_TURNSTILE_SITE_KEY' ? 'site' : 'secret'] = $d[2];
            }
        }
        foreach ($keys as $k => $v) {
            if (str_starts_with($v, 'PASTE_')) {
                $keys[$k] = '';
            }
        }

        return $keys;
    }

    /**
     * The plain-text mail to the topic's mailbox. It names the form it came from (the live
     * site's address once /contactus/ is served under www, v3's while testing) and links the
     * copy in the desk, which lives on v3 only (behind Cloudflare Access).
     */
    public static function mailBody(array $v, array $topic, string $country, string $deskUrl, string $formUrl = ''): string
    {
        $lines = [
            'New message from the FreeExtraChips contact form',
            '',
            'Topic:   '.$topic['label'],
            'From:    '.$v['name'].' <'.$v['email'].'>',
        ];
        if (($v['page_url'] ?? '') !== '') {
            $lines[] = 'Page:    '.$v['page_url'];
        }
        if ($country !== '') {
            $lines[] = 'Country: '.$country;
        }
        $lines[] = 'Subject: '.$v['subject'];
        $lines[] = '';
        $lines[] = $v['message'];
        $lines[] = '';
        $lines[] = '--';
        if ($formUrl !== '') {
            $lines[] = 'Sent from the contact form at '.$formUrl;
        }
        $lines[] = 'Reply to this email to answer the sender.';
        if ($deskUrl !== '') {
            $lines[] = 'Copy in the desk (v3): '.$deskUrl;
        }

        return implode("\n", $lines)."\n";
    }
}
