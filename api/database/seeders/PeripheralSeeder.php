<?php

namespace Database\Seeders;

use App\Models\Peripheral;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PeripheralSeeder extends Seeder
{
    public function run(): void
    {
        $peripherals = [

            // ── CPU ───────────────────────────────────────────────────────────
            [
                'name'         => 'Bare-Metal CPU Patch',
                'stat_boosted' => 'cpu',
                'boost_amount' => 1,
                'rarity'       => 'common',
                'port_cost'    => 1,
                'price_creds'  => 900,
            ],
            [
                'name'         => 'Overclocked CPU Module',
                'stat_boosted' => 'cpu',
                'boost_amount' => 2,
                'rarity'       => 'uncommon',
                'port_cost'    => 1,
                'price_creds'  => 2200,
            ],
            [
                'name'         => 'Hardwired CPU Core',
                'stat_boosted' => 'cpu',
                'boost_amount' => 3,
                'rarity'       => 'rare',
                'port_cost'    => 1,
                'price_creds'  => 5000,
            ],

            // ── RAM ───────────────────────────────────────────────────────────
            [
                'name'         => 'Budget RAM Stick',
                'stat_boosted' => 'ram',
                'boost_amount' => 1,
                'rarity'       => 'common',
                'port_cost'    => 1,
                'price_creds'  => 900,
            ],
            [
                'name'         => 'Ghost RAM Module',
                'stat_boosted' => 'ram',
                'boost_amount' => 2,
                'rarity'       => 'uncommon',
                'port_cost'    => 1,
                'price_creds'  => 2000,
            ],
            [
                'name'         => 'High-Density RAM Stack',
                'stat_boosted' => 'ram',
                'boost_amount' => 3,
                'rarity'       => 'rare',
                'port_cost'    => 1,
                'price_creds'  => 4800,
            ],

            // ── Firewall ──────────────────────────────────────────────────────
            [
                'name'         => 'Copper NIC',
                'stat_boosted' => 'firewall',
                'boost_amount' => 1,
                'rarity'       => 'common',
                'port_cost'    => 1,
                'price_creds'  => 800,
            ],
            [
                'name'         => 'Hardened NIC',
                'stat_boosted' => 'firewall',
                'boost_amount' => 2,
                'rarity'       => 'uncommon',
                'port_cost'    => 1,
                'price_creds'  => 2200,
            ],
            [
                'name'         => 'Military-Grade Firewall Card',
                'stat_boosted' => 'firewall',
                'boost_amount' => 3,
                'rarity'       => 'rare',
                'port_cost'    => 1,
                'price_creds'  => 5500,
            ],

            // ── Storage ───────────────────────────────────────────────────────
            [
                'name'         => 'Surplus Drive',
                'stat_boosted' => 'storage',
                'boost_amount' => 1,
                'rarity'       => 'common',
                'port_cost'    => 1,
                'price_creds'  => 700,
            ],
            [
                'name'         => 'Expanded Drive Array',
                'stat_boosted' => 'storage',
                'boost_amount' => 2,
                'rarity'       => 'uncommon',
                'port_cost'    => 1,
                'price_creds'  => 1800,
            ],
            [
                'name'         => 'Deep Vault Array',
                'stat_boosted' => 'storage',
                'boost_amount' => 3,
                'rarity'       => 'rare',
                'port_cost'    => 1,
                'price_creds'  => 4500,
            ],

            // ── OS ────────────────────────────────────────────────────────────
            [
                'name'         => 'Bootleg OS Patch',
                'stat_boosted' => 'os',
                'boost_amount' => 1,
                'rarity'       => 'common',
                'port_cost'    => 1,
                'price_creds'  => 1000,
            ],
            [
                'name'         => 'Ghost OS Kernel',
                'stat_boosted' => 'os',
                'boost_amount' => 2,
                'rarity'       => 'uncommon',
                'port_cost'    => 1,
                'price_creds'  => 2500,
            ],
            [
                'name'         => 'Daemon Core Kernel',
                'stat_boosted' => 'os',
                'boost_amount' => 3,
                'rarity'       => 'rare',
                'port_cost'    => 1,
                'price_creds'  => 6000,
            ],

            // ── Uplink — hardware-only stat, cannot be raised by invested points ──
            [
                'name'         => 'Deep Link Mk.I',
                'stat_boosted' => 'uplink',
                'boost_amount' => 1,
                'rarity'       => 'common',
                'port_cost'    => 1,
                'price_creds'  => 1200,
            ],
            [
                'name'         => 'Deep Link Mk.II',
                'stat_boosted' => 'uplink',
                'boost_amount' => 2,
                'rarity'       => 'uncommon',
                'port_cost'    => 1,
                'price_creds'  => 3000,
            ],
            [
                'name'         => 'Deep Link Mk.III',
                'stat_boosted' => 'uplink',
                'boost_amount' => 3,
                'rarity'       => 'rare',
                'port_cost'    => 1,
                'price_creds'  => 7000,
            ],

            // ── Command Modules — Nav Wraith series (map slots) ───────────────
            // Each Mk adds one map loadout slot. Tier = max command level allowed.
            [
                'name'            => 'Nav Wraith Mk.I',
                'stat_boosted'    => null,
                'boost_amount'    => 0,
                'rarity'          => 'uncommon',
                'port_cost'       => 1,
                'price_creds'     => 1500,
                'peripheral_type' => 'command_module',
                'slot_type'       => 'map',
                'slot_tier'       => 1,
            ],
            [
                'name'            => 'Nav Wraith Mk.II',
                'stat_boosted'    => null,
                'boost_amount'    => 0,
                'rarity'          => 'rare',
                'port_cost'       => 1,
                'price_creds'     => 3500,
                'peripheral_type' => 'command_module',
                'slot_type'       => 'map',
                'slot_tier'       => 2,
            ],
            [
                'name'            => 'Nav Wraith Mk.III',
                'stat_boosted'    => null,
                'boost_amount'    => 0,
                'rarity'          => 'legendary',
                'port_cost'       => 1,
                'price_creds'     => 8000,
                'peripheral_type' => 'command_module',
                'slot_type'       => 'map',
                'slot_tier'       => 3,
            ],

            // ── Command Modules — ICE Pick series (hack slots) ────────────────
            // Each Mk adds one hack loadout slot. Tier = max command level allowed.
            [
                'name'            => 'ICE Pick Mk.I',
                'stat_boosted'    => null,
                'boost_amount'    => 0,
                'rarity'          => 'uncommon',
                'port_cost'       => 1,
                'price_creds'     => 1500,
                'peripheral_type' => 'command_module',
                'slot_type'       => 'hack',
                'slot_tier'       => 1,
            ],
            [
                'name'            => 'ICE Pick Mk.II',
                'stat_boosted'    => null,
                'boost_amount'    => 0,
                'rarity'          => 'rare',
                'port_cost'       => 1,
                'price_creds'     => 3500,
                'peripheral_type' => 'command_module',
                'slot_type'       => 'hack',
                'slot_tier'       => 2,
            ],
            [
                'name'            => 'ICE Pick Mk.III',
                'stat_boosted'    => null,
                'boost_amount'    => 0,
                'rarity'          => 'legendary',
                'port_cost'       => 1,
                'price_creds'     => 8000,
                'peripheral_type' => 'command_module',
                'slot_type'       => 'hack',
                'slot_tier'       => 3,
            ],
        ];

        foreach ($peripherals as $data) {
            Peripheral::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}
