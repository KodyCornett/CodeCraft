<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — Rig-Command State for Packet Hijack matches.
 *
 * Adds per-role columns needed to track:
 *   overclock_active   — exploit threshold raised to 45% for next exploit
 *   mirror_active      — next opponent rig command is reflected
 *   corrupt_ports      — ports with fake bias values (sector_corrupt, expires after 10s)
 *   bait_ports         — ports baited to lock the attacker on exploit (bait command)
 *   used_commands      — slug list of already-deployed commands (one-use-per-match guard)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            $table->boolean('challenger_overclock_active')->default(false)->after('defender_locked_until');
            $table->boolean('defender_overclock_active')->default(false)->after('challenger_overclock_active');
            $table->boolean('challenger_mirror_active')->default(false)->after('defender_overclock_active');
            $table->boolean('defender_mirror_active')->default(false)->after('challenger_mirror_active');
            $table->json('challenger_corrupt_ports')->nullable()->after('defender_mirror_active');
            $table->json('defender_corrupt_ports')->nullable()->after('challenger_corrupt_ports');
            $table->json('challenger_bait_ports')->nullable()->after('defender_corrupt_ports');
            $table->json('defender_bait_ports')->nullable()->after('challenger_bait_ports');
            $table->json('challenger_used_commands')->nullable()->after('defender_bait_ports');
            $table->json('defender_used_commands')->nullable()->after('challenger_used_commands');
        });
    }

    public function down(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            $table->dropColumn([
                'challenger_overclock_active',
                'defender_overclock_active',
                'challenger_mirror_active',
                'defender_mirror_active',
                'challenger_corrupt_ports',
                'defender_corrupt_ports',
                'challenger_bait_ports',
                'defender_bait_ports',
                'challenger_used_commands',
                'defender_used_commands',
            ]);
        });
    }
};
