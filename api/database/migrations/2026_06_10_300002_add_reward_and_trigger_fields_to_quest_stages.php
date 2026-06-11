<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds reward fields and quest trigger fields to quest_stages.
 *
 * Reward fields — all nullable, only set for stages that grant that reward type:
 *   reward_creds        — wallet_creds granted on completion
 *   reward_tech_points  — tech_points granted on completion
 *   reward_item_id      — FK to consumables, grants one consumable item
 *   reward_command_id   — FK to commands, grants command ownership
 *   reward_node_access  — canvas_id of a node unlocked by this stage
 *   reward_lore_key     — string key unlocking a Splice page or archive entry
 *
 * Trigger fields:
 *   node_canvas_id  — the map node the player must be at to trigger this stage
 *                     (null = no map requirement, e.g. terminal choices or visits)
 *   minigame_type   — the hacking minigame that fires for this stage
 *                     (null = no minigame; 'data_grab' | 'system_override' | etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_stages', function (Blueprint $table) {
            // ── Rewards ────────────────────────────────────────────────────────
            $table->unsignedInteger('reward_creds')->default(0)->after('rep_reward');
            $table->decimal('reward_tech_points', 8, 2)->default(0)->after('reward_creds');
            $table->foreignUuid('reward_item_id')->nullable()->after('reward_tech_points')
                  ->constrained('consumables')->nullOnDelete();
            $table->foreignUuid('reward_command_id')->nullable()->after('reward_item_id')
                  ->constrained('commands')->nullOnDelete();
            $table->string('reward_node_access')->nullable()->after('reward_command_id'); // canvas_id string
            $table->string('reward_lore_key')->nullable()->after('reward_node_access');

            // ── Map & minigame triggers ────────────────────────────────────────
            $table->string('node_canvas_id')->nullable()->after('reward_lore_key');
            $table->string('minigame_type')->nullable()->after('node_canvas_id');
        });
    }

    public function down(): void
    {
        Schema::table('quest_stages', function (Blueprint $table) {
            $table->dropForeign(['reward_item_id']);
            $table->dropForeign(['reward_command_id']);
            $table->dropColumn([
                'reward_creds', 'reward_tech_points',
                'reward_item_id', 'reward_command_id',
                'reward_node_access', 'reward_lore_key',
                'node_canvas_id', 'minigame_type',
            ]);
        });
    }
};
