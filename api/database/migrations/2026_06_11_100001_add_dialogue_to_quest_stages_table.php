<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_stages', function (Blueprint $table) {
            $table->json('dialogue')->nullable()->after('objective_text');
        });
    }

    public function down(): void
    {
        Schema::table('quest_stages', function (Blueprint $table) {
            $table->dropColumn('dialogue');
        });
    }
};
