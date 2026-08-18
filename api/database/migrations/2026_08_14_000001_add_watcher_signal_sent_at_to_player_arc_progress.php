<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks when the client has actually displayed the Watcher interrupt
 * cinematic tied to this arc's completion (see WATCHER_TRANSITIONS in
 * resources/js/constants/watcherTransitions.js). Distinct from
 * `completed_at` — an arc can be complete for a while before the player
 * physically leaves the doc's hub node and triggers the interrupt.
 * Nullable/unset means "not yet shown"; re-derived on every quest-log
 * load so a reload between arc completion and leaving the hub can't
 * drop the interrupt. See QuestService::markWatcherSignalSent().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_arc_progress', function (Blueprint $table) {
            $table->timestamp('watcher_signal_sent_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('player_arc_progress', function (Blueprint $table) {
            $table->dropColumn('watcher_signal_sent_at');
        });
    }
};
