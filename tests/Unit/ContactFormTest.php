<?php

namespace Tests\Unit;

use App\Fec\Contact\ContactForm;
use PHPUnit\Framework\TestCase;

/** The rules of the contact form of /contactus/. */
final class ContactFormTest extends TestCase
{
    /** @return array<string, array{label: string, to: string, url: string, hint: string}> */
    private function topics(): array
    {
        return ContactForm::topics([
            'correction' => ['label' => 'Correction', 'to' => 'corrections@freeextrachips.com', 'url' => 'required', 'hint' => 'h'],
            'tip' => ['label' => 'Tip', 'to' => 'tips@freeextrachips.com', 'url' => 'optional'],
            'payments' => ['label' => 'Payments', 'to' => 'financial@freeextrachips.com', 'url' => 'none'],
            'broken' => ['label' => 'No mailbox', 'to' => 'not an address'],
            'Bad Key' => ['label' => 'x', 'to' => 'x@freeextrachips.com'],
        ]);
    }

    private function input(array $over = []): array
    {
        return $over + [
            'topic' => 'tip',
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'page_url' => '',
            'subject' => 'A code',
            'message' => 'The code FEC50 on this casino does not work any more.',
        ];
    }

    public function test_topics_keep_only_valid_ones(): void
    {
        $this->assertSame(['correction', 'tip', 'payments'], array_keys($this->topics()));
        $this->assertSame('none', $this->topics()['payments']['url']);
        $this->assertSame('', $this->topics()['tip']['hint']);
    }

    public function test_a_good_message_passes_and_is_cleaned(): void
    {
        [$v, $errors] = ContactForm::validate($this->input(['name' => "  Ada\r\nBcc: x@y.z ", 'message' => "Line one is long enough.\n\n\n\nLine <b>two</b>"]), $this->topics());
        $this->assertSame([], $errors);
        $this->assertSame('Ada Bcc: x@y.z', $v['name']);
        $this->assertSame("Line one is long enough.\n\nLine two", $v['message']);
    }

    public function test_errors_by_field(): void
    {
        [, $errors] = ContactForm::validate(['topic' => 'nope', 'email' => 'bad', 'message' => 'short'], $this->topics());
        $this->assertSame(['topic', 'name', 'email', 'subject', 'message'], array_keys($errors));
    }

    public function test_the_page_url_follows_the_topic(): void
    {
        [, $errors] = ContactForm::validate($this->input(['topic' => 'correction']), $this->topics());
        $this->assertArrayHasKey('page_url', $errors);

        [$v, $errors] = ContactForm::validate($this->input(['topic' => 'correction', 'page_url' => 'https://www.freeextrachips.com/bonuses/']), $this->topics());
        $this->assertSame([], $errors);
        $this->assertSame('https://www.freeextrachips.com/bonuses/', $v['page_url']);

        [, $errors] = ContactForm::validate($this->input(['page_url' => 'javascript:alert(1)']), $this->topics());
        $this->assertArrayHasKey('page_url', $errors);

        // a topic without a page drops whatever was sent
        [$v, $errors] = ContactForm::validate($this->input(['topic' => 'payments', 'page_url' => 'javascript:alert(1)']), $this->topics());
        $this->assertSame([], $errors);
        $this->assertSame('', $v['page_url']);
    }

    public function test_timing(): void
    {
        $this->assertSame('ok', ContactForm::timing(1000, 1010, 4));
        $this->assertSame('fast', ContactForm::timing(1000, 1002, 4));
        $this->assertSame('stale', ContactForm::timing(1000, 1000 + 86401, 4));
        $this->assertSame('bad', ContactForm::timing(null, 1000, 4));
        $this->assertSame('bad', ContactForm::timing(5000, 1000, 4));
    }

    public function test_turnstile_keys_are_read_not_run(): void
    {
        $dir = sys_get_temp_dir().'/fec-contact-test-'.getmypid();
        @mkdir($dir);
        $file = $dir.'/fec_review_config.php';
        file_put_contents($file, "<?php\ndefined('_JEXEC') or die;\ndefine('FEC_TURNSTILE_SITE_KEY', '0x4AAAsite');\ndefine( 'FEC_TURNSTILE_SECRET_KEY' , '0x4AAAsecret' );\n");
        $this->assertSame(['site' => '0x4AAAsite', 'secret' => '0x4AAAsecret'], ContactForm::turnstileKeys($file));
        file_put_contents($file, "<?php\ndefine('FEC_TURNSTILE_SITE_KEY', 'PASTE_SITE_KEY_HERE');\n");
        $this->assertSame(['site' => '', 'secret' => ''], ContactForm::turnstileKeys($file));
        $this->assertSame(['site' => '', 'secret' => ''], ContactForm::turnstileKeys($dir.'/missing.php'));
        @unlink($file);
        @rmdir($dir);
    }

    public function test_mail_body(): void
    {
        $topics = $this->topics();
        [$v] = ContactForm::validate($this->input(['topic' => 'correction', 'page_url' => 'https://www.freeextrachips.com/x']), $topics);
        $body = ContactForm::mailBody($v, $topics['correction'], 'IT', 'https://v3.freeextrachips.com/desk/messages/7', 'https://www.freeextrachips.com/contactus/');
        $this->assertStringContainsString("Topic:   Correction\n", $body);
        $this->assertStringContainsString("From:    Ada <ada@example.com>\n", $body);
        $this->assertStringContainsString("Page:    https://www.freeextrachips.com/x\n", $body);
        $this->assertStringContainsString("Country: IT\n", $body);
        $this->assertStringContainsString("Sent from the contact form at https://www.freeextrachips.com/contactus/\n", $body);
        $this->assertStringContainsString('Copy in the desk (v3): https://v3.freeextrachips.com/desk/messages/7', $body);
    }
}
