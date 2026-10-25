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
}
