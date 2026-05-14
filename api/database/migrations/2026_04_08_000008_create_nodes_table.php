<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained('districts')->cascadeOnDelete();
            $table->string('business_name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedTinyInteger('tier');
            $table->unsignedInteger('cred_value_base');
            $table->boolean('cred_resource_depleted')->default(false);
            $table->timestamp('cred_last_hacked_at')->nullable();
            $table->boolean('movement_resource_depleted')->default(false);
            $table->timestamp('movement_last_hacked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
