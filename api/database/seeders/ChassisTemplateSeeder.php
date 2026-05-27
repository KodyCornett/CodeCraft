<?php

namespace Database\Seeders;

use App\Models\ChassisTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the chassis template catalog.
 *
 * Tier 1 — BlackHat v1.0 (starter rig, everyone begins here)
 * Tier 2 — NullTek Series 2 (three build paths unlocked at BlackHat v1.9)
 *   GX-7 Ghost     — High Uplink + High OS  (mobile/evasion)
 *   BR-9 Breaker   — High CPU + High RAM    (aggressive hacking, massive cache)
 *   VT-3 Vault     — High Firewall + Storage (PvP durability, large loadout)
 *
 * Stat design notes:
 *   - All _level fields in player_rigs start at 0 (invested points above base).
 *   - effective stat = base_* + player_rig.*_level + peripheral_boost
 *   - OS exception: base_os_level is numeric; base_os is the OS name string.
 *   - effective_os = base_os_level + os_level + boost; maxSS = effective_os × 10
 *   - cap_* = maximum effective value (base + max investable player points)
 *   - Uplink is chassis-locked; the only way to increase it is a new chassis.
 */
class ChassisTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $chassis = [

            // ── Tier 1 ────────────────────────────────────────────────────────
            [
                'name'             => 'BlackHat v1.0',
                'tier'             => 1,
                // Numeric bases
                'base_cpu'         => 3,
                'base_ram'         => 2,
                'base_firewall'    => 1,
                'base_storage'     => 2,
                'base_uplink'      => 3,
                // OS
                'base_os'          => 'BlackHat OS 1.0',
                'base_os_level'    => 2,
                // Caps (effective max = base + max investable)
                'cap_cpu'          => 5,   // 3 base + 2 investable
                'cap_ram'          => 4,   // 2 base + 2 investable
                'cap_firewall'     => 3,   // 1 base + 2 investable
                'cap_storage'      => 4,   // 2 base + 2 investable
                'cap_os'           => 4,   // 2 base + 2 investable
                // Upgrade limits
                'peripheral_slots' => 0,
                'total_point_cap'  => 9,   // v1.0 → v1.9
                // Loadout slots — 1 map, 1 hack, 1 open. No hardware slots (tier 1).
                'base_map_slots'   => 1,
                'base_hack_slots'  => 1,
                'base_open_slots'  => 1,
            ],

            // ── Tier 2 — NullTek Ghost ────────────────────────────────────────
            // High Uplink (7) + High OS: mobile, hard to locate, long runs.
            // OS 5 base → pings only reveal general area. Uplink 7 = max v2 range.
            [
                'name'             => 'NullTek GX-7 Ghost',
                'tier'             => 2,
                'base_cpu'         => 3,
                'base_ram'         => 3,
                'base_firewall'    => 2,
                'base_storage'     => 3,
                'base_uplink'      => 7,
                'base_os'          => 'NullTek GhostOS 2.0',
                'base_os_level'    => 5,
                'cap_cpu'          => 6,
                'cap_ram'          => 6,
                'cap_firewall'     => 5,
                'cap_storage'      => 6,
                'cap_os'           => 9,
                'peripheral_slots' => 2,
                'total_point_cap'  => 18,  // v2.0 → v2.9
                // Loadout slots — map-heavy evasion build. 2 hardware slots.
                'base_map_slots'   => 2,
                'base_hack_slots'  => 1,
                'base_open_slots'  => 0,
            ],

            // ── Tier 2 — NullTek Breaker ──────────────────────────────────────
            // High CPU + High RAM: fast node cracking, massive cache pool (10).
            // CPU 5 + RAM 5 = 10 cache at base — double BlackHat's starting pool.
            [
                'name'             => 'NullTek BR-9 Breaker',
                'tier'             => 2,
                'base_cpu'         => 5,
                'base_ram'         => 5,
                'base_firewall'    => 1,
                'base_storage'     => 3,
                'base_uplink'      => 5,
                'base_os'          => 'NullTek BreakerOS 2.0',
                'base_os_level'    => 2,
                'cap_cpu'          => 9,
                'cap_ram'          => 8,
                'cap_firewall'     => 4,
                'cap_storage'      => 6,
                'cap_os'           => 5,
                'peripheral_slots' => 2,
                'total_point_cap'  => 18,
                // Loadout slots — hack-heavy breach build. 2 hardware slots.
                'base_map_slots'   => 1,
                'base_hack_slots'  => 2,
                'base_open_slots'  => 0,
            ],

            // ── Tier 2 — NullTek Vault ────────────────────────────────────────
            // High Firewall + High Storage: PvP durability, huge command loadout.
            // Firewall 5 base hard-blocks most tier ≤4 commands at purchase.
            [
                'name'             => 'NullTek VT-3 Vault',
                'tier'             => 2,
                'base_cpu'         => 3,
                'base_ram'         => 3,
                'base_firewall'    => 5,
                'base_storage'     => 5,
                'base_uplink'      => 5,
                'base_os'          => 'NullTek VaultOS 2.0',
                'base_os_level'    => 2,
                'cap_cpu'          => 6,
                'cap_ram'          => 6,
                'cap_firewall'     => 9,
                'cap_storage'      => 10,
                'cap_os'           => 5,
                'peripheral_slots' => 3,
                'total_point_cap'  => 18,
                // Loadout slots — configurable tank build. 3 hardware slots (no open base).
                'base_map_slots'   => 1,
                'base_hack_slots'  => 1,
                'base_open_slots'  => 0,
            ],
        ];

        foreach ($chassis as $data) {
            ChassisTemplate::updateOrCreate(['name' => $data['name']], $data);
        }

        $this->command?->info('ChassisTemplateSeeder: ' . count($chassis) . ' chassis templates seeded.');
    }
}
