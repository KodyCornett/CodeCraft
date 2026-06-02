<?php

namespace App\Services;

use App\Models\Consumable;
use App\Models\HardwareEncrypt;
use App\Models\Peripheral;
use App\Models\Player;
use App\Models\PlayerConsumable;
use App\Models\PlayerRig;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * All inventory purchase and consumption logic.
 *
 * Rules:
 *  - Hardware purchases create an uninstalled HardwareEncrypt record.
 *    Installation is handled separately by CyberDocService::installEncrypt().
 *  - Consumable purchases upsert a PlayerConsumable quantity row.
 *  - Using a consumable applies its effect and decrements the quantity (deletes at 0).
 *  - Repair kits (stat = 'ss') restore SS via RigService::repairPartial().
 *  - Software items (duration_moves > 0) register a move-counted active_effect on the player.
 */
class InventoryService
{
    /** Key prefix for software consumable active effects stored on the player. */
    private const SW_PREFIX = 'sw_';

    public function __construct(
        private readonly RigService $rigService,
    ) {}

    // -------------------------------------------------------------------------
    // Hardware — peripheral purchase
    // -------------------------------------------------------------------------

    /**
     * Purchase a peripheral from the store catalog.
     * Deducts price_creds from wallet_creds.
     * Creates an uninstalled HardwareEncrypt record (item is in inventory, not yet slotted).
     *
     * Players may own multiple uninstalled copies; no duplicate guard is applied.
     *
     * @throws InvalidArgumentException When the peripheral is not found.
     * @throws RuntimeException         When the player cannot afford it.
     */
    public function purchasePeripheral(Player $player, string $peripheralId): HardwareEncrypt
    {
        $peripheral = Peripheral::find($peripheralId);

        if ($peripheral === null) {
            throw new InvalidArgumentException("Peripheral '{$peripheralId}' not found.");
        }

        $cost = (int) $peripheral->price_creds;

        if (($player->wallet_creds ?? 0) < $cost) {
            throw new RuntimeException(
                "Insufficient creds. Need {$cost}, have " . ($player->wallet_creds ?? 0) . '.'
            );
        }

        return DB::transaction(function () use ($player, $peripheral, $cost) {
            $player->wallet_creds = (int) ($player->wallet_creds ?? 0) - $cost;
            $player->save();

            return HardwareEncrypt::create([
                'player_id'     => $player->id,
                'peripheral_id' => $peripheral->id,
                'is_installed'  => false,
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Consumables — purchase
    // -------------------------------------------------------------------------

    /**
     * Purchase a consumable from the store catalog.
     * Deducts price_creds from wallet_creds.
     * Upserts the player_consumables row — increments quantity if already owned.
     *
     * @throws InvalidArgumentException When the consumable is not found.
     * @throws RuntimeException         When the player cannot afford it.
     */
    public function purchaseConsumable(Player $player, string $consumableId): PlayerConsumable
    {
        $consumable = Consumable::find($consumableId);

        if ($consumable === null) {
            throw new InvalidArgumentException("Consumable '{$consumableId}' not found.");
        }

        $cost = (int) $consumable->price_creds;

        if (($player->wallet_creds ?? 0) < $cost) {
            throw new RuntimeException(
                "Insufficient creds. Need {$cost}, have " . ($player->wallet_creds ?? 0) . '.'
            );
        }

        return DB::transaction(function () use ($player, $consumable, $cost) {
            $player->wallet_creds = (int) ($player->wallet_creds ?? 0) - $cost;
            $player->save();

            $row = PlayerConsumable::firstOrNew([
                'player_id'     => $player->id,
                'consumable_id' => $consumable->id,
            ]);
            $row->quantity = ($row->quantity ?? 0) + 1;
            $row->save();

            return $row;
        });
    }

    // -------------------------------------------------------------------------
    // Consumables — use
    // -------------------------------------------------------------------------

    /**
     * Apply a consumable and decrement its quantity.
     *
     * Repair kits (stat = 'ss'):
     *   Restores up to boost_amount SS via RigService::repairPartial().
     *   Returns: [ type, ss_restored, current_ss, max_ss ]
     *
     * Software (duration_moves > 0):
     *   Writes a move-counted effect to player.active_effects under key 'sw_{stat}'.
     *   Stacks with any existing count (e.g. using two Scan Patches adds duration).
     *   Returns: [ type, stat, boost_amount, moves_remaining, active_effects ]
     *
     * @throws InvalidArgumentException When the consumable row is not found or quantity is 0.
     * @throws RuntimeException         When a repair kit is used but the player has no rig.
     */
    public function useConsumable(Player $player, string $consumableId): array
    {
        $row = PlayerConsumable::with('consumable')
            ->where('player_id', $player->id)
            ->where('consumable_id', $consumableId)
            ->first();

        if ($row === null || $row->quantity <= 0) {
            throw new InvalidArgumentException('Consumable not found in player inventory.');
        }

        $consumable = $row->consumable;

        // Decrement quantity and apply the effect in a single transaction so that
        // a failure in applyRepair/applySoftware rolls back the quantity decrement —
        // the player cannot lose the consumable without receiving its benefit.
        return DB::transaction(function () use ($player, $row, $consumable) {
            if ($row->quantity <= 1) {
                $row->delete();
            } else {
                $row->decrement('quantity');
            }

            return $consumable->stat === 'ss'
                ? $this->applyRepair($player, $consumable->boost_amount)
                : $this->applySoftware($player, $consumable->stat, $consumable->boost_amount, (int) $consumable->duration_moves);
        });
    }

    // -------------------------------------------------------------------------
    // Private effect applicators
    // -------------------------------------------------------------------------

    private function applyRepair(Player $player, int $amount): array
    {
        $rig = $this->rigService->getRigForPlayer($player);

        if ($rig === null) {
            throw new RuntimeException('Player has no rig to repair.');
        }

        $restored = $this->rigService->repairPartial($rig, $amount, $player);

        // Refresh player to get the updated is_limping value after potential save
        $freshPlayer = $player->fresh();

        return [
            'type'        => 'repair',
            'ss_restored' => $restored,
            'current_ss'  => (int) $rig->current_ss,
            'max_ss'      => $this->rigService->maxSs($rig),
            'is_limping'  => (bool) ($freshPlayer->is_limping ?? false),
        ];
    }

    private function applySoftware(Player $player, string $stat, int $boost, int $durationMoves): array
    {
        $key    = self::SW_PREFIX . $stat;
        $effects = $player->active_effects ?? [];

        // Stack onto any existing duration for the same stat
        $effects[$key] = ($effects[$key] ?? 0) + $durationMoves;

        $player->active_effects = $effects;
        $player->save();

        return [
            'type'            => 'software',
            'stat'            => $stat,
            'boost_amount'    => $boost,
            'moves_remaining' => $effects[$key],
            'active_effects'  => $effects,
        ];
    }
}
