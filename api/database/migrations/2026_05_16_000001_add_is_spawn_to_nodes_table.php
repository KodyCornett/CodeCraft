<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the is_spawn flag to nodes.
 *
 * Spawn-eligible nodes are the 6 designated entry points spread across
 * the map — one per district plus one central neighborhood junction.
 * Marked by SpawnNodeSeeder; queried by GET /api/nodes to seed the client.
 *
 * Spawn rules enforced by getSpawnNode() in useMapData.js:
 *   - is_spawn === true
 *   - type    === 'action'  (never a CyberDoc hub)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->boolean('is_spawn')->default(false)->after('ice')->index();
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('is_spawn');
        });
    }
};
