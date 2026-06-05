<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds decoy support to node_traces.
 *
 * is_decoy    — true when this trace was placed by a Decoy command, not a real hack.
 * fake_handle — the spoofed handle shown to other players inspecting the node.
 *               Null for real traces.
 *
 * The placer's player_id is still stored so the trace can be cleaned up when
 * the player disconnects (cascadeOnDelete) and so placeDecoy() can use
 * updateOrCreate to prevent a player stacking multiple decoys on the same node.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('node_traces', function (Blueprint $table) {
            $table->boolean('is_decoy')->default(false)->after('expires_at');
            $table->string('fake_handle')->nullable()->after('is_decoy');
        });
    }

    public function down(): void
    {
        Schema::table('node_traces', function (Blueprint $table) {
            $table->dropColumn(['is_decoy', 'fake_handle']);
        });
    }
};
