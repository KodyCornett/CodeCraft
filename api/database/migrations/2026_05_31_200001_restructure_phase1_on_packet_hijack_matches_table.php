<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            // New Phase 1 suspect objects — replaces the old random IP pool.
            // Schema per entry:
            //   ip, latency_ms, latency_status, hops, network_range,
            //   last_seen_seconds, whois_class, whois_redacted, is_target, flushed
            $table->json('challenger_suspects')->nullable()->after('challenger_ip_pool');
            $table->json('defender_suspects')->nullable()->after('defender_ip_pool');

            // Make old pool columns nullable so existing rows don't break.
            $table->json('challenger_ip_pool')->nullable()->change();
            $table->json('defender_ip_pool')->nullable()->change();

            // active_decoys superseded by per-suspect attributes — drop them.
            $table->dropColumn(['challenger_active_decoys', 'defender_active_decoys']);
        });
    }

    public function down(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            $table->dropColumn(['challenger_suspects', 'defender_suspects']);
            $table->json('challenger_active_decoys')->nullable()->after('challenger_ip_pool');
            $table->json('defender_active_decoys')->nullable()->after('defender_ip_pool');
        });
    }
};
