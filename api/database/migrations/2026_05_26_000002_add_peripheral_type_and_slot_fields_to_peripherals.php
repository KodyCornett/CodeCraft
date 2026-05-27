<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peripherals', function (Blueprint $table) {
            // 'stat_boost'     — boosts a rig stat (existing peripherals)
            // 'command_module' — adds one typed loadout slot
            $table->string('peripheral_type')->default('stat_boost')->after('price_creds');

            // Only populated for command_module peripherals.
            // 'map'  — adds one map-context loadout slot
            // 'hack' — adds one hack-context loadout slot
            $table->string('slot_type')->nullable()->after('peripheral_type');

            // Only populated for command_module peripherals.
            // Tier 1 = max level 1 commands, Tier 2 = max level 2, Tier 3 = max level 3.
            $table->unsignedTinyInteger('slot_tier')->nullable()->after('slot_type');
        });
    }

    public function down(): void
    {
        Schema::table('peripherals', function (Blueprint $table) {
            $table->dropColumn(['peripheral_type', 'slot_type', 'slot_tier']);
        });
    }
};
