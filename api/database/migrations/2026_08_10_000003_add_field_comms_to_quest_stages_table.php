<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * field_comms — the DOC's in-field voice-call lines for a stage, distinct
 * from `dialogue` (the CyberDoc terminal conversation back at the hub).
 * Mirrors the dialogue column exactly: nullable JSON array of
 * { text, audio? } lines, played through FieldCommsWindow.vue when the
 * player arrives at the stage's field node.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_stages', function (Blueprint $table) {
            $table->json('field_comms')->nullable()->after('dialogue');
        });
    }

    public function down(): void
    {
        Schema::table('quest_stages', function (Blueprint $table) {
            $table->dropColumn('field_comms');
        });
    }
};
