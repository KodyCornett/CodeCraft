<?php

namespace Database\Seeders;

use App\Models\Node;
use App\Models\StreetDoc;
use Illuminate\Database\Seeder;

/**
 * Seeds one Street Doc (repair/upgrade shop) per district.
 *
 * Each Street Doc is anchored to the district's CyberDoc hub node
 * (type = 'cyberdoc') — the yellow hexagonal nodes on the canvas.
 * One per district means the player always has a clear, named home base.
 *
 * Hub canvas_ids:
 *   NS-hub  → North Spokane
 *   BA-hub  → Browne's Addition
 *   DT-hub  → Downtown
 *   UD-hub  → University District
 *   SV-hub  → Spokane Valley
 */
class StreetDocSeeder extends Seeder
{
    private const DOCS = [
        'NS-hub' => ['name' => 'North Side Hardware Hub',     'district' => 'North Spokane'],
        'BA-hub' => ['name' => "Browne's Backroom Clinic",    'district' => "Browne's Addition"],
        'DT-hub' => ['name' => 'Monroe St. Underground',      'district' => 'Downtown'],
        'UD-hub' => ['name' => 'Campus Black Market',         'district' => 'University District'],
        'SV-hub' => ['name' => 'Valley Depot Repairs',        'district' => 'Spokane Valley'],
    ];

    public function run(): void
    {
        foreach (self::DOCS as $canvasId => $config) {
            $node = Node::where('canvas_id', $canvasId)->first();

            if ($node === null) {
                $this->command?->warn("StreetDocSeeder: node '{$canvasId}' not found — skipped.");
                continue;
            }

            StreetDoc::updateOrCreate(
                ['node_id' => $node->id],
                [
                    'district' => $config['district'],
                    'name'     => $config['name'],
                ],
            );
        }
    }
}
