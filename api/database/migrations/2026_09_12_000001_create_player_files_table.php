<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-player virtual file system backing the File Explorer OS program.
 *
 * A self-referencing tree: parent_id is null for a player's root folder,
 * otherwise points at the containing folder's row. type is 'folder' or
 * 'file' — folders never carry content/extension, files never have children.
 *
 * content is plain text for now (read-only viewer, Phase 1 — see
 * CONTRACTS_AND_OS_REWORK_PLAN.md). Kept generic enough that a later mission
 * can insert a file into a specific player's tree without a schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('player_files')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['folder', 'file']);
            $table->string('extension')->nullable();
            $table->longText('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['player_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_files');
    }
};
