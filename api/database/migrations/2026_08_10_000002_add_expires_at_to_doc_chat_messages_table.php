<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a short TTL to doc_chat_messages, mirroring node_traces' expires_at
 * pattern. Rows are filtered out at read time once expired, and
 * DocChatService opportunistically deletes expired rows on every history
 * fetch — no scheduled job required, and the table never grows into a
 * permanent transcript of hub conversations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doc_chat_messages', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('body');
            $table->index(['hub_canvas_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('doc_chat_messages', function (Blueprint $table) {
            $table->dropIndex(['hub_canvas_id', 'expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
