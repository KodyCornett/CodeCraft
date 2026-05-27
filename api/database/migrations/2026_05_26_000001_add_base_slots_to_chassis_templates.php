<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chassis_templates', function (Blueprint $table) {
            // Typed base loadout slots built into the chassis.
            // Map slots accept map-context commands only.
            // Hack slots accept hack-context commands only.
            // Open slots accept either context.
            $table->unsignedTinyInteger('base_map_slots')->default(0)->after('peripheral_slots');
            $table->unsignedTinyInteger('base_hack_slots')->default(0)->after('base_map_slots');
            $table->unsignedTinyInteger('base_open_slots')->default(0)->after('base_hack_slots');
        });
    }

    public function down(): void
    {
        Schema::table('chassis_templates', function (Blueprint $table) {
            $table->dropColumn(['base_map_slots', 'base_hack_slots', 'base_open_slots']);
        });
    }
};
