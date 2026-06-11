<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chronological event log for each player's story progress.
 *
 * event_type values:
 *   stage_complete  — a quest stage was finished
 *   branch_choice   — player chose a side on a branch stage
 *   watcher_signal  — a Watcher transmission was delivered
 *   arc_unlocked    — a new quest arc became available
 *   referral        — a doc introduction was issued
 *
 * payload — JSON bag of event-specific data rendered by the archive page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_quest_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['player_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_quest_log');
    }
};
