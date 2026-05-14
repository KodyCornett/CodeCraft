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
            // Common — 1 port, small boost
            [
                'name'         => 'Copper NIC',
                'stat_boosted' => 'firewall',
                'boost_amount' => 1,
                'rarity'       => 'common',
                'port_cost'    => 1,
            ],
            [
                'name'         => 'Budget RAM Stick',
                'stat_boosted' => 'ram',
                'boost_amount' => 1,
                'rarity'       => 'common',
                'port_cost'    => 1,
            ],

            // Uncommon — 1 port, moderate boost
            [
                'name'         => 'Overclocked CPU Module',
                'stat_boosted' => 'cpu',
                'boost_amount' => 2,
                'rarity'       => 'uncommon',
                'port_cost'    => 1,
            ],
            [
                'name'         => 'Expanded Drive Array',
                'stat_boosted' => 'storage',
                'boost_amount' => 2,
                'rarity'       => 'uncommon',
                'port_cost'    => 1,
            ],

            // Rare — 1 port, strong boost
            [
                'name'         => 'Military-Grade Firewall Card',
                'stat_boosted' => 'firewall',
                'boost_amount' => 3,
                'rarity'       => 'rare',
                'port_cost'    => 1,
            ],

            // Legendary — 2 ports, exceptional boost
            [
                'name'         => 'Ghost OS Kernel',
                'stat_boosted' => 'os',
                'boost_amount' => 5,
                'rarity'       => 'legendary',
                'port_cost'    => 2,
            ],
        ];

        foreach ($peripherals as $data) {
            Peripheral::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
