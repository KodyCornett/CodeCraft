<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_arc_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUuid('quest_arc_id')->constrained('quest_arcs')->cascadeOnDelete();
            // locked   = rep threshold not yet reached
            // active   = unlocked, in progress
            // complete = all stages done
            $table->string('status')->default('locked');
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'quest_arc_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_arc_progress');
    }
};
