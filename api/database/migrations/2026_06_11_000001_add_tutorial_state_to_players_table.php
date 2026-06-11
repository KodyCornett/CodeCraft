<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves tutorial progress from client localStorage to the players table.
 *
 * tutorial_state — JSON bag mirroring the shape useTutorial.js previously
 * stored in localStorage:
 *   {
 *     tutorialSeen:    bool,
 *     tutorialSkipped: bool,
 *     stepsDone:       { [stepId]: true },
 *     questsRewarded:  string[],
 *     hasBadge:        bool
 *   }
 *
 * null = fresh player, no tutorial interaction yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->json('tutorial_state')->nullable()->after('cyberdoc_cooldowns');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('tutorial_state');
        });
    }
};
