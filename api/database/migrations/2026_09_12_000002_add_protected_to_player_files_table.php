<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * System-seeded folders/files (the starter Documents contents, and the
 * Documents/Downloads folders themselves) are marked protected so the
 * player can't move or delete them — see FileService::ensureSeeded().
 * Anything the player or a game system adds later is unprotected and can
 * be deleted from inside its folder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_files', function (Blueprint $table) {
            $table->boolean('protected')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('player_files', function (Blueprint $table) {
            $table->dropColumn('protected');
        });
    }
};
