<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chassis_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedSmallInteger('base_cpu');
            $table->unsignedSmallInteger('base_ram');
            $table->unsignedSmallInteger('base_firewall');
            $table->unsignedSmallInteger('base_storage');
            $table->string('base_os');
            $table->unsignedTinyInteger('peripheral_slots');
            $table->unsignedSmallInteger('total_point_cap');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chassis_templates');
    }
};
