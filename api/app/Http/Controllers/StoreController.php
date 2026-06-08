<?php

namespace App\Http\Controllers;

use App\Models\Command;
use App\Models\Consumable;
use App\Models\Node;
use App\Models\Peripheral;
use App\Models\Player;
use App\Services\CyberDocInventoryService;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function __construct(
        private readonly CyberDocInventoryService $cyberDocInventory,
        private readonly InventoryService         $inventoryService,
    ) {}

    /**
     * Assert the player is physically at a CyberDoc node.
     * Returns a 403 JsonResponse if not, or null if the check passes.
     */
    private function assertAtCyberDoc(Player $player): ?JsonResponse
    {
        $node = Node::find($player->current_node_id);
        if ($node === null || $node->type !== 'cyberdoc') {
            return response()->json(['message' => 'You must be at a CyberDoc terminal.'], 403);
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Catalog
    // -------------------------------------------------------------------------

    /**
     * GET /api/store/catalog
     *
     * Returns the purchasable catalog. Behaviour depends on whether a
     * cyberdoc_canvas_id is provided:
     *
     *   With cyberdoc_canvas_id (e.g. 'NS-hub'):
     *     Returns only items registered in street_doc_catalog for that terminal
     *     — global items (repair consumables etc.) + doc-specific items.
     *     This is the normal in-game path once the browser navigates to a doc.
     *
     *   Without cyberdoc_canvas_id:
     *     Returns the full unfiltered catalog (all peripherals + all consumables).
     *     Preserved for backward compatibility and the generic fallback store page.
     *
     * Query params:
     *   cyberdoc_canvas_id  string|null  Hub canvas_id (e.g. 'NS-hub'). Optional.
     */
    public function catalog(Request $request): JsonResponse
    {
        $canvasId = $request->query('cyberdoc_canvas_id');

        // ── Doc-filtered catalog ──────────────────────────────────────────────
        if ($canvasId !== null) {
            try {
                $catalog = $this->cyberDocInventory->catalogForDoc($canvasId);
            } catch (\RuntimeException $e) {
                // Unknown canvas_id — fall through to the global catalog rather
                // than returning a hard error (doc may not be seeded yet in dev).
                $catalog = null;
            }

            if ($catalog !== null) {
                return response()->json($catalog);
            }
        }

        // ── Global fallback catalog (original behaviour) ──────────────────────
        $hardware = Peripheral::orderBy('rarity')->orderBy('name')->get()->map(fn ($p) => [
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
        ]);

        $consumables = Consumable::orderBy('category')->orderBy('rarity')->orderBy('name')->get()->map(fn ($c) => [
            'id'             => $c->id,
            'category'       => $c->category,
            'name'           => $c->name,
            'stat'           => $c->stat,
            'boost'          => $c->boost_amount,
            'duration_moves' => $c->duration_moves,
            'rarity'         => $c->rarity,
            'price_creds'    => $c->price_creds,
            'desc'           => $c->description,
        ]);

        return response()->json([
            'hardware'    => $hardware,
            'consumables' => $consumables,
        ]);
    }

    // -------------------------------------------------------------------------
    // Hardware purchase
    // -------------------------------------------------------------------------

    /**
     * POST /api/store/purchase-peripheral
     *
     * Purchases a peripheral and places it in the player's uninstalled inventory.
     * Installation is a separate step via POST /api/cyberdoc/install.
     *
     * Body: { player_id, peripheral_id }
     */
    public function purchasePeripheral(Request $request): JsonResponse
    {
        $data = $request->validate([
            'peripheral_id' => 'required|uuid',
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        if ($err = $this->assertAtCyberDoc($player)) return $err;

        try {
            $encrypt = $this->inventoryService->purchasePeripheral($player, $data['peripheral_id']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $fresh = $player->fresh();

        return response()->json([
            'message'       => 'Peripheral purchased.',
            'encrypt_id'    => $encrypt->id,
            'peripheral_id' => $encrypt->peripheral_id,
            'wallet_creds'  => (int) $fresh->wallet_creds,
        ]);
    }

    // -------------------------------------------------------------------------
    // Consumable purchase
    // -------------------------------------------------------------------------

    /**
     * POST /api/store/purchase-consumable
     *
     * Purchases a consumable and increments the player's quantity.
     *
     * Body: { player_id, consumable_id }
     */
    public function purchaseConsumable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'consumable_id' => 'required|uuid',
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        if ($err = $this->assertAtCyberDoc($player)) return $err;

        try {
            $row = $this->inventoryService->purchaseConsumable($player, $data['consumable_id']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $fresh = $player->fresh();

        return response()->json([
            'message'       => 'Consumable purchased.',
            'consumable_id' => $row->consumable_id,
            'quantity'      => $row->quantity,
            'wallet_creds'  => (int) $fresh->wallet_creds,
        ]);
    }

    // -------------------------------------------------------------------------
    // Command purchase (existing — unchanged)
    // -------------------------------------------------------------------------

    /**
     * POST /api/store/purchase-command
     *
     * Purchase a command from the CyberDoc store.
     * Deducts price_creds from wallet_creds and price_tp from tech_points.
     * Creates a player_commands row at level 1, inactive by default.
     *
     * Body: { player_id, command_id }
     */
    public function purchaseCommand(Request $request): JsonResponse
    {
        $data = $request->validate([
            'command_id' => 'required|uuid',
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        if ($err = $this->assertAtCyberDoc($player)) return $err;

        $command = Command::find($data['command_id']);
        if ($command === null) {
            return response()->json(['message' => 'Command not found.'], 404);
        }

        // Already owned?
        $alreadyOwned = DB::table('player_commands')
            ->where('player_id', $player->id)
            ->where('command_id', $command->id)
            ->exists();

        if ($alreadyOwned) {
            return response()->json(['message' => 'Command already owned.'], 422);
        }

        // Afford check
        $credCost = (int) $command->price_creds;
        $tpCost   = (int) $command->price_tp;

        if (($player->wallet_creds ?? 0) < $credCost) {
            return response()->json([
                'message' => "Insufficient creds. Need {$credCost}, have " . ($player->wallet_creds ?? 0) . '.',
            ], 422);
        }

        if (($player->tech_points ?? 0) < $tpCost) {
            return response()->json([
                'message' => "Insufficient Tech Points. Need {$tpCost}, have " . ($player->tech_points ?? 0) . '.',
            ], 422);
        }

        DB::transaction(function () use ($player, $command, $credCost, $tpCost) {
            $player->wallet_creds = (int) ($player->wallet_creds ?? 0) - $credCost;
            $player->tech_points  = round((float) ($player->tech_points ?? 0) - $tpCost, 2);
            $player->save();

            DB::table('player_commands')->insert([
                'player_id'  => $player->id,
                'command_id' => $command->id,
                'is_active'  => false,
                'level'      => 1,
            ]);
        });

        $fresh = $player->fresh();

        return response()->json([
            'message'      => "Command '{$command->name}' purchased.",
            'command_id'   => $command->id,
            'wallet_creds' => (int) $fresh->wallet_creds,
            'tech_points'  => (float) $fresh->tech_points,
        ]);
    }
}
