<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\PlayerRig;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the BlackHat v1.0 chassis template and creates a test player.
 *
 * BlackHat v1.0 — starter rig:
 *   CPU 3 | RAM 2 | Firewall 1 | Storage 2 | OS 2
 *   0 peripheral ports | 10 total upgrade point cap
 *
 * The test player is linked to the test user (test@example.com) seeded by
 * DatabaseSeeder. Running migrate:fresh --seed will always produce a player
 * with a known handle ('CodeCraft') and predictable base stats.
 */
class PlayerSeeder extends Seeder
{
    public function run(): void
    {
        // ── Chassis template — BlackHat v1.0 ─────────────────────────────── //
        $chassis = DB::table('chassis_templates')
            ->where('name', 'BlackHat v1.0')
            ->first();

        if (!$chassis) {
            $chassisId = (string) Str::uuid();
            DB::table('chassis_templates')->insert([
                'id'               => $chassisId,
                'name'             => 'BlackHat v1.0',
                'base_cpu'         => 3,
                'base_ram'         => 2,
                'base_firewall'    => 1,
                'base_storage'     => 2,
                'base_uplink'      => 3,
                'base_os'          => 'BlackHat OS 1.0',
                'peripheral_slots' => 0,
                'total_point_cap'  => 10,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        } else {
            $chassisId = $chassis->id;
        }

        // ── Test user ─────────────────────────────────────────────────────── //
        $user = User::where('email', 'test@example.com')->first();

        if (!$user) {
            $this->command?->warn('PlayerSeeder: test user not found — run DatabaseSeeder first.');
            return;
        }

        // ── Player ────────────────────────────────────────────────────────── //
        $player = Player::firstOrCreate(
            ['user_id' => $user->id],
            [
                'handle'           => 'CodeCraft',
                'current_district' => 'DOWNTOWN',
                'bounty_level'     => 0,
            ],
        );

        // ── Rig — BlackHat v1.0 base stats ───────────────────────────────── //
        $existingRig = DB::table('player_rigs')
            ->where('player_id', $player->id)
            ->first();

        if (!$existingRig) {
            DB::table('player_rigs')->insert([
                'id'                  => (string) Str::uuid(),
                'player_id'           => $player->id,
                'chassis_template_id' => $chassisId,
                'cpu_level'           => 3,
                'ram_level'           => 2,
                'firewall_level'      => 1,
                'storage_level'       => 2,
                'os_level'            => 2,
                'current_ss'          => 100,
                'is_limping'          => false,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }

        $this->command?->info("PlayerSeeder: player '{$player->handle}' ready (id: {$player->id})");
    }
}
