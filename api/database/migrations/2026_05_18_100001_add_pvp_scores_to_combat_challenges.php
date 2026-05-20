<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds score tracking and result caching to combat_challenges.
 *
 * challenger_score / target_score — sequences completed by each player during
 *   their GridBreach run. Both are null until the respective player dismisses
 *   the GridBreach outcome and submits via POST /api/combat/result.
 *   Higher score wins; ties go to the challenger.
 *
 * result_payload — JSON snapshot of the full resolution response, stored once
 *   when both scores arrive. The second submitter receives this directly;
 *   the first submitter retrieves it via GET /api/combat/result/{id} polling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combat_challenges', function (Blueprint $table) {
            $table->unsignedInteger('challenger_score')->nullable()->after('node_canvas_id');
            $table->unsignedInteger('target_score')->nullable()->after('challenger_score');
            $table->text('result_payload')->nullable()->after('target_score');
        });
    }

    public function down(): void
    {
        Schema::table('combat_challenges', function (Blueprint $table) {
            $table->dropColumn(['challenger_score', 'target_score', 'result_payload']);
        });
    }
};
