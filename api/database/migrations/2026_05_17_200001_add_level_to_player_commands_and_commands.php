<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds command levelling to the system.
 *
 * player_commands
 *   level            — current upgrade level (1–max_level). Starts at 1 on purchase.
 *
 * commands
 *   max_level        — ceiling for this command's upgrade path.
 *                      T1/T2 = 3, T3/T4 = 4, T5 = 5.
 *   upgrade_cost_tp  — base Tech Points to go from level N → N+1.
 *                      T1 = 2, T2 = 3, T3 = 5, T4 = 8, T5 = 12.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_commands', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->after('is_active');
        });

        Schema::table('commands', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_level')->default(5)->after('tier');
            $table->unsignedSmallInteger('upgrade_cost_tp')->default(2)->after('max_level');
        });

        // Populate max_level and upgrade_cost_tp based on tier
        $tiers = [
            1 => ['max_level' => 3, 'upgrade_cost_tp' => 2],
            2 => ['max_level' => 3, 'upgrade_cost_tp' => 3],
            3 => ['max_level' => 4, 'upgrade_cost_tp' => 5],
            4 => ['max_level' => 4, 'upgrade_cost_tp' => 8],
            5 => ['max_level' => 5, 'upgrade_cost_tp' => 12],
        ];

        foreach ($tiers as $tier => $values) {
            DB::table('commands')
                ->where('tier', $tier)
                ->update($values);
        }
    }

    public function down(): void
    {
        Schema::table('player_commands', function (Blueprint $table) {
            $table->dropColumn('level');
        });

        Schema::table('commands', function (Blueprint $table) {
            $table->dropColumn(['max_level', 'upgrade_cost_tp']);
        });
    }
};
