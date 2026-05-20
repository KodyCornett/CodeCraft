<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combat_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('challenger_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUuid('target_id')->constrained('players')->cascadeOnDelete();
            $table->string('node_canvas_id');   // canvas ID of the node where combat occurs
            $table->enum('status', ['pending', 'accepted', 'declined', 'resolved', 'expired'])
                  ->default('pending');
            $table->timestamp('expires_at');    // auto-expire if target doesn't respond
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combat_challenges');
    }
};
