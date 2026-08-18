<?php

use App\Models\Node;
use App\Support\SpliceAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a persisted splice_address column to nodes.
 *
 * Previously the SPLICE address (format ZONE.HASH, e.g. 14.A3F9) was
 * computed client-side on the fly from the node's UUID — no backend work
 * needed, but nobody could look up or hand-set a node's real address
 * without running the hash themselves. This promotes it to a real column
 * so quest content can reference a specific node's address (see the
 * Mission Log "LEAD:" line) and so a writer can override a specific
 * story-important node's address by hand instead of always taking
 * whatever the hash produces.
 *
 * The backfill below runs SpliceAddress::generate() — a bit-for-bit PHP
 * port of the frontend's existing hash, verified against the JS
 * implementation on 500+ random UUIDs — so every node that already has a
 * visible address today keeps that exact same address after this ships.
 * New nodes get one auto-generated on creation via Node::booted().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('splice_address', 16)->nullable()->after('canvas_id');
        });

        foreach (Node::query()->whereNull('splice_address')->cursor() as $node) {
            $node->splice_address = SpliceAddress::generate($node->id, $node->district);
            $node->saveQuietly();
        }
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('splice_address');
        });
    }
};
