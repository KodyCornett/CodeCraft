<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_stage_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUuid('quest_stage_id')->constrained('quest_stages')->cascadeOnDelete();
            // locked   = arc active but this stage not yet reached
            // active   = current objective
            // complete = done
            $table->string('status')->default('locked');
            // For branch stages — which doc the player turned the job into
            $table->foreignUuid('turned_into_doc_id')->nullable()->constrained('cyber_docs')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'quest_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_stage_progress');
    }
};
