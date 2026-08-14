<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the Codex trigger field to quest_stages. Separate from the existing
 * `node_canvas_id` (which gates where the stage's own minigame/trigger
 * fires) — the node a completed stage flags for Codex investigation can be
 * a different node entirely, so it needs its own column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_stages', function (Blueprint $table) {
            $table->string('codex_flag_node_canvas_id')->nullable()->after('reward_lore_key');
        });
    }

    public function down(): void
    {
        Schema::table('quest_stages', function (Blueprint $table) {
            $table->dropColumn('codex_flag_node_canvas_id');
        });
    }
};
