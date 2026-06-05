<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds per-player disruption timer columns to packet_hijack_matches.
 *
 * Each column is a nullable timestamp following the established `{role}_{effect}_until`
 * convention (see: challenger_locked_until / defender_locked_until). NULL means the
 * effect is not active. The service layer resolves active state as: now() < until.
 *
 * Disruption effects added:
 *   crash          — freezes the target's terminal input for a timed window.
 *   dark_mode      — blinds the target's port board for a timed window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            $table->timestamp('challenger_crash_until')
                  ->nullable()
                  ->after('defender_mirror_active');

            $table->timestamp('defender_crash_until')
                  ->nullable()
                  ->after('challenger_crash_until');

            $table->timestamp('challenger_dark_mode_until')
                  ->nullable()
                  ->after('defender_crash_until');

            $table->timestamp('defender_dark_mode_until')
                  ->nullable()
                  ->after('challenger_dark_mode_until');
        });
    }

    public function down(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            $table->dropColumn([
                'challenger_crash_until',
                'defender_crash_until',
                'challenger_dark_mode_until',
                'defender_dark_mode_until',
            ]);
        });
    }
};
