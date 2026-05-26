<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Redesigns the commands table for the map / hack split.
 *
 * Added:
 *   context             — 'map' | 'hack'  which mini-game the command belongs to
 *   gridbreach_effect   — GridBreach-specific effect description (hack commands only)
 *   packethijack_effect — Packet Hijack-specific effect description (hack commands only)
 *   level_scaling       — JSON key numeric values per level (L1 / L2 / L3)
 *
 * Removed:
 *   tier        — gating now driven by chassis / peripheral system, not player RAM tier
 *   hack_effect — replaced by gridbreach_effect + packethijack_effect
 *
 * max_level is reset to 3 for all commands:
 *   L1 = base effect, L2 = enhanced, L3 = L2 effect + second use.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Add new columns ───────────────────────────────────────────────────
        Schema::table('commands', function (Blueprint $table) {
            $table->enum('context', ['map', 'hack'])->default('map')->after('name');
            $table->text('gridbreach_effect')->nullable()->after('map_effect');
            $table->text('packethijack_effect')->nullable()->after('gridbreach_effect');
            $table->json('level_scaling')->nullable()->after('duration');
        });

        // ── Drop obsolete columns ─────────────────────────────────────────────
        Schema::table('commands', function (Blueprint $table) {
            $table->dropColumn(['tier', 'hack_effect']);
        });

        // ── Reset max_level to 3 across the board ─────────────────────────────
        DB::table('commands')->update(['max_level' => 3]);
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropColumn(['context', 'gridbreach_effect', 'packethijack_effect', 'level_scaling']);
        });

        Schema::table('commands', function (Blueprint $table) {
            $table->unsignedTinyInteger('tier')->default(1)->after('name');
            $table->text('hack_effect')->nullable()->after('map_effect');
        });
    }
};
