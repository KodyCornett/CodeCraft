<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedInteger('pvp_wins_this_run')->default(0)->after('nodes_hacked_this_run');
            $table->decimal('bounty_multiplier', 4, 2)->default(1.00)->after('pvp_wins_this_run');
            $table->string('bounty_district_snapshot')->nullable()->after('bounty_multiplier');
            $table->unsignedInteger('open_season_current_wins')->default(0)->after('open_season_wins');
            $table->unsignedInteger('open_season_best_wins')->default(0)->after('open_season_current_wins');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn([
                'pvp_wins_this_run',
                'bounty_multiplier',
                'bounty_district_snapshot',
                'open_season_current_wins',
                'open_season_best_wins',
            ]);
        });
    }
};
