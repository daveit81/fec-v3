<?php

namespace App\Fec\Legacy\Joomla;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail as LaravelMail;
use Throwable;

/**
 * JMail as our code uses it (JFactory::getMailer(): the review pipeline's
 * moderation notice): recipients, subject, body, plain text or HTML, sender
 * and reply-to, then Send(). Joomla 2.5 sent through PHPMailer with the
 * site's mail settings and From = configuration.php's mailfrom/fromname;
 * here the application's mailer sends it (MAIL_MAILER, sendmail on the
 * server) from JFactory::getConfig()'s mailfrom/fromname. Send() answers
 * true or false, as PHPMailer did, and never throws.
 */
class Mail
{
    /** @var list<array{0: string, 1: string}> */
    private array $to = [];

    /** @var list<array{0: string, 1: string}> */
    private array $cc = [];

    /** @var list<array{0: string, 1: string}> */
    private array $bcc = [];

    /** @var list<array{0: string, 1: string}> */
    private array $replyTo = [];

    private ?array $from = null;

    private string $subject = '';

    private string $body = '';

    private bool $html = false;

    public string $ErrorInfo = '';

    /** @param  string|array<int, string>  $recipient  @param  string|array<int, string>  $name */
    public function addRecipient(string|array $recipient, string|array $name = ''): static
    {
        return $this->add($this->to, $recipient, $name);
    }

    public function addCC(string|array $cc, string|array $name = ''): static
    {
        return $this->add($this->cc, $cc, $name);
    }

    public function addBCC(string|array $bcc, string|array $name = ''): static
    {
        return $this->add($this->bcc, $bcc, $name);
    }

    /** JMail 2.5: array(email, name), or a bare address. */
    public function addReplyTo(string|array $replyto, string $name = ''): static
    {
        $this->replyTo[] = is_array($replyto)
            ? [trim((string) ($replyto[0] ?? '')), (string) ($replyto[1] ?? '')]
            : [trim($replyto), $name];

        return $this;
    }

    /** @param  string|array{0: string, 1?: string}  $from */
    public function setSender(string|array $from): static
    {
        $this->from = is_array($from) ? [(string) ($from[0] ?? ''), (string) ($from[1] ?? '')] : [$from, ''];

        return $this;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function setBody(string $content): static
    {
        $this->body = $content;

        return $this;
    }

    public function isHTML(bool $ishtml = true): static
    {
        $this->html = $ishtml;

        return $this;
    }

    public function Send(): bool
    {
        if ($this->to === []) {
            $this->ErrorInfo = 'You must provide at least one recipient email address.';

            return false;
        }
        $config = Factory::getConfig();
        $from = $this->from ?? [(string) $config->get('mailfrom', ''), (string) $config->get('fromname', '')];

        try {
            $callback = function (Message $m) use ($from): void {
                foreach ($this->to as [$email, $name]) {
                    $m->to($email, $name !== '' ? $name : null);
                }
                foreach ($this->cc as [$email, $name]) {
                    $m->cc($email, $name !== '' ? $name : null);
                }
                foreach ($this->bcc as [$email, $name]) {
                    $m->bcc($email, $name !== '' ? $name : null);
                }
                foreach ($this->replyTo as [$email, $name]) {
                    $m->replyTo($email, $name !== '' ? $name : null);
                }
                if ($from[0] !== '') {
                    $m->from($from[0], $from[1] !== '' ? $from[1] : null);
                }
                $m->subject($this->subject);
            };
            if ($this->html) {
                LaravelMail::html($this->body, $callback);
            } else {
                LaravelMail::raw($this->body, $callback);
            }

            return true;
        } catch (Throwable $e) {
            $this->ErrorInfo = $e->getMessage();
            error_log('legacy JMail: '.$e->getMessage());

            return false;
        }
    }

    /** JMail::sendMail(): the one-call form. */
    public function sendMail(string $from, string $fromName, string|array $recipient, string $subject, string $body, bool $mode = false, mixed $cc = null, mixed $bcc = null, mixed $attachment = null, mixed $replyTo = null, mixed $replyToName = null): bool
    {
        $this->setSender([$from, $fromName]);
        $this->setSubject($subject);
        $this->setBody($body);
        $this->isHTML($mode);
        $this->addRecipient($recipient);
        if ($cc !== null) {
            $this->addCC($cc);
        }
        if ($bcc !== null) {
            $this->addBCC($bcc);
        }
        if ($replyTo !== null) {
            $this->addReplyTo($replyTo, (string) ($replyToName ?? ''));
        }

        return $this->Send();
    }

    /**
     * @param  list<array{0: string, 1: string}>  $list
     * @param  string|array<int, string>  $address
     * @param  string|array<int, string>  $name
     */
    private function add(array &$list, string|array $address, string|array $name): static
    {
        $addresses = is_array($address) ? array_values($address) : [$address];
        $names = is_array($name) ? array_values($name) : [];
        foreach ($addresses as $i => $a) {
            $list[] = [trim((string) $a), is_array($name) ? (string) ($names[$i] ?? '') : (string) $name];
        }

        return $this;
    }
}
