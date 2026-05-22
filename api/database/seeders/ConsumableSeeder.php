<?php

namespace Database\Seeders;

use App\Models\Consumable;
use Illuminate\Database\Seeder;

class ConsumableSeeder extends Seeder
{
    public function run(): void
    {
        $consumables = [
            // ── Software — move-counted stat boosts ──────────────────────────
            [
                'name'           => 'Scan Patch v1.1',
                'category'       => 'software',
                'stat'           => 'cpu',
                'boost_amount'   => 1,
                'duration_moves' => 10,
                'rarity'         => 'common',
                'price_creds'    => 600,
                'description'    => 'Lightweight scan utility. Basic node profiling.',
            ],
            [
                'name'           => 'Stealth Kernel',
                'category'       => 'software',
                'stat'           => 'os',
                'boost_amount'   => 2,
                'duration_moves' => 8,
                'rarity'         => 'uncommon',
                'price_creds'    => 2600,
                'description'    => 'Masks runner signature during active hacks. Short duration.',
            ],
            [
                'name'           => 'Zero-Day Exploit Pack',
                'category'       => 'software',
                'stat'           => 'cpu',
                'boost_amount'   => 4,
                'duration_moves' => 5,
                'rarity'         => 'rare',
                'price_creds'    => 7200,
                'description'    => 'Black market exploit bundle. Single use. Devastating breach power.',
            ],

            // ── Repair kits — instant SS restoration ─────────────────────────
            [
                'name'           => 'SS Stabilizer (25)',
                'category'       => 'repair',
                'stat'           => 'ss',
                'boost_amount'   => 25,
                'duration_moves' => null,
                'rarity'         => 'common',
                'price_creds'    => 150,
                'description'    => 'Restores 25 points of System Stability. Field-grade kit.',
            ],
            [
                'name'           => 'SS Stabilizer (50)',
                'category'       => 'repair',
                'stat'           => 'ss',
                'boost_amount'   => 50,
                'duration_moves' => null,
                'rarity'         => 'uncommon',
                'price_creds'    => 300,
                'description'    => 'Restores 50 points of System Stability. Clinic-grade treatment.',
            ],
            [
                'name'           => 'Full System Restore',
                'category'       => 'repair',
                'stat'           => 'ss',
                'boost_amount'   => 100,
                'duration_moves' => null,
                'rarity'         => 'rare',
                'price_creds'    => 600,
                'description'    => 'Restores full System Stability. Expensive but complete.',
            ],
        ];

        foreach ($consumables as $data) {
            Consumable::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
