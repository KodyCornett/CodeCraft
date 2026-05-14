<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Node;
use App\Models\StreetDoc;
use Illuminate\Database\Seeder;

/**
 * Seeds one Street Doc per district using an existing node in that district.
 *
 * Street Doc names are styled as underground/back-alley repair shops to fit
 * the game's Spokane cyberpunk aesthetic.
 */
class StreetDocSeeder extends Seeder
{
    /**
     * Maps district name → [Street Doc name, preferred node business_name].
     * Falls back to the first node in the district if the preferred node doesn't exist.
     */
    private const DOCS = [
        'Downtown' => [
            'name'        => 'Monroe St. Underground',
            'prefer_node' => 'Steam Plant Square',
        ],
        'South Hill' => [
            'name'        => 'South Hill Salvage & Repair',
            'prefer_node' => 'Manito Park',
        ],
        'University District' => [
            'name'        => 'Campus Black Market',
            'prefer_node' => 'Washington State University Spokane',
        ],
        "Browne's Addition" => [
            'name'        => "Browne's Backroom Clinic",
            'prefer_node' => 'Dempseys Brass Rail',
        ],
        'North Spokane' => [
            'name'        => 'North Side Hardware Hub',
            'prefer_node' => 'Spokane County Library North',
        ],
        'Spokane Valley' => [
            'name'        => 'Valley Depot Repairs',
            'prefer_node' => 'Spokane Valley Mall',
        ],
    ];

    public function run(): void
    {
        foreach (self::DOCS as $districtName => $config) {
            $district = District::where('name', $districtName)->first();
            if ($district === null) {
                continue;  // district not seeded yet — skip
            }

            // Try preferred node first, fall back to any node in the district
            $node = Node::where('district_id', $district->id)
                ->where('business_name', $config['prefer_node'])
                ->first()
                ?? Node::where('district_id', $district->id)->first();

            if ($node === null) {
                continue;  // no nodes in this district yet — skip
            }

            StreetDoc::firstOrCreate(
                ['node_id' => $node->id],
                [
                    'district_id' => $district->id,
                    'name'        => $config['name'],
                ],
            );
        }
    }
}
