<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_connections', function (Blueprint $table) {
            $table->foreignUuid('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->foreignUuid('connected_node_id')->constrained('nodes')->cascadeOnDelete();
            $table->primary(['node_id', 'connected_node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_connections');
    }
};
