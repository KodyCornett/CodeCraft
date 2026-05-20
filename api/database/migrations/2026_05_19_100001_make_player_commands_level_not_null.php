<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce NOT NULL on player_commands.level.
 *
 * The original migration added level with a DEFAULT of 1, but without an
 * explicit NOT NULL constraint. A direct DB insert that omits the column
 * would therefore store NULL, silently bypassing the CPU-cap enforcement
 * in RigService::enforceCpuCommandCap() (WHERE level > $maxLevel evaluates
 * to NULL for any row where level IS NULL).
 *
 * Steps:
 *  1. Back-fill any existing NULL rows to 1 (safe — 1 is the starting level).
 *  2. Alter the column to NOT NULL DEFAULT 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Back-fill so no existing row violates the incoming constraint.
        DB::table('player_commands')
            ->whereNull('level')
            ->update(['level' => 1]);

        // 2. Tighten the column definition — NOT NULL is now enforced by the DB.
        Schema::table('player_commands', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('player_commands', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->nullable()->change();
        });
    }
};
