<?php

namespace App\Services;

use App\Models\ChassisTemplate;
use App\Models\Command;
use App\Models\Consumable;
use App\Models\Node;
use App\Models\Peripheral;
use App\Models\StreetDoc;
use App\Models\StreetDocCatalog;
use Carbon\Carbon;
use RuntimeException;

/**
 * StreetDocInventoryService
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
class StreetDocInventoryService
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
     * @throws RuntimeException When canvasId is given but no matching StreetDoc exists.
     */
    public function grantCatalogItem(
        ?string $canvasId,
        string  $itemType,
        string  $itemId,
        array   $options = []
    ): StreetDocCatalog {
        $this->assertValidItemType($itemType);

        $streetDocId = null;

        if ($canvasId !== null) {
            $streetDoc = $this->resolveDoc($canvasId);
            $streetDocId = $streetDoc->id;
        }

        $availableUntil = $options['available_until'] ?? null;
        if ($availableUntil !== null && !$availableUntil instanceof Carbon) {
            $availableUntil = Carbon::parse($availableUntil);
        }

        /** @var StreetDocCatalog $row */
        $row = StreetDocCatalog::updateOrCreate(
            [
                'street_doc_id' => $streetDocId,
                'item_type'     => $itemType,
                'item_id'       => $itemId,
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
     * Useful for cleaning up mission-granted items after an arc ends:
     *   $service->revokeBySource('mission:vault_run_01');
     *
     * @param string      $source    The source label to delete.
     * @param string|null $canvasId  Scope to one doc. null = all docs.
     */
    public function revokeBySource(string $source, ?string $canvasId = null): int
    {
        $query = StreetDocCatalog::where('source', $source);

        if ($canvasId !== null) {
            $streetDoc = $this->resolveDoc($canvasId);
            $query->where('street_doc_id', $streetDoc->id);
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
     *   - Global items (street_doc_id IS NULL) that are not expired
     *   - Doc-specific items for this terminal that are not expired
     *
     * Returns an array shaped identically to StoreController::catalog() so the
     * controller can swap in this result without reformatting.
     *
     * @param  string $canvasId  Hub canvas_id (e.g. 'NS-hub').
     * @return array{hardware: array, consumables: array, commands: array}
     *
     * @throws RuntimeException When no StreetDoc exists for the given canvasId.
     */
    public function catalogForDoc(string $canvasId): array
    {
        $streetDoc = $this->resolveDoc($canvasId);

        $entries = StreetDocCatalog::forDoc($streetDoc->id)
            ->active()
            ->get();

        // Partition by item_type so we can batch-load each source table once.
        $byType = $entries->groupBy('item_type');

        $peripheralIds = $byType->get('peripheral', collect())->pluck('item_id')->all();
        $consumableIds = $byType->get('consumable', collect())->pluck('item_id')->all();
        $commandIds    = $byType->get('command',    collect())->pluck('item_id')->all();
        $chassisIds    = $byType->get('chassis',    collect())->pluck('item_id')->all();

        // Build the catalog entry map so we can attach stock_limit / is_exclusive
        // metadata to each item if needed in the future.
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
     * Used by purchase endpoints to validate doc-restricted items.
     *
     * @param string $canvasId   Hub canvas_id.
     * @param string $itemType   'peripheral' | 'consumable' | 'command' | 'chassis'
     * @param string $itemId     UUID of the item.
     */
    public function isAvailableAt(string $canvasId, string $itemType, string $itemId): bool
    {
        $streetDoc = StreetDoc::whereHas('node', fn ($q) => $q->where('canvas_id', $canvasId))->first();
        if ($streetDoc === null) {
            return false;
        }

        return StreetDocCatalog::where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->where(function ($q) use ($streetDoc) {
                $q->where('street_doc_id', $streetDoc->id)
                  ->orWhereNull('street_doc_id');
            })
            ->active()
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve a StreetDoc by hub canvas_id.
     *
     * @throws RuntimeException
     */
    private function resolveDoc(string $canvasId): StreetDoc
    {
        $doc = StreetDoc::whereHas(
            'node',
            fn ($q) => $q->where('canvas_id', $canvasId)
        )->first();

        if ($doc === null) {
            throw new RuntimeException(
                "StreetDocInventoryService: no StreetDoc found for canvas_id '{$canvasId}'."
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

    // ── Item formatters — mirror StoreController shape exactly ───────────────

    private function formatPeripheral(Peripheral $p, ?StreetDocCatalog $meta): array
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

    private function formatChassis(ChassisTemplate $c, ?StreetDocCatalog $meta): array
    {
        return [
            'id'           => $c->id,
            'category'     => 'chassis',
            'name'         => $c->name,
            'tier'         => $c->tier,
            'rarity'       => 'rare',
            'price_creds'  => $c->price_creds ?? 0,
            'desc'         => null,
            'is_exclusive' => $meta?->is_exclusive ?? false,
            'stock_limit'  => $meta?->stock_limit,
            'available_until' => $meta?->available_until?->toIso8601String(),
        ];
    }

    private function formatConsumable(Consumable $c, ?StreetDocCatalog $meta): array
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

    private function formatCommand(Command $cmd, ?StreetDocCatalog $meta): array
    {
        return [
            'id'           => $cmd->id,
            'category'     => 'command',
            'name'         => $cmd->name,
            'context'      => $cmd->context,
            'type'         => $cmd->type,
            'rarity'       => $cmd->rarity ?? 'common',
            'price_creds'  => $cmd->price_creds ?? 0,
            'price_tp'     => $cmd->price_tp ?? 0,
            'desc'         => null,
            'is_exclusive' => $meta?->is_exclusive ?? false,
            'stock_limit'  => $meta?->stock_limit,
            'available_until' => $meta?->available_until?->toIso8601String(),
        ];
    }
}
