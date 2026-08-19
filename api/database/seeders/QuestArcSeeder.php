<?php

namespace Database\Seeders;

use App\Models\CyberDoc;
use App\Models\Node;
use App\Models\QuestArc;
use Illuminate\Database\Seeder;

/**
 * Seeds skeleton quest arcs for all 5 CyberDocs.
 * Keyed by canvas_id to match CyberDocSeeder — immune to name changes.
 *
 * No story content here. Stages are added via QuestStageSeeder.
 *
 * Rep thresholds (tier gates):
 *   NULL      =    0
 *   RESOLVED  =  250
 *   ROUTED    =  600
 *   ENCRYPTED = 1200
 *   ROOT      = 2000
 */
class QuestArcSeeder extends Seeder
{
    private const ARCS = [
        'BA-hub' => [   // Knuckle — starting doc, auto-unlocks for all new players
            [
                'sequence_order' => 1,
                'title'          => 'The Viral Breach',
                'rep_required'   => 0,
                'is_entry_arc'   => true,
            ],
            [
                // Chapter 1 — unlocked by the Chapter-1-kickoff special-case in
                // QuestService (Patch's Prologue-completion stage), not by referral.
                'sequence_order' => 2,
                'title'          => 'The Dead End',
                'rep_required'   => 0,
                'is_entry_arc'   => false,
            ],
        ],
        'DT-hub' => [   // Veil
            [
                'sequence_order' => 1,
                'title'          => 'The Smoothing Protocol',
                'rep_required'   => 0,
                'is_entry_arc'   => false,
            ],
            [
                'sequence_order' => 2,
                'title'          => 'The Persistence Theory',
                'rep_required'   => 0,
                'is_entry_arc'   => false,
            ],
        ],
        'SV-hub' => [   // Float
            [
                'sequence_order' => 1,
                'title'          => 'The Drift-Anchor',
                'rep_required'   => 0,
                'is_entry_arc'   => false,
            ],
            [
                'sequence_order' => 2,
                'title'          => 'The Cold Static',
                'rep_required'   => 0,
                'is_entry_arc'   => false,
            ],
        ],
        'UD-hub' => [   // Axiom
            [
                'sequence_order' => 1,
                'title'          => 'The Deep Archive',
                'rep_required'   => 0,
                'is_entry_arc'   => false,
            ],
            [
                'sequence_order' => 2,
                'title'          => 'The Reaching Signal',
                'rep_required'   => 0,
                'is_entry_arc'   => false,
            ],
        ],
        'NS-hub' => [   // Patch
            [
                'sequence_order' => 1,
                'title'          => 'Ghost-Kernel Calibration',
                'rep_required'   => 0,
                'is_entry_arc'   => false,
            ],
            [
                'sequence_order' => 2,
                'title'          => 'The Pickup',
                'rep_required'   => 0,
                'is_entry_arc'   => false,
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::ARCS as $canvasId => $arcs) {
            $node = Node::where('canvas_id', $canvasId)->first();
            if ($node === null) {
                $this->command?->warn("QuestArcSeeder: node '{$canvasId}' not found — skipped.");
                continue;
            }

            $doc = CyberDoc::where('node_id', $node->id)->first();
            if ($doc === null) {
                $this->command?->warn("QuestArcSeeder: CyberDoc at '{$canvasId}' not found — skipped.");
                continue;
            }

            foreach ($arcs as $arcData) {
                QuestArc::updateOrCreate(
                    [
                        'cyber_doc_id'   => $doc->id,
                        'sequence_order' => $arcData['sequence_order'],
                    ],
                    [
                        'title'        => $arcData['title'],
                        'rep_required' => $arcData['rep_required'],
                        'is_entry_arc' => $arcData['is_entry_arc'],
                    ],
                );
            }
        }
    }
}
