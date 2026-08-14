<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two changes to splice_pages:
 *
 * - source_key -> thread_key. Same purpose (matching content to a hunt),
 *   renamed because it no longer matches a node canvas_id — it matches a
 *   codex thread activated by a quest stage (player_codex_activations).
 *
 * - login_password -> credentials (JSON array of {label, answer}). Codex
 *   pages now support multiple required credentials instead of exactly one
 *   password, so harder threads can require synthesizing several documents'
 *   worth of information instead of just one. login_username stays as a
 *   single decorative field shown on the page regardless of field count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('splice_pages', function (Blueprint $table) {
            $table->renameColumn('source_key', 'thread_key');
        });

        Schema::table('splice_pages', function (Blueprint $table) {
            $table->json('credentials')->nullable()->after('login_username');
        });

        Schema::table('splice_pages', function (Blueprint $table) {
            $table->dropColumn('login_password');
        });
    }

    public function down(): void
    {
        Schema::table('splice_pages', function (Blueprint $table) {
            $table->string('login_password')->nullable()->after('login_username');
        });

        Schema::table('splice_pages', function (Blueprint $table) {
            $table->dropColumn('credentials');
        });

        Schema::table('splice_pages', function (Blueprint $table) {
            $table->renameColumn('thread_key', 'source_key');
        });
    }
};
