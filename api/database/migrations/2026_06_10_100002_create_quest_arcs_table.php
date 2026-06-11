<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_arcs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cyber_doc_id')->constrained('cyber_docs')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence_order');  // order within this doc's arcs
            $table->string('title');
            $table->unsignedInteger('rep_required')->default(0); // rep threshold to unlock this arc
            $table->boolean('is_entry_arc')->default(false);     // true for Knuckle arc 1 (auto-unlocked)
            $table->timestamps();

            $table->index(['cyber_doc_id', 'sequence_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_arcs');
    }
};
