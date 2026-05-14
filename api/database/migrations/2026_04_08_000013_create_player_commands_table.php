<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_commands', function (Blueprint $table) {
            $table->foreignUuid('player_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('command_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(false);
            $table->primary(['player_id', 'command_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_commands');
    }
};
