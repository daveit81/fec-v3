<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desk Lint memory (framework database): one row per inconsistency ever
 * seen, so the nightly report can tell new findings from known ones and
 * notice when one disappears. Lives next to sessions and cache, not in the
 * legacy catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desk_lint_findings', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 190)->unique();
            $table->string('rule', 64)->index();
            $table->string('key', 120);
            $table->string('subject', 255);
            $table->text('detail')->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable()->index();
            $table->unsignedInteger('times_seen')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desk_lint_findings');
    }
};
