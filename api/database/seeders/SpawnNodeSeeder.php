<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Marks the 6 designated player spawn nodes.
 *
 * Spawn points are spread one-per-district across the map, each on an
 * inner-ring action node adjacent to the district's CyberDoc hub.
 * H7 (central neighborhood) is added as the sixth to give players a
 * mid-map entry option.
 *
 * To change spawn points: update SPAWN_CANVAS_IDS and re-run this seeder.
 *   php artisan db:seed --class=SpawnNodeSeeder
 */
class SpawnNodeSeeder extends Seeder
{
    private const SPAWN_CANVAS_IDS = [
        'DT-v5',   // Downtown         — inner ring, adjacent to DT-hub
        'NS-v1',   // North Spokane     — inner ring, adjacent to NS-hub
        'BA-v9',   // Browne's Addition — inner ring, adjacent to BA-hub
        'UD-v5',   // University District — inner ring, adjacent to UD-hub
        'SV-v5',   // Spokane Valley    — inner ring, adjacent to SV-hub
        'H7-v2',   // H7 neighborhood   — central map connector
    ];

    public function run(): void
    {
        // Clear any previously flagged spawn nodes first (idempotent re-runs)
        DB::table('nodes')->where('is_spawn', true)->update(['is_spawn' => false]);

        $updated = DB::table('nodes')
            ->whereIn('canvas_id', self::SPAWN_CANVAS_IDS)
            ->update(['is_spawn' => true]);

        $this->command?->info("SpawnNodeSeeder: {$updated} of " . count(self::SPAWN_CANVAS_IDS) . " spawn nodes marked.");

        // Warn about any canvas IDs that weren't found
        $found = DB::table('nodes')
            ->whereIn('canvas_id', self::SPAWN_CANVAS_IDS)
            ->pluck('canvas_id')
            ->toArray();

        $missing = array_diff(self::SPAWN_CANVAS_IDS, $found);
        foreach ($missing as $id) {
            $this->command?->warn("SpawnNodeSeeder: canvas_id '{$id}' not found in nodes table.");
        }
    }
}
