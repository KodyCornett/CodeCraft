<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codex-tier pages need content that only appears after a successful login
 * solve — the actual decrypted payload, distinct from the pre-login preview
 * already stored in `body`. Without this, `body` was the only content field
 * and was always visible regardless of solve state, so there was nothing to
 * reveal. `unlocked_body` is nullable and only meaningful for type='codex'
 * rows; flavor pages leave it null. See CodexService::getPageBySlug() and
 * ::solveLogin() for the gating logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('splice_pages', function (Blueprint $table) {
            $table->text('unlocked_body')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('splice_pages', function (Blueprint $table) {
            $table->dropColumn('unlocked_body');
        });
    }
};
