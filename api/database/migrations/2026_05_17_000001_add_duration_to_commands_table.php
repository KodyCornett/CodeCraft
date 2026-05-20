<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `duration` JSON column to commands.
 *
 * Stores the effect window as a JSON object, e.g.:
 *   null                        — instant / permanent until CyberDoc
 *   {"moves": 5, "minutes": 5}  — whichever fires first
 *   {"moves": 3}                — move-count only
 *   {"hacks": 3}                — hack-count only
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->json('duration')->nullable()->after('hack_effect');
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};
