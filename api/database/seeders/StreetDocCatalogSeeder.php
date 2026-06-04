<?php

namespace Database\Seeders;

use App\Models\Consumable;
use App\Services\StreetDocInventoryService;
use Illuminate\Database\Seeder;

/**
 * Seeds the street_doc_catalog table with items that should be
 * available at CyberDoc terminals.
 *
 * ── Global items (available at every doc) ────────────────────────────────────
 *
 *   All three repair consumables are seeded as global — street_doc_id = null.
 *   Any doc can sell them without needing an explicit row per terminal.
 *
 * ── How to add doc-specific items ────────────────────────────────────────────
 *
 *   Call StreetDocInventoryService::grantCatalogItem() with the hub canvas_id
 *   and any options you need. The service is the single point of truth.
 *
 *   Examples:
 *
 *     // Exclusive peripheral only at Downtown
 *     $service->grantCatalogItem('DT-hub', 'peripheral', $peripheralId, [
 *         'is_exclusive' => true,
 *         'source'       => 'seed',
 *     ]);
 *
 *     // Mission-unlocked chassis only at Spokane Valley, limited to 1 purchase
 *     $service->grantCatalogItem('SV-hub', 'chassis', $chassisId, [
 *         'is_exclusive' => true,
 *         'stock_limit'  => 1,
 *         'source'       => 'mission:valley_contract_03',
 *     ]);
 *
 *     // 24-hour rotating special at North Spokane
 *     $service->grantCatalogItem('NS-hub', 'consumable', $consumableId, [
 *         'source'          => 'rotation:2026-06-04',
 *         'available_until' => now()->addHours(24),
 *     ]);
 *
 * ── Removing items ────────────────────────────────────────────────────────────
 *
 *   $service->revokeBySource('mission:X');          // remove all from one mission
 *   $service->revokeBySource('mission:X', 'SV-hub'); // scoped to one doc
 */
class StreetDocCatalogSeeder extends Seeder
{
    public function __construct(private readonly StreetDocInventoryService $inventory) {}

    public function run(): void
    {
        $this->seedGlobalRepairKits();
    }

    // -------------------------------------------------------------------------
    // Global — repair consumables at every doc
    // -------------------------------------------------------------------------

    private function seedGlobalRepairKits(): void
    {
        $kits = Consumable::where('category', 'repair')->get();

        if ($kits->isEmpty()) {
            $this->command?->warn('StreetDocCatalogSeeder: no repair consumables found — run ConsumableSeeder first.');
            return;
        }

        foreach ($kits as $kit) {
            // null canvasId = global (all docs carry it)
            $this->inventory->grantCatalogItem(
                canvasId: null,
                itemType: 'consumable',
                itemId:   $kit->id,
                options:  ['source' => 'seed'],
            );
        }

        $this->command?->info("StreetDocCatalogSeeder: seeded {$kits->count()} global repair consumable(s).");
    }
}
