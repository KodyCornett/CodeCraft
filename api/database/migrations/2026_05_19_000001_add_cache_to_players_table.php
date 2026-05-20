<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // Tracks how many nodes the player has hacked this run without banking.
            // Fills by 1 per successful hack; locked when full (max = effective CPU + RAM).
            // Reset to 0 at CyberDoc bank and on critical failure.
            $table->unsignedSmallInteger('cache')->default(0)->after('pocket_creds');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('cache');
        });
    }
};
