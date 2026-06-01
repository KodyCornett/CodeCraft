<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            // Phase 2 — system fingerprint credential objects.
            // Schema: {
            //   hostname: { tier1, tier2, tier3, assembled, fragments: [{value, port, found}] },
            //   os:       { tier1, tier2, tier3, assembled, fragments: [{value, port, found}] },
            //   ports: [{ port, service, version, exposure, fragments_hidden: [], shattered, probed }],
            //   exploit_port: int,   — the designated exfil vector port
            //   current_path: string — Phase 3 filesystem cursor
            // }
            $table->json('challenger_fingerprint')->nullable()->after('challenger_ports');
            $table->json('defender_fingerprint')->nullable()->after('defender_ports');

            // Phase 3 — filesystem directory tree with wallet location.
            // Schema: { tree: {...}, wallet_path: string, current_path: string }
            $table->json('challenger_filesystem')->nullable()->after('challenger_fingerprint');
            $table->json('defender_filesystem')->nullable()->after('defender_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            $table->dropColumn([
                'challenger_fingerprint',
                'defender_fingerprint',
                'challenger_filesystem',
                'defender_filesystem',
            ]);
        });
    }
};
