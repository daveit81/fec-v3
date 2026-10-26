<?php

namespace App\Console\Commands;

use App\DeskLint\Registry;
use App\DeskLint\Report;
use App\DeskLint\Runner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Desk Lint (approved 22 Oct 2026): the nightly, read-only check of the
 * catalogue that turns the inconsistencies once found by hand into queries,
 * remembers what it has already reported and sends only the news, with a
 * full recap once a week. First Artisan command of FEC v3.
 *
 *   php artisan fec:desk-lint                 run and print
 *   php artisan fec:desk-lint --probe         distributions of status/type/flags, to calibrate config/fec.php
 *   php artisan fec:desk-lint --rules=a,b     only some rules
 *   php artisan fec:desk-lint --full          full recap regardless of the weekday
 *   php artisan fec:desk-lint --dry-run       do not touch the memory table
 *   php artisan fec:desk-lint --notify        also send by mail and Telegram (when configured)
 */
class DeskLint extends Command
{
    protected $signature = 'fec:desk-lint
        {--probe : Print value distributions used by the rules and exit}
        {--rules= : Comma-separated rule ids to run}
        {--full : Full recap of every open finding}
        {--dry-run : Run the rules without updating the findings memory}
        {--notify : Send the report by mail and Telegram}
        {--json : Print the raw result as JSON}';

    protected $description = 'Nightly consistency check of the FEC catalogue (Desk Lint)';

    public function handle(Runner $runner): int
    {
        if ($this->option('probe')) {
            return $this->probe();
        }

        $only = $this->option('rules') ? array_map('trim', explode(',', (string) $this->option('rules'))) : null;
        $rules = Registry::rules($only);
        if ($rules === []) {
            $this->error('No rule matches: '.implode(', ', $only ?? []));

            return self::INVALID;
        }

        // Desk-local clock: the recap weekday and every timestamp in the report are Europe/Rome, not UTC.
        $now = Carbon::now((string) config('fec.timezone', 'Europe/Rome'));
        $full = (bool) $this->option('full') || $now->dayOfWeekIso === (int) config('fec.desk_lint.recap_weekday');
        $result = $runner->run($rules, ! $this->option('dry-run'));
        $report = new Report($rules, $result, $full, $now);

        if ($this->option('json')) {
            $this->line(json_encode([
                'at' => $now->toIso8601String(),
                'full' => $full,
                'ran' => $result['ran'],
                'errors' => $result['errors'],
                'new' => $result['new']->map->toArray()->values(),
                'known' => $result['known']->map->toArray()->values(),
                'resolved' => $result['resolved']->map->toArray()->values(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line($report->text());
            $this->line('Rules run: '.collect($result['ran'])->map(fn ($n, $id) => "$id=$n")->implode('  '));
        }

        if ($this->option('notify')) {
            if ($report->isEmpty()) {
                $this->info('Nothing new: no notification sent.');
            } else {
                $this->notify($report);
            }
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function notify(Report $report): void
    {
        $token = (string) config('fec.desk_lint.telegram_token');
        $chat = (string) config('fec.desk_lint.telegram_chat_id');
        if ($token !== '' && $chat !== '') {
            foreach ($report->telegramChunks() as $chunk) {
                try {
                    $response = Http::timeout(15)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                        'chat_id' => $chat,
                        'text' => $chunk,
                        'parse_mode' => 'HTML',
                        'disable_web_page_preview' => true,
                    ]);
                    if (! $response->successful()) {
                        Log::warning('Desk Lint: Telegram refused the message', ['status' => $response->status(), 'body' => mb_strimwidth($response->body(), 0, 300, '...')]);
                    }
                } catch (Throwable $e) {
                    Log::warning('Desk Lint: Telegram send failed', ['error' => $e->getMessage()]);
                }
            }
            $this->info('Telegram: sent '.count($report->telegramChunks()).' message(s).');
        } else {
            $this->comment('Telegram: not configured (FEC_TG_BOT_TOKEN / FEC_TG_DESK_CHAT_ID).');
        }

        $to = config('fec.desk_lint.mail_to', []);
        if ($to !== []) {
            try {
                Mail::raw($report->text(), function ($message) use ($to) {
                    $message->to($to)->subject('[FEC] Desk Lint '.Carbon::now((string) config('fec.timezone', 'Europe/Rome'))->format('d/m'));
                });
                $this->info('Mail: sent to '.implode(', ', $to).' via '.config('mail.default').'.');
            } catch (Throwable $e) {
                Log::warning('Desk Lint: mail send failed', ['error' => $e->getMessage()]);
                $this->warn('Mail failed: '.$e->getMessage());
            }
        }
    }

    /** Distributions of the columns the rules depend on, to calibrate config/fec.php. */
    private function probe(): int
    {
        $db = DB::connection('fec');
        $show = function (string $label, string $sql) use ($db) {
            $this->line('');
            $this->info($label);
            try {
                foreach ($db->select($sql) as $row) {
                    $row = (array) $row;
                    $this->line(sprintf('  %-32s %8s', var_export(reset($row), true), end($row)));
                }
            } catch (Throwable $e) {
                $this->error('  '.$e->getMessage());
            }
        };

        $p = $db->getTablePrefix();
        $show('jos_bonuses.status', "SELECT status, COUNT(*) n FROM {$p}bonuses GROUP BY status ORDER BY n DESC");
        $show('jos_bonuses.type (live bonuses)', "SELECT type, COUNT(*) n FROM {$p}bonuses WHERE ".config('fec.bonus.active_where').' GROUP BY type ORDER BY n DESC LIMIT 20');
        $show('jos_bonuses.currency (live bonuses)', "SELECT currency, COUNT(*) n FROM {$p}bonuses WHERE ".config('fec.bonus.active_where').' GROUP BY currency ORDER BY n DESC LIMIT 15');
        $show('jos_jreviews_content.jr_closed', "SELECT jr_closed, COUNT(*) n FROM {$p}jreviews_content GROUP BY jr_closed ORDER BY n DESC");
        $show('jos_jreviews_content.jr_suspended', "SELECT jr_suspended, COUNT(*) n FROM {$p}jreviews_content GROUP BY jr_suspended ORDER BY n DESC");
        $show('jos_jreviews_content.jr_blacklisted', "SELECT jr_blacklisted, COUNT(*) n FROM {$p}jreviews_content GROUP BY jr_blacklisted ORDER BY n DESC");
        $show('jos_fec_bonus_packages.status', "SELECT status, COUNT(*) n FROM {$p}fec_bonus_packages GROUP BY status");
        $show('jos_fec_clock_brands.enabled', "SELECT enabled, COUNT(*) n FROM {$p}fec_clock_brands GROUP BY enabled");
        $this->line('');
        $this->comment('Adjust FEC_BONUS_ACTIVE_WHERE and FEC_BONUS_WELCOME_TYPES in .env if these differ from the defaults in config/fec.php.');

        return self::SUCCESS;
    }
}
