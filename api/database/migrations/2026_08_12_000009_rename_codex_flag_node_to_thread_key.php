<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A completed quest stage now activates a codex thread (playable anywhere),
 * not a specific node. Renaming to match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_stages', function (Blueprint $table) {
            $table->renameColumn('codex_flag_node_canvas_id', 'codex_thread_key');
        });
    }

    public function down(): void
    {
        Schema::table('quest_stages', function (Blueprint $table) {
            $table->renameColumn('codex_thread_key', 'codex_flag_node_canvas_id');
        });
    }
};
