<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * player_document_keys
 *
 * One row per Archive Extraction win at a Codex-flagged node. Winning always
 * grants a key — the reward for the minigame itself is just the key; whether
 * it actually leads anywhere is decided at resolve time, not at win time.
 *
 * status:
 *   - 'unresolved' — earned, not yet taken to the Decrypter.
 *   - 'empty'      — resolved, matched nothing. The source node stays
 *                    Codex-flagged (see player_node_codex_flags) so the
 *                    player can just try Archive Extraction again.
 *   - 'resolved'   — resolved to a real splice_pages row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_document_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('source_node_canvas_id');
            $table->string('status')->default('unresolved');  // unresolved | empty | resolved
            $table->foreignUuid('resolved_splice_page_id')->nullable()
                ->constrained('splice_pages')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_document_keys');
    }
};
