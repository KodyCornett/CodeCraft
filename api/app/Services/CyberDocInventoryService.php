<?php

namespace App\Services;

use App\Models\ChassisTemplate;
use App\Models\Command;
use App\Models\Consumable;
use App\Models\CyberDoc;
use App\Models\CyberDocCatalog;
use App\Models\Peripheral;
use Carbon\Carbon;
use RuntimeException;

/**
 * CyberDocInventoryService
 *
 * Single point of control for what each CyberDoc terminal carries.
 *
 * ## The open seeding call — grantCatalogItem()
 *
 * Any system that needs to put an item in a doc's inventory calls this method:
 *
 *   - Seeders (base static inventory)
 *   - Mission completion handlers (exclusive/limited items unlocked by quest)
 *   - Artisan commands (rotating specials, admin grants)
 *   - Future event system (timed specials)
 *
 * The call is idempotent: running it twice for the same (doc, type, item)
 * updates the existing row rather than inserting a duplicate.
 *
 * ## catalogForDoc()
 *
 * Returns the fully resolved item payload for a specific terminal —
 * global items + doc-specific items, unexpired, ready to hand to the client.
 */
class CyberDocInventoryService
{
    // -------------------------------------------------------------------------
    // Public API — seeding
    // -------------------------------------------------------------------------

    /**
     * Grant an item to a CyberDoc terminal's catalog.
     *
     * @param string|null $canvasId      Hub canvas_id (e.g. 'BA-hub').
     *                                   Pass null to add a global item (every doc).
     * @param string      $itemType      'peripheral' | 'consumable' | 'command' | 'chassis'
     * @param string      $itemId        UUID of the item in its source table.
     * @param array       $options {
     *   bool        is_exclusive     Whether to hide from global queries (default false).
     *   int|null    stock_limit      Finite purchase cap across all players. null = unlimited.
     *   string      source           Audit label — e.g. 'seed', 'mission:vault_run_01'.
     *   Carbon|null available_until  Expiry timestamp. null = permanent.
     * }
     *
     * @throws RuntimeException When canvasId is given but no matching CyberDoc exists.
     */
    public function grantCatalogItem(
        ?string $canvasId,
        string  $itemType,
        string  $itemId,
        array   $options = []
    ): CyberDocCatalog {
        $this->assertValidItemType($itemType);

        $cyberDocId = null;

        if ($canvasId !== null) {
            $cyberDoc   = $this->resolveDoc($canvasId);
            $cyberDocId = $cyberDoc->id;
        }

        $availableUntil = $options['available_until'] ?? null;
        if ($availableUntil !== null && !$availableUntil instanceof Carbon) {
            $availableUntil = Carbon::parse($availableUntil);
        }

        /** @var CyberDocCatalog $row */
        $row = CyberDocCatalog::updateOrCreate(
            [
                'cyber_doc_id' => $cyberDocId,
                'item_type'    => $itemType,
                'item_id'      => $itemId,
            ],
            [
                'is_exclusive'    => $options['is_exclusive']  ?? false,
                'stock_limit'     => $options['stock_limit']   ?? null,
                'source'          => $options['source']        ?? 'seed',
                'available_until' => $availableUntil,
            ]
        );

        return $row;
    }

    /**
     * Revoke all catalog entries created by a specific source.
     *
     * @param string      $source    The source label to delete.
     * @param string|null $canvasId  Scope to one doc. null = all docs.
     */
    public function revokeBySource(string $source, ?string $canvasId = null): int
    {
        $query = CyberDocCatalog::where('source', $source);

        if ($canvasId !== null) {
            $cyberDoc = $this->resolveDoc($canvasId);
            $query->where('cyber_doc_id', $cyberDoc->id);
        }

        return $query->delete();
    }

    // -------------------------------------------------------------------------
    // Public API — catalog retrieval
    // -------------------------------------------------------------------------

    /**
     * Return the full resolved catalog for a specific CyberDoc terminal.
     *
     * Includes:
     *   - Global items (cyber_doc_id IS NULL) that are not expired
     *   - Doc-specific items for this terminal that are not expired
     *
     * @param  string $canvasId  Hub canvas_id (e.g. 'NS-hub').
     * @return array{hardware: array, consumables: array, commands: array}
     *
     * @throws RuntimeException When no CyberDoc exists for the given canvasId.
     */
    public function catalogForDoc(string $canvasId): array
    {
        $cyberDoc = $this->resolveDoc($canvasId);

        $entries = CyberDocCatalog::forDoc($cyberDoc->id)
            ->active()
            ->get();

        $byType = $entries->groupBy('item_type');

        $peripheralIds = $byType->get('peripheral', collect())->pluck('item_id')->all();
        $consumableIds = $byType->get('consumable', collect())->pluck('item_id')->all();
        $commandIds    = $byType->get('command',    collect())->pluck('item_id')->all();
        $chassisIds    = $byType->get('chassis',    collect())->pluck('item_id')->all();

        $catalogMeta = $entries->keyBy(fn ($e) => $e->item_type . ':' . $e->item_id);

        // ── Hardware (peripherals + chassis templates) ────────────────────────

        $hardware = collect();

        if (!empty($peripheralIds)) {
            $peripherals = Peripheral::whereIn('id', $peripheralIds)
                ->orderBy('rarity')
                ->orderBy('name')
                ->get()
                ->map(fn ($p) => $this->formatPeripheral($p, $catalogMeta['peripheral:' . $p->id] ?? null));

            $hardware = $hardware->merge($peripherals);
        }

        if (!empty($chassisIds)) {
            $chassis = ChassisTemplate::whereIn('id', $chassisIds)
                ->orderBy('tier')
                ->orderBy('name')
                ->get()
                ->map(fn ($c) => $this->formatChassis($c, $catalogMeta['chassis:' . $c->id] ?? null));

            $hardware = $hardware->merge($chassis);
        }

        // ── Consumables ───────────────────────────────────────────────────────

        $consumables = collect();

        if (!empty($consumableIds)) {
            $consumables = Consumable::whereIn('id', $consumableIds)
                ->orderBy('category')
                ->orderBy('rarity')
                ->orderBy('name')
                ->get()
                ->map(fn ($c) => $this->formatConsumable($c, $catalogMeta['consumable:' . $c->id] ?? null));
        }

        // ── Commands ──────────────────────────────────────────────────────────

        $commands = collect();

        if (!empty($commandIds)) {
            $commands = Command::whereIn('id', $commandIds)
                ->orderBy('context')
                ->orderBy('name')
                ->get()
                ->map(fn ($cmd) => $this->formatCommand($cmd, $catalogMeta['command:' . $cmd->id] ?? null));
        }

        return [
            'hardware'    => $hardware->values()->all(),
            'consumables' => $consumables->values()->all(),
            'commands'    => $commands->values()->all(),
        ];
    }

