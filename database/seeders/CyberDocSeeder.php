<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\CyberDoc;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CyberDocSeeder extends Seeder
{
    public function run(): void
    {
        $docs = [
            [
                'handle' => 'Knuckle',
                'district' => "Browne's Addition",
                'layer' => 'Autonomic Dependency',
            ],
            [
                'handle' => 'Veil',
                'district' => 'Downtown Core',
                'layer' => 'Structural Equilibrium',
            ],
            [
                'handle' => 'Axiom',
                'district' => 'University District',
                'layer' => 'Archival Provenance',
            ],
            [
                'handle' => 'Float',
                'district' => 'Spokane Valley',
                'layer' => 'Historical Ledger',
            ],
            [
                'handle' => 'Patch',
                'district' => 'North Spokane',
                'layer' => 'Sensory Immersion',
            ],
        ];

        foreach ($docs as $docData) {
            $user = User::create([
                'name' => $docData['handle'],
                'email' => strtolower($docData['handle']) . '@codecraft.internal',
                'password' => Hash::make('system_protected_identity'),
                'is_cyberdoc' => true,
            ]);

            $user->cyberDoc()->create([
                'district' => $docData['district'],
                'specialty' => $docData['layer'],
            ]);
        }
    }
}