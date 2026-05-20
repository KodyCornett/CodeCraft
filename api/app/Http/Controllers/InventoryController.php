<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * GET /api/inventory
     *
     * Returns the authenticated player's current inventory:
     *   hardware    — uninstalled HardwareEncrypt records (purchased, awaiting installation)
     *   consumables — owned consumables with remaining quantity
     */
    public function index(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();

        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $hardware = $player->hardwareEncrypts()
            ->where('is_installed', false)
            ->with('peripheral')
            ->get()
            ->map(fn ($e) => [
                'encrypt_id'   => $e->id,
                'peripheral_id' => $e->peripheral_id,
                'name'         => $e->peripheral->name,
                'stat'         => $e->peripheral->stat_boosted,
                'boost'        => $e->peripheral->boost_amount,
                'rarity'       => $e->peripheral->rarity,
                'port_cost'    => $e->peripheral->port_cost,
            ]);

        $consumables = $player->playerConsumables()
            ->where('quantity', '>', 0)
            ->with('consumable')
            ->get()
            ->map(fn ($pc) => [
                'consumable_id'  => $pc->consumable_id,
                'name'           => $pc->consumable->name,
                'category'       => $pc->consumable->category,
                'stat'           => $pc->consumable->stat,
                'boost'          => $pc->consumable->boost_amount,
                'duration_moves' => $pc->consumable->duration_moves,
                'rarity'         => $pc->consumable->rarity,
                'quantity'       => $pc->quantity,
            ]);

        return response()->json([
            'hardware'    => $hardware,
            'consumables' => $consumables,
        ]);
    }

    /**
     * POST /api/inventory/use
     *
     * Apply a consumable from the player's inventory.
     * Decrements quantity (deletes the row when it reaches zero).
     *
     * Body: { consumable_id }
     *
     * Repair response:   { type, ss_restored, current_ss, max_ss, wallet_creds }
     * Software response: { type, stat, boost_amount, moves_remaining, active_effects, wallet_creds }
     */
    public function use(Request $request): JsonResponse
    {
        $data = $request->validate([
            'consumable_id' => 'required|uuid',
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();

        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        try {
            $result = $this->inventoryService->useConsumable($player, $data['consumable_id']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
