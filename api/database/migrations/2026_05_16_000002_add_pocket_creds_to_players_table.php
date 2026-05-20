<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds pocket_creds to players.
 *
 * pocket_creds — creds earned this run that have NOT been banked at a CyberDoc.
 * This is the at-risk pool that another player can steal on a successful PvP
 * extract. It is zeroed when:
 *   • The player banks at a CyberDoc (extract → safe wallet)
 *   • The player loses PvP combat (wiped; winner gets their cut, ICE seizes rest)
 *
 * Incremented by POST /api/nodes/{id}/deplete when resource === 'creds'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedInteger('pocket_creds')->default(0)->after('last_street_doc_id');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('pocket_creds');
        });
    }
};
