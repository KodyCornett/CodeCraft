<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Watcher archive system (WatcherMessage / PlayerWatcherMessage) was
 * scaffolded ahead of content that never arrived — no seeder ever populated
 * watcher_messages, so deliverForStage() always no-op'd and the archive
 * page (splice://watcher) was permanently empty for every player. The live
 * Watcher interrupt cinematics (prologue + tutorial) are pushed directly
 * from the client via useWatcher.js's triggerSignal()/activeSignal queue,
 * which doesn't touch these tables. Dropping both rather than carry
 * dead infrastructure forward. See WATCHER_TRANSITIONS in
 * resources/js/constants/watcherTransitions.js for the live mechanism.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('player_watcher_messages');
        Schema::dropIfExists('watcher_messages');
    }

    public function down(): void
    {
        Schema::create('watcher_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trigger_stage_id')->nullable()
                  ->constrained('quest_stages')->nullOnDelete();
            $table->text('signal_text');
            $table->timestamps();
        });

        Schema::create('player_watcher_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')
                  ->constrained('players')->cascadeOnDelete();
            $table->foreignUuid('watcher_message_id')
                  ->constrained('watcher_messages')->cascadeOnDelete();
            $table->timestamp('delivered_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'watcher_message_id']);
        });
    }
};
