<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peripherals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('stat_boosted', ['os', 'ram', 'cpu', 'storage', 'firewall']);
            $table->unsignedSmallInteger('boost_amount');
            $table->enum('rarity', ['common', 'uncommon', 'rare', 'legendary']);
            $table->unsignedTinyInteger('port_cost')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peripherals');
    }
};
