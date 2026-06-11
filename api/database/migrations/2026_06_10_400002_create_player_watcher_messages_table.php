<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks delivery and read state of Watcher signals per player.
 *
 * delivered_at — when the message was queued for this player.
 * read_at      — null until the player opens the Watcher channel page.
 *   Used to drive the unread glitch indicator on the TERMINAL nav button.
 */
return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('player_watcher_messages');
    }
};
