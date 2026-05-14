<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_rigs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('player_id');
            $table->uuid('chassis_template_id');
            $table->unsignedSmallInteger('cpu_level');
            $table->unsignedSmallInteger('ram_level');
            $table->unsignedSmallInteger('firewall_level');
            $table->unsignedSmallInteger('storage_level');
            $table->unsignedSmallInteger('os_level');
            $table->unsignedSmallInteger('current_ss')->default(100);
            $table->boolean('is_limping')->default(false);
            $table->timestamps();

            $table->foreign('chassis_template_id')
                  ->references('id')
                  ->on('chassis_templates')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_rigs');
    }
};
