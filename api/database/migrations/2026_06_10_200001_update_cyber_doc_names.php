<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Updates cyber_doc names from placeholder location names to character names.
 * Looks up each doc via its hub node canvas_id — same approach as CyberDocSeeder.
 */
return new class extends Migration
{
    private const NAMES = [
        'NS-hub' => "Patch's Clinic",
        'BA-hub' => "Knuckle's Med-Wagon",
        'DT-hub' => "Veil's Parlour",
        'UD-hub' => 'Axiom Systems',
        'SV-hub' => "Float's Repair Bay",
    ];

    public function up(): void
    {
        foreach (self::NAMES as $canvasId => $name) {
            $node = DB::table('nodes')->where('canvas_id', $canvasId)->first();
            if ($node === null) continue;

            DB::table('cyber_docs')
                ->where('node_id', $node->id)
                ->update(['name' => $name]);
        }
    }

    public function down(): void
    {
        $old = [
            'NS-hub' => 'North Side Hardware Hub',
            'BA-hub' => "Browne's Backroom Clinic",
            'DT-hub' => 'Monroe St. Underground',
            'UD-hub' => 'Campus Black Market',
            'SV-hub' => 'Valley Depot Repairs',
        ];

        foreach ($old as $canvasId => $name) {
            $node = DB::table('nodes')->where('canvas_id', $canvasId)->first();
            if ($node === null) continue;

            DB::table('cyber_docs')
                ->where('node_id', $node->id)
                ->update(['name' => $name]);
        }
    }
};
