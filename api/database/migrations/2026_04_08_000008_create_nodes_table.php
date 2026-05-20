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
            // Canvas identifier — matches ALL_NODES keys in HexMapCanvas.vue
            // e.g. 'DT-hub', 'NS-v3', 'wp_3_-7', 'northCorridor'
            $table->string('canvas_id', 64)->unique();
            // Pixel coordinates from the SVG canvas (HEX_SIZE=48, CX=600, CY=400)
            $table->decimal('x', 8, 2);
            $table->decimal('y', 8, 2);
            // 'action' = hackable node (pink dot), 'cyberdoc' = store hub (yellow dot)
            $table->enum('type', ['action', 'cyberdoc'])->default('action');
            // District name string — null for waypoints/junctions on NetLinks
            $table->string('district', 64)->nullable()->index();
            // ICE rating — minimum 3 (BlackHat v1.0 CPU floor). CyberDoc hubs store 0.
            $table->unsignedTinyInteger('ice')->default(3);
            $table->unsignedTinyInteger('tier')->default(1);
            $table->unsignedInteger('cred_value_base')->default(100);
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
