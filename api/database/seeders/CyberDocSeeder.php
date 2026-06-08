<?php

namespace Database\Seeders;

use App\Models\CyberDoc;
use App\Models\Node;
use Illuminate\Database\Seeder;

/**
 * Seeds one CyberDoc (repair/upgrade shop) per district.
 *
 * Each CyberDoc is anchored to the district's hub node (type = 'cyberdoc').
 *
 * Hub canvas_ids:
 *   NS-hub  → North Spokane
 *   BA-hub  → Browne's Addition
 *   DT-hub  → Downtown
 *   UD-hub  → University District
 *   SV-hub  → Spokane Valley
 */
class CyberDocSeeder extends Seeder
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
                $this->command?->warn("CyberDocSeeder: node '{$canvasId}' not found — skipped.");
                continue;
            }

            CyberDoc::updateOrCreate(
                ['node_id' => $node->id],
                [
                    'district' => $config['district'],
                    'name'     => $config['name'],
                ],
            );
        }
    }
}
