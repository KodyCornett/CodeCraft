<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reworks Archive Extraction from "available at one flagged node" to
 * "available anywhere, gated by an active codex thread." Drops the
 * node-specific flag table entirely and replaces it with a per-thread
 * activation table — a row means "this thread is actively being hunted for
 * this player," with no node tie-in at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('player_node_codex_flags');

        Schema::create('player_codex_activations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('thread_key'); // matches splice_pages.thread_key
            $table->foreignUuid('source_quest_stage_id')->nullable()
                ->constrained('quest_stages')->nullOnDelete();
            $table->timestamps();

            $table->unique(['player_id', 'thread_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_codex_activations');

        Schema::create('player_node_codex_flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('node_canvas_id');
            $table->foreignUuid('source_quest_stage_id')->nullable()
                ->constrained('quest_stages')->nullOnDelete();
            $table->timestamps();

            $table->unique(['player_id', 'node_canvas_id']);
        });
    }
};
