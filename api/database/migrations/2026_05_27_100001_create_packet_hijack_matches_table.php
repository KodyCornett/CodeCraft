<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packet_hijack_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Participants — FK to players
            $table->foreignUuid('challenger_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUuid('defender_id')->constrained('players')->cascadeOnDelete();

            // Match lifecycle
            $table->enum('status', ['pending', 'phase1', 'phase2', 'complete'])->default('pending');
            $table->foreignUuid('winner_id')->nullable()->constrained('players')->nullOnDelete();

            // Each player's generated target IP (the real IP the opponent must find)
            // challenger_target_ip = the IP the challenger must locate (defender's rig)
            // defender_target_ip   = the IP the defender must locate (challenger's rig)
            $table->string('challenger_target_ip', 20);
            $table->string('defender_target_ip', 20);

            // Full shuffled IP pools (50+ decoys + 1 real each)
            $table->json('challenger_ip_pool');
            $table->json('defender_ip_pool');

            // Port topology for each player's Phase 2
            // Schema: [{ port: 80, service: 'HTTP', bias: 12, shattered: false }, ...]
            $table->json('challenger_ports');
            $table->json('defender_ports');

            // Which phase each player is currently in (1 or 2)
            $table->tinyInteger('challenger_phase')->default(1)->unsigned();
            $table->tinyInteger('defender_phase')->default(1)->unsigned();

            // Honeypot penalty: input locked until this timestamp (nullable = not locked)
            $table->timestamp('challenger_locked_until')->nullable();
            $table->timestamp('defender_locked_until')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packet_hijack_matches');
    }
};
