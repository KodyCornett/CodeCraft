<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The street_doc_catalog table is the single source of truth for which items
     * are available at which CyberDoc terminal.
     *
     * Design notes:
     *
     *   street_doc_id  — NULL means the item is global (every doc carries it).
     *                    A UUID ties the row to one specific terminal.
     *
     *   item_type      — discriminator for the source table:
     *                      'peripheral'  → peripherals
     *                      'consumable'  → consumables
     *                      'command'     → commands
     *                      'chassis'     → chassis_templates
     *
     *   item_id        — UUID of the item in its source table. No FK constraint
     *                    because it's polymorphic across four tables.
     *
     *   is_exclusive   — When true, the item is only available at this specific doc
     *                    and will never bleed into global results. Ignored for global
     *                    rows (street_doc_id IS NULL).
     *
     *   stock_limit    — NULL = unlimited. A positive integer caps how many times
     *                    this item can be purchased across all players at this doc
     *                    before it disappears. Stock depletion tracking is handled
     *                    at the application layer via StreetDocInventoryService.
     *
     *   source         — Audit trail: where did this row come from?
     *                    Examples: 'seed', 'mission:vault_run_01', 'event:halloween_2026',
     *                    'rotation:2026-06-04', 'admin'
     *                    Allows targeted removal: WHERE source = 'mission:X' → delete.
     *
     *   available_until — NULL = permanent. Timestamp-gated specials auto-expire when
     *                     the catalog endpoint filters them out.
     */
    public function up(): void
    {
        Schema::create('street_doc_catalog', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable FK — null = global item available at all docs
            $table->foreignUuid('street_doc_id')
                  ->nullable()
                  ->constrained('street_docs')
                  ->cascadeOnDelete();

            $table->enum('item_type', ['peripheral', 'consumable', 'command', 'chassis']);
            $table->uuid('item_id'); // polymorphic — no FK constraint

            $table->boolean('is_exclusive')->default(false);
            $table->unsignedInteger('stock_limit')->nullable();
            $table->string('source', 128)->default('seed');
            $table->timestamp('available_until')->nullable();

            $table->timestamps();

            // One entry per (doc, type, item) combination.
            // SQLite treats NULL as distinct for unique indexes, so two global rows
            // for the same item are technically possible — the service uses
            // updateOrCreate to keep this clean.
            $table->index(['street_doc_id', 'item_type']);
            $table->index(['item_type', 'item_id']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('street_doc_catalog');
    }
};
