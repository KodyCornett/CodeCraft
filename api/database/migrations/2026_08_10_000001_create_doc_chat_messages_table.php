<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * doc_chat_messages
 *
 * Live text messages exchanged in a DOC's hub chat room. One room per CyberDoc,
 * keyed by the hub's node canvas_id (e.g. 'BA-hub' for Knuckle). Rooms are
 * isolated from each other — a message only ever belongs to one hub_canvas_id
 * and is only ever broadcast on that hub's doc-chat.{hubCanvasId} channel.
 *
 * Access (both read and write) requires the player be physically standing on
 * that hub's node right now — enforced server-side in DocChatService, not just
 * client-side. See routes/channels.php for the matching Reverb channel auth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('hub_canvas_id');   // which doc's room this belongs to, e.g. 'BA-hub'
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('handle');          // snapshot of the sender's handle at send time
            $table->string('body', 240);
            $table->timestamps();

            // Read-path index — history fetch filters by hub, orders by time
            $table->index(['hub_canvas_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_chat_messages');
    }
};
