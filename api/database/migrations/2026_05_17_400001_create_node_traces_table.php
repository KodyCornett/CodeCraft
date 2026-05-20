<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * node_traces
 *
 * Data fragments left behind when a player hacks a node. Other players who
 * view the node info see the hacker's handle and a 5-minute countdown — the
 * older the fragment the more likely the runner has moved on.
 *
 * Re-hacking the same node by the same player updates expires_at in place
 * (unique on node_id + player_id), so the timer resets rather than stacking.
 *
 * Expired rows are filtered out at read time. A future scheduled cleanup
 * can prune rows where expires_at < now() − 1 day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_traces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            // One active trace per (node, player) — re-hacks update in place
            $table->unique(['node_id', 'player_id']);

            // Read-path index — traces endpoint filters by node + expires_at
            $table->index(['node_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_traces');
    }
};
