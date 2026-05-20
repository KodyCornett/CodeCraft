<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expands the commands catalog to carry all GDD-required fields.
 *
 * New columns:
 *  tier        — 1–5, gates equipping by player RAM level
 *  price_creds — Cred cost to purchase from CyberDoc
 *  price_tp    — Tech Point cost to purchase from CyberDoc
 *  target_type — how the command is activated on the map (self/node/player)
 *  map_effect  — description of the map-layer effect
 *  hack_effect — description of the Grid-Breach effect
 *
 * Type enum is rebuilt to include 'trap' and 'stealth' in addition to the
 * existing 'offensive', 'defensive', 'movement' values.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (used in testing) doesn't support ALTER COLUMN for enums,
        // so we modify via a raw statement on MySQL/Postgres and skip on SQLite.
        $driver = DB::connection()->getDriverName();

        Schema::table('commands', function (Blueprint $table) use ($driver) {
            $table->unsignedTinyInteger('tier')->default(1)->after('name');
            $table->unsignedInteger('price_creds')->default(0)->after('tier');
            $table->unsignedInteger('price_tp')->default(0)->after('price_creds');
            $table->string('target_type', 20)->default('self')->after('price_tp'); // self|node|player
            $table->text('map_effect')->nullable()->after('description');
            $table->text('hack_effect')->nullable()->after('map_effect');

            if ($driver !== 'sqlite') {
                $table->enum('type', ['offensive', 'defensive', 'movement', 'trap', 'stealth'])
                    ->default('offensive')
                    ->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropColumn([
                'tier', 'price_creds', 'price_tp',
                'target_type', 'map_effect', 'hack_effect',
            ]);
        });
    }
};
