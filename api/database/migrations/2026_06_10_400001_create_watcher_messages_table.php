<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores authored Watcher signal messages.
 *
 * Each message is attached to a quest stage trigger — when that stage
 * completes, the message is queued for delivery to the player.
 *
 * trigger_stage_id — the quest stage whose completion fires this signal.
 *   Nullable so Watcher messages can also be seeded standalone (future use).
 *
 * signal_text — the raw encrypted signal body, exactly as it renders on screen.
 *   Supports {persona} and {persona_desc} tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watcher_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trigger_stage_id')->nullable()
                  ->constrained('quest_stages')->nullOnDelete();
            $table->text('signal_text');   // the full message body
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watcher_messages');
    }
};
