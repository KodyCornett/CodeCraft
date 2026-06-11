<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_reputation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUuid('cyber_doc_id')->constrained('cyber_docs')->cascadeOnDelete();
            $table->unsignedInteger('score')->default(0);
            $table->timestamps();

            $table->unique(['player_id', 'cyber_doc_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_reputation');
    }
};
