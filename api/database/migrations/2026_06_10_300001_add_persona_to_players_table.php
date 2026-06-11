<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // null = persona not yet selected (first-login gate not passed)
            $table->string('persona')->nullable()->after('handle');
            $table->string('persona_desc')->nullable()->after('persona');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['persona', 'persona_desc']);
        });
    }
};
