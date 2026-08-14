<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * player_splice_unlocks
 *
 * Per-player state for every splice_pages row a player has reached — the
 * Decrypter app's History list reads straight off this table. A row is
 * created the moment a page is reached, either by resolving a key (any
 * type) or by following a lead link from an already-unlocked codex page
 * (flavor pages only — leads never point at another codex page).
 *
 * status:
 *   - 'completed'  — flavor pages land here immediately, nothing to solve.
 *                    Codex pages land here once their login is solved.
 *   - 'unresolved' — codex pages only, until solved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_splice_unlocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUuid('splice_page_id')->constrained('splice_pages')->cascadeOnDelete();
            $table->string('status');  // unresolved | completed
            $table->timestamp('unlocked_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'splice_page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_splice_unlocks');
    }
};
