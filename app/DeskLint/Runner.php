<?php

namespace App\DeskLint;

use App\Models\DeskLintFinding;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Runs the rules, reconciles the findings with the persisted memory and
 * returns the material for the report: new, known, resolved, errors.
 */
final class Runner
{
    /**
     * @param  list<Rule>  $rules
     * @return array{new: Collection<int, DeskLintFinding>, known: Collection<int, DeskLintFinding>, resolved: Collection<int, DeskLintFinding>, errors: array<string, string>, ran: array<string, int>}
     */
    public function run(array $rules, bool $persist = true): array
    {
        $now = Carbon::now();
        $errors = [];
        $ran = [];
        $seen = [];

        /** @var array<string, Finding> $current */
        $current = [];

        foreach ($rules as $rule) {
            try {
                $findings = $rule->run();
                $ran[$rule->id()] = count($findings);
                foreach ($findings as $finding) {
                    $current[$finding->fingerprint()] = $finding;
                }
            } catch (Throwable $e) {
                $errors[$rule->id()] = $e->getMessage();
            }
        }

        $ruleIds = array_map(fn (Rule $r) => $r->id(), $rules);
        $open = DeskLintFinding::query()
            ->whereIn('rule', $ruleIds)
            ->whereNull('resolved_at')
            ->get()
            ->keyBy('fingerprint');

        $new = collect();
        $known = collect();

        foreach ($current as $fingerprint => $finding) {
            $seen[] = $fingerprint;
            $row = $open->get($fingerprint);

            if ($row) {
                $row->last_seen_at = $now;
                $row->times_seen++;
                $row->subject = $finding->subject;
                $row->detail = $finding->detail;
                $row->url = $finding->url;
                if ($persist) {
                    $row->save();
                }
                $known->push($row);

                continue;
            }

            // Never seen, or seen and resolved earlier: it is news either way.
            $row = DeskLintFinding::query()->where('fingerprint', $fingerprint)->first()
                ?? new DeskLintFinding(['fingerprint' => $fingerprint, 'first_seen_at' => $now, 'times_seen' => 0]);
            $row->fill($finding->toArray());
            $row->first_seen_at = $row->resolved_at ? $now : ($row->first_seen_at ?? $now);
            $row->resolved_at = null;
            $row->last_seen_at = $now;
            $row->times_seen++;
            if ($persist) {
                $row->save();
            }
            $new->push($row);
        }

        // Open findings of rules that ran cleanly and did not report them: resolved.
        $resolved = collect();
        foreach ($open as $fingerprint => $row) {
            if (in_array($fingerprint, $seen, true) || isset($errors[$row->rule])) {
                continue;
            }
            $row->resolved_at = $now;
            if ($persist) {
                $row->save();
            }
            $resolved->push($row);
        }

        return compact('new', 'known', 'resolved', 'errors', 'ran');
    }
}
