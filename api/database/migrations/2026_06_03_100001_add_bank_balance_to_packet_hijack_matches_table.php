<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            $table->unsignedInteger('challenger_bank_balance')->nullable()->after('challenger_credential_state');
            $table->unsignedInteger('defender_bank_balance')->nullable()->after('defender_credential_state');
        });
    }

    public function down(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            $table->dropColumn(['challenger_bank_balance', 'defender_bank_balance']);
        });
    }
};
