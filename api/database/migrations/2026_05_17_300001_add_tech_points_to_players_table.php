<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds tech_points to the players table.
 *
 * tech_points — accumulated by hacking TECH nodes via GridBreach.
 * Unlike pocket_creds they are permanent (never lost to PvP) and are
 * spent on rig stat upgrades and command upgrades at the Street Doc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedInteger('tech_points')->default(0)->after('wallet_creds');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('tech_points');
        });
    }
};
