<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite does not support ALTER TABLE ADD CONSTRAINT, so the only way to add
 * a foreign key to an existing column is to drop and recreate the table.
 * This migration drops the original player_rigs table and recreates it with
 * a proper FK on player_id → players.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::drop('player_rigs');

        Schema::create('player_rigs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUuid('chassis_template_id')->constrained('chassis_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('cpu_level');
            $table->unsignedSmallInteger('ram_level');
            $table->unsignedSmallInteger('firewall_level');
            $table->unsignedSmallInteger('storage_level');
            $table->unsignedSmallInteger('os_level');
            $table->unsignedSmallInteger('current_ss')->default(100);
            $table->boolean('is_limping')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('player_rigs');

        Schema::create('player_rigs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('player_id');
            $table->foreignUuid('chassis_template_id')->constrained('chassis_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('cpu_level');
            $table->unsignedSmallInteger('ram_level');
            $table->unsignedSmallInteger('firewall_level');
            $table->unsignedSmallInteger('storage_level');
            $table->unsignedSmallInteger('os_level');
            $table->unsignedSmallInteger('current_ss')->default(100);
            $table->boolean('is_limping')->default(false);
            $table->timestamps();
        });
    }
};
