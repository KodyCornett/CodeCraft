<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make stat_boosted nullable on peripherals.
 *
 * Command module peripherals (Nav Wraith, ICE Pick) add loadout slots rather
 * than boosting a rig stat, so they have no stat_boosted value.
 * The column must accept NULL for those rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peripherals', function (Blueprint $table) {
            $table->string('stat_boosted')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('peripherals', function (Blueprint $table) {
            $table->string('stat_boosted')->nullable(false)->change();
        });
    }
};
