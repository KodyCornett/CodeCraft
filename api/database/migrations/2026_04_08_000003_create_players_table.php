<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('handle')->unique();
            $table->uuid('current_node_id')->nullable();
            $table->string('current_district')->nullable();
            $table->unsignedInteger('bounty_level')->default(0);
            $table->unsignedInteger('nodes_hacked_this_run')->default(0);
            $table->boolean('is_open_season')->default(false);
            $table->unsignedInteger('open_season_wins')->default(0);
            $table->boolean('is_limping')->default(false);
            $table->unsignedTinyInteger('post_combat_silent_moves')->default(0);
            $table->uuid('last_street_doc_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
