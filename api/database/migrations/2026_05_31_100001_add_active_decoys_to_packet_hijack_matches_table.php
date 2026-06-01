<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            // IPs from the pool that also return ACTIVE TRAFFIC on trafficTest,
            // seeded at match creation from the opponent's OS stat.
            $table->json('challenger_active_decoys')->nullable()->after('challenger_ip_pool');
            $table->json('defender_active_decoys')->nullable()->after('defender_ip_pool');
        });
    }

    public function down(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            $table->dropColumn(['challenger_active_decoys', 'defender_active_decoys']);
        });
    }
};
