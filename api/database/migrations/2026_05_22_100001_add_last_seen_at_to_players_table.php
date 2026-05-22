<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // Touched by POST /api/player/heartbeat and POST /api/player/position.
            // Used by NodeController::players() to exclude ghost players whose
            // session ended without a clean logout (tab close, crash, etc.).
            // NULL means the player has never sent a heartbeat (pre-feature accounts).
            $table->timestamp('last_seen_at')->nullable()->after('current_district');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
