<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the REP system.
 *
 * - Drops the player_reputation table entirely.
 * - Removes rep_required from quest_arcs (arc unlocking is now purely
 *   sequence-order based — completing the last stage of an arc unlocks
 *   the next arc in sequence_order for that doc).
 * - Removes rep_reward from quest_stages (rep rewards no longer exist).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the per-player rep score table
        Schema::dropIfExists('player_reputation');

        // Remove rep gating from arcs
        Schema::table('quest_arcs', function (Blueprint $table) {
            $table->dropColumn('rep_required');
        });

        // Remove rep reward from stages
        Schema::table('quest_stages', function (Blueprint $table) {
            $table->dropColumn('rep_reward');
        });
    }

    public function down(): void
    {
        Schema::create('player_reputation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUuid('cyber_doc_id')->constrained('cyber_docs')->cascadeOnDelete();
            $table->unsignedInteger('score')->default(0);
            $table->timestamps();
            $table->unique(['player_id', 'cyber_doc_id']);
        });

        Schema::table('quest_arcs', function (Blueprint $table) {
            $table->unsignedInteger('rep_required')->default(0)->after('is_entry_arc');
        });

        Schema::table('quest_stages', function (Blueprint $table) {
            $table->unsignedInteger('rep_reward')->default(0)->after('objective_text');
        });
    }
};
