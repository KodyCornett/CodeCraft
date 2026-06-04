<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_traps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('placer_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUuid('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->string('command_name');          // e.g. 'Crash', 'Packet Flood'
            $table->json('effect_data');             // level-specific payload from level_scaling
            $table->unsignedTinyInteger('placer_moves_left')->default(5); // decrements on placer move
            $table->boolean('consumed')->default(false);
            $table->timestamp('expires_at');         // time-based TTL (5 min default)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_traps');
    }
};
