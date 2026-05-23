<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds npc_handle to nodes.
     *
     * Only populated on CyberDoc hub nodes — these are the quest-giver NPCs.
     * Action nodes leave this null.
     *
     * Examples:
     *   NS-hub → PATCH
     *   DT-hub → VEIL
     */
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('npc_handle', 32)->nullable()->after('is_safe_zone');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('npc_handle');
        });
    }
};
