<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the fields needed for the rig progression system:
 *
 *  tier         — chassis series (1 = v1.x BlackHat, 2 = v2.x NullTek, etc.)
 *  base_os_level — numeric starting OS level (previously only stored as string name)
 *  cap_cpu/ram/firewall/storage/cap_os — per-stat effective ceiling
 *                 (base + max investable points = effective cap)
 *
 * With base_os_level added, effectiveStats() can treat OS the same as other stats:
 *   os_level = invested points (starts at 0), effective_os = base_os_level + os_level
 *
 * PlayerSeeder is updated separately to seed os_level=0 (not the base value).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chassis_templates', function (Blueprint $table) {
            $table->unsignedTinyInteger('tier')->default(1)->after('name');
            $table->unsignedTinyInteger('base_os_level')->default(2)->after('base_os');
            $table->unsignedSmallInteger('cap_cpu')->default(5)->after('base_storage');
            $table->unsignedSmallInteger('cap_ram')->default(4)->after('cap_cpu');
            $table->unsignedSmallInteger('cap_firewall')->default(3)->after('cap_ram');
            $table->unsignedSmallInteger('cap_storage')->default(4)->after('cap_firewall');
            $table->unsignedSmallInteger('cap_os')->default(4)->after('cap_storage');
        });
    }

    public function down(): void
    {
        Schema::table('chassis_templates', function (Blueprint $table) {
            $table->dropColumn([
                'tier', 'base_os_level',
                'cap_cpu', 'cap_ram', 'cap_firewall', 'cap_storage', 'cap_os',
            ]);
        });
    }
};
