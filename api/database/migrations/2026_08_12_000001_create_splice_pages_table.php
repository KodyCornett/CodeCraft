<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * splice_pages
 *
 * Static, dev-authored content — not per-player. Represents a hidden SPLICE
 * page a player can reach by resolving a key at the Decrypter (see
 * player_document_keys) or, for flavor-only pages, by following a lead link
 * from a codex page's body.
 *
 * Two tiers, distinguished by `type`:
 *   - 'flavor' — pure reading, no login, no reward. Auto-completed the
 *     moment a player unlocks it (see PlayerSpliceUnlock).
 *   - 'codex'  — carries a login with `login_password` missing from the
 *     page itself. The password lives on one of this page's `lead_slugs`
 *     (a flavor page elsewhere) — some leads are red herrings, at least one
 *     contains the real answer. Solving it pays reward_tech_points/reward_creds.
 *
 * `source_key` is a loose tag (e.g. a node canvas_id or a chapter/thread tag)
 * used by CodexService::resolveKey() to pick which pages are eligible to
 * resolve for a given earned key — not a foreign key, just a matching string
 * so content can be authored without hard-coding IDs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('splice_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('type');          // 'flavor' | 'codex'
            $table->string('title');
            $table->text('body');
            $table->string('source_key')->nullable();  // matches player_document_keys.source_node_canvas_id

            // Codex-tier login puzzle — null for flavor pages
            $table->string('login_username')->nullable();
            $table->string('login_password')->nullable();
            $table->json('lead_slugs')->nullable();     // array of splice_pages.slug — some red herrings, some real

            // Codex-tier reward, granted on solving the login
            $table->unsignedInteger('reward_creds')->nullable();
            $table->decimal('reward_tech_points', 8, 2)->nullable();

            $table->timestamps();

            $table->index('source_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('splice_pages');
    }
};
