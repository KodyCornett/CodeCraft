<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archive Extraction is no longer tied to a specific node, so a key no
 * longer has a meaningful "source node" — eligibility at resolve time is
 * computed from the player's active codex threads instead (see
 * player_codex_activations), not from where the key was earned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_document_keys', function (Blueprint $table) {
            $table->dropColumn('source_node_canvas_id');
        });
    }

    public function down(): void
    {
        Schema::table('player_document_keys', function (Blueprint $table) {
            $table->string('source_node_canvas_id')->nullable()->after('player_id');
        });
    }
};
