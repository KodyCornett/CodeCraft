<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // Safe, banked creds — earned by banking pocket_creds at a Street Doc.
            // Not at risk from PvP. Used to purchase items at CyberDoc.
            $table->unsignedBigInteger('wallet_creds')->default(0)->after('pocket_creds');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('wallet_creds');
        });
    }
};
