<?php

namespace App\DeskLint;

use App\Models\DeskLintFinding;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns the runner result into text (console, mail) and Telegram HTML.
 * Nightly: news and resolutions only. Recap day: everything still open.
 */
final class Report
{
    /** @param  list<Rule>  $rules */
    public function __construct(
        private readonly array $rules,
        private readonly array $result,
        private readonly bool $full,
        private readonly Carbon $at,
    ) {
    }

    /** Resolved findings listed in full before the "... e altre N" line (a rule recalibration can resolve thousands at once). */
    private const RESOLVED_SHOWN = 30;

    public function isEmpty(): bool
    {
        return $this->result['new']->isEmpty()
            && $this->result['resolved']->isEmpty()
            && $this->result['errors'] === []
            && (! $this->full || $this->result['known']->isEmpty());
    }

    public function text(): string
    {
        $lines = [];
        $lines[] = sprintf('Desk Lint - %s%s', $this->at->format('D d M Y H:i'), $this->full ? ' - riepilogo completo' : '');
        $lines[] = sprintf('Nuove: %d   Risolte: %d   Ancora aperte: %d   Regole: %d%s',
            $this->result['new']->count(), $this->result['resolved']->count(), $this->result['known']->count(),
            count($this->rules), $this->result['errors'] ? '   ERRORI: '.count($this->result['errors']) : '');
        $lines[] = '';

        foreach ($this->rules as $rule) {
            $new = $this->result['new']->where('rule', $rule->id());
            $known = $this->full ? $this->result['known']->where('rule', $rule->id()) : collect();
            $summary = $this->full ? $rule->summary() : null;
            if ($new->isEmpty() && $known->isEmpty() && $summary === null && ! isset($this->result['errors'][$rule->id()])) {
                continue;
            }
            $lines[] = strtoupper($rule->title());
            if ($summary !== null) {
                $lines[] = '  '.$summary;
            }
            if (isset($this->result['errors'][$rule->id()])) {
                $lines[] = '  ERRORE: '.$this->result['errors'][$rule->id()];
            }
            foreach ($new as $f) {
                $lines[] = '  [NUOVO] '.$this->line($f);
            }
            foreach ($known as $f) {
                $lines[] = '  [aperto da '.$this->day($f->first_seen_at).'] '.$this->line($f);
            }
            $lines[] = '';
        }

        if ($this->result['resolved']->isNotEmpty()) {
            $lines[] = 'RISOLTE DALL\'ULTIMO GIRO';
            foreach ($this->result['resolved']->take(self::RESOLVED_SHOWN) as $f) {
                $lines[] = '  [ok] '.$f->rule.' - '.$f->subject;
            }
            if ($this->result['resolved']->count() > self::RESOLVED_SHOWN) {
                $lines[] = sprintf('  ... e altre %d', $this->result['resolved']->count() - self::RESOLVED_SHOWN);
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /** A stored UTC timestamp shown as a desk-local day. */
    private function day(Carbon $at): string
    {
        return $at->copy()->timezone($this->at->getTimezone())->format('d/m');
    }

    /** Telegram HTML, split in chunks under the 4096-character limit. */
    public function telegramChunks(): array
    {
        $esc = fn (string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $blocks = [];
        $blocks[] = sprintf('<b>Desk Lint</b> - %s%s', $this->at->format('d/m H:i'), $this->full ? ' - riepilogo' : '');
        $blocks[] = sprintf('Nuove %d - Risolte %d - Aperte %d%s',
            $this->result['new']->count(), $this->result['resolved']->count(), $this->result['known']->count(),
            $this->result['errors'] ? ' - <b>errori '.count($this->result['errors']).'</b>' : '');

        foreach ($this->rules as $rule) {
            $new = $this->result['new']->where('rule', $rule->id());
            $known = $this->full ? $this->result['known']->where('rule', $rule->id()) : collect();
            if ($new->isEmpty() && $known->isEmpty() && ! isset($this->result['errors'][$rule->id()]) && ! ($this->full && $rule->summary() !== null)) {
                continue;
            }
            $b = '<b>'.$esc($rule->title()).'</b>';
            if ($this->full && $rule->summary() !== null) {
                $b .= "\n".'<i>'.$esc($rule->summary()).'</i>';
            }
            if (isset($this->result['errors'][$rule->id()])) {
                $b .= "\n".'errore: '.$esc($this->result['errors'][$rule->id()]);
            }
            foreach ($new->take(15) as $f) {
                $b .= "\n".'+ '.$this->tgLine($f, $esc);
            }
            if ($new->count() > 15) {
                $b .= "\n".sprintf('... e altre %d', $new->count() - 15);
            }
            foreach ($known->take(10) as $f) {
                $b .= "\n".'- '.$this->tgLine($f, $esc);
            }
            if ($known->count() > 10) {
                $b .= "\n".sprintf('... e altre %d aperte', $known->count() - 10);
            }
            $blocks[] = $b;
        }

        if ($this->result['resolved']->isNotEmpty()) {
            $b = '<b>Risolte</b>';
            foreach ($this->result['resolved']->take(15) as $f) {
                $b .= "\n".'ok '.$esc($f->subject);
            }
            $blocks[] = $b;
        }

        $chunks = [];
        $cur = '';
        foreach ($blocks as $block) {
            if ($cur !== '' && strlen($cur) + strlen($block) + 2 > 3900) {
                $chunks[] = $cur;
                $cur = '';
            }
            $cur .= ($cur === '' ? '' : "\n\n").$block;
        }
        if ($cur !== '') {
            $chunks[] = $cur;
        }

        return $chunks;
    }

    private function line(DeskLintFinding $f): string
    {
        return trim($f->subject.($f->detail ? ' - '.$f->detail : '').($f->url ? ' '.$f->url : ''));
    }

    private function tgLine(DeskLintFinding $f, callable $esc): string
    {
        $s = $f->url ? '<a href="'.$esc($f->url).'">'.$esc($f->subject).'</a>' : $esc($f->subject);

        return $s.($f->detail ? ' - '.$esc(mb_strimwidth($f->detail, 0, 140, '...')) : '');
    }
}
