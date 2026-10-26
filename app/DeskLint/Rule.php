<?php

namespace App\DeskLint;

/**
 * A Desk Lint rule: one read-only query on the catalogue that returns the
 * rows breaking an editorial invariant. Rules never throw to the runner;
 * a rule that cannot run reports itself as an error finding.
 */
interface Rule
{
    /** Short machine id, also the first half of every fingerprint. */
    public function id(): string;

    /** One line, for the report header. */
    public function title(): string;

    /** @return list<Finding> */
    public function run(): array;

    /**
     * One line of context printed under the title after run(), or null.
     * Queue-style rules use it for the size of the whole backlog when the
     * findings are only its head ("2.145 bonus attivi non toccati...").
     */
    public function summary(): ?string;
}
