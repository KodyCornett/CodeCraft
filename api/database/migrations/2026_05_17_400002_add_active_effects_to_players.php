<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * active_effects — move-counted command durations stored server-side.
 *
 * Format: { "ghost_protocol": 3, "dark_mode": 2 }
 *   key   — snake_case of the command name
 *   value — moves remaining before the effect expires
 *
 * Written by PlayerController::activateCommand when a player fires
 * a self-targeted command. Decremented by PlayerController::position
 * on each move. Checked by NodeController::deplete to suppress traces
 * (Ghost Protocol) and other server-authoritative effects.
 *
 * Null when no effects are active — avoids a stale empty object in the DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->json('active_effects')->nullable()->after('post_combat_silent_moves');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('active_effects');
        });
    }
};
