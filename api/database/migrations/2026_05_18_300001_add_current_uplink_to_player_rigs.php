<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_rigs', function (Blueprint $table) {
            // Persists remaining uplink across page reloads.
            // null = run not yet started (first move initialises from chassis base_uplink).
            // Reset to base_uplink on CyberDoc extract or Critical System Failure.
            $table->unsignedSmallInteger('current_uplink')->nullable()->after('current_ss');
        });
    }

    public function down(): void
    {
        Schema::table('player_rigs', function (Blueprint $table) {
            $table->dropColumn('current_uplink');
        });
    }
};
