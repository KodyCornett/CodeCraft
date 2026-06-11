<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quest_arc_id')->constrained('quest_arcs')->cascadeOnDelete();
            $table->unsignedTinyInteger('stage_number');
            $table->string('title');
            $table->text('objective_text');
            $table->unsignedInteger('rep_reward')->default(0);  // rep granted to owning doc on completion

            // Branching — if is_branch, player picks which doc to turn the job into.
            // branch_options: [{"cyber_doc_id": "...", "label": "Turn job into Knuckle", "rep_reward": 200}, ...]
            $table->boolean('is_branch')->default(false);
            $table->json('branch_options')->nullable();

            // Referral — if set, completing this stage introduces the player to another doc.
            // The referral entry appears in that doc's quest log section immediately.
            $table->foreignUuid('referral_doc_id')->nullable()->constrained('cyber_docs')->nullOnDelete();
            $table->string('referral_text')->nullable(); // e.g. "Knuckle says Ghost wants a word."

            $table->timestamps();

            $table->index(['quest_arc_id', 'stage_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_stages');
    }
};
