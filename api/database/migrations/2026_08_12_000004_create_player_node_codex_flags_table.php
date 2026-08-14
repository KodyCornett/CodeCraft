<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * player_node_codex_flags
 *
 * Tracks which nodes currently offer Archive Extraction as a Codex
 * investigation action for a given player — separate entirely from normal
 * node hacking (no depletion interaction, no resource reward). A row's mere
 * existence means "available"; per design this never gets removed once
 * granted, so a player can always retry a node that resolved to nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('player_node_codex_flags');
    }
};