    /**
     * Check whether a specific item is currently available at a given doc.
     */
    public function isAvailableAt(string $canvasId, string $itemType, string $itemId): bool
    {
        $cyberDoc = CyberDoc::whereHas('node', fn ($q) => $q->where('canvas_id', $canvasId))->first();
        if ($cyberDoc === null) {
            return false;
        }

        return CyberDocCatalog::where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->where(function ($q) use ($cyberDoc) {
                $q->where('cyber_doc_id', $cyberDoc->id)
                  ->orWhereNull('cyber_doc_id');
            })
            ->active()
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveDoc(string $canvasId): CyberDoc
    {
        $doc = CyberDoc::whereHas(
            'node',
            fn ($q) => $q->where('canvas_id', $canvasId)
        )->first();

        if ($doc === null) {
            throw new RuntimeException(
                "CyberDocInventoryService: no CyberDoc found for canvas_id '{$canvasId}'."
            );
        }

        return $doc;
    }

    private function assertValidItemType(string $itemType): void
    {
        $valid = ['peripheral', 'consumable', 'command', 'chassis'];
        if (!in_array($itemType, $valid, true)) {
            throw new \InvalidArgumentException(
                "Invalid item_type '{$itemType}'. Must be one of: " . implode(', ', $valid) . '.'
            );
        }
    }

    private function formatPeripheral(Peripheral $p, ?CyberDocCatalog $meta): array
    {
        return [
            'id'              => $p->id,
            'category'        => 'hardware',
            'name'            => $p->name,
            'peripheral_type' => $p->peripheral_type ?? 'stat_boost',
            'stat'            => $p->stat_boosted,
            'boost'           => $p->boost_amount,
            'slot_type'       => $p->slot_type,
            'slot_tier'       => $p->slot_tier,
            'rarity'          => $p->rarity,
            'port_cost'       => $p->port_cost,
            'price_creds'     => $p->price_creds,
            'desc'            => null,
            'is_exclusive'    => $meta?->is_exclusive ?? false,
            'stock_limit'     => $meta?->stock_limit,
            'available_until' => $meta?->available_until?->toIso8601String(),
        ];
    }

    private function formatChassis(ChassisTemplate $c, ?CyberDocCatalog $meta): array
    {
        return [
            'id'              => $c->id,
            'category'        => 'chassis',
            'name'            => $c->name,
            'tier'            => $c->tier,
            'rarity'          => 'rare',
            'price_creds'     => $c->price_creds ?? 0,
            'desc'            => null,
            'is_exclusive'    => $meta?->is_exclusive ?? false,
            'stock_limit'     => $meta?->stock_limit,
            'available_until' => $meta?->available_until?->toIso8601String(),
        ];
    }

    private function formatConsumable(Consumable $c, ?CyberDocCatalog $meta): array
    {
        return [
            'id'             => $c->id,
            'category'       => $c->category,
            'name'           => $c->name,
            'stat'           => $c->stat,
            'boost'          => $c->boost_amount,
            'duration_moves' => $c->duration_moves,
            'rarity'         => $c->rarity,
            'price_creds'    => $c->price_creds,
            'desc'           => $c->description,
            'is_exclusive'   => $meta?->is_exclusive ?? false,
            'stock_limit'    => $meta?->stock_limit,
            'available_until' => $meta?->available_until?->toIso8601String(),
        ];
    }

    private function formatCommand(Command $cmd, ?CyberDocCatalog $meta): array
    {
        return [
            'id'              => $cmd->id,
            'category'        => 'command',
            'name'            => $cmd->name,
            'context'         => $cmd->context,
            'type'            => $cmd->type,
            'rarity'          => $cmd->rarity ?? 'common',
            'price_creds'     => $cmd->price_creds ?? 0,
            'price_tp'        => $cmd->price_tp ?? 0,
            'desc'            => null,
            'is_exclusive'    => $meta?->is_exclusive ?? false,
            'stock_limit'     => $meta?->stock_limit,
            'available_until' => $meta?->available_until?->toIso8601String(),
        ];
    }
}
