<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('category', ['software', 'repair']);
            // stat targeted by this consumable ('ss' for repair kits)
            $table->enum('stat', ['cpu', 'ram', 'os', 'firewall', 'storage', 'ss']);
            $table->unsignedSmallInteger('boost_amount');
            // null = instant (repair); >0 = move-counted session effect (software)
            $table->unsignedTinyInteger('duration_moves')->nullable();
            $table->enum('rarity', ['common', 'uncommon', 'rare']);
            $table->unsignedInteger('price_creds');
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumables');
    }
};
