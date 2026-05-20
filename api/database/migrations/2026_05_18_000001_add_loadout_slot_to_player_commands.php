<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_commands', function (Blueprint $table) {
            // Slot position (1-based) set when a command is activated via setLoadout().
            // Used by enforceRamCap() to deactivate from the highest slot downward
            // when SS degradation drops effective RAM below active command count.
            $table->unsignedTinyInteger('loadout_slot')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('player_commands', function (Blueprint $table) {
            $table->dropColumn('loadout_slot');
        });
    }
};
