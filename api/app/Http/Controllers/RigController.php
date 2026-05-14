<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\RigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RigController extends Controller
{
    public function __construct(private readonly RigService $rigService) {}

    /**
     * GET /api/rig?player_id={uuid}
     *
     * Returns the rig's current effective stats for the given player,
     * including per-stat peripheral boosts and total point accounting.
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate(['player_id' => 'required|uuid']);

        $player = Player::find($request->player_id);

        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $rig = $this->rigService->getRigForPlayer($player);

        if ($rig === null) {
            return response()->json(['message' => 'No rig found for this player.'], 404);
        }

        return response()->json([
            'rig_id'      => $rig->id,
            'chassis'     => $rig->chassis->name,
            'is_limping'  => $rig->is_limping,
            'current_ss'  => $rig->current_ss,
            'max_ss'      => $this->rigService->maxSs($rig),
            'stats'       => $this->rigService->effectiveStats($rig, $player),
            'points'      => [
                'spent' => $this->rigService->totalPointsSpent($rig),
                'cap'   => $rig->chassis->total_point_cap,
            ],
        ]);
    }

    /**
     * POST /api/rig/damage
     *
     * Reduces the rig's current_ss by the given amount.
     *
     * Request body:
     *   player_id  string (uuid)   — the player whose rig takes the hit
     *   amount     int (min:1)     — damage to apply
     *   source     string          — 'pve' or 'pvp'
     *
     * Response includes the updated rig snapshot and a nullable `event` field:
     *   null              — SS reduced but rig still standing
     *   'limp_mode'       — SS hit 0 from PvE; rig is at 1 SS, is_limping = true
     *   'street_doc_reset'— SS hit 0 from PvP; rig restored to full SS, limp cleared
     */
    public function damage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id' => 'required|uuid',
            'amount'    => 'required|integer|min:1',
            'source'    => 'required|string|in:pve,pvp',
        ]);

        $player = Player::find($data['player_id']);

        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $rig = $this->rigService->getRigForPlayer($player);

        if ($rig === null) {
            return response()->json(['message' => 'No rig found for this player.'], 404);
        }

        ['rig' => $rig, 'event' => $event] = $this->rigService->applyDamage(
            $rig,
            $data['amount'],
            $data['source'],
            $player,
        );

        return response()->json([
            'event'      => $event,
            'rig_id'     => $rig->id,
            'is_limping' => $rig->is_limping,
            'current_ss' => $rig->current_ss,
            'max_ss'     => $this->rigService->maxSs($rig),
        ]);
    }

    /**
     * POST /api/rig/upgrade
     *
     * Upgrades one stat level on the player's rig.
     * If the chassis point cap is reached the Parasite Stat is automatically
     * taxed; the tax event is returned so the client can animate the change.
     *
     * Request body:
     *   player_id  string (uuid)
     *   stat       string — one of: cpu, ram, firewall, storage, os
     *
     * 422 returned when:
     *   - stat is already at the chassis maximum
     *   - all other stats are at minimum (tax impossible)
     *   - player has insufficient creds (stubbed — always passes for now)
     */
    public function upgrade(Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id' => 'required|uuid',
            'stat'      => 'required|string|in:cpu,ram,firewall,storage,os',
        ]);

        $player = Player::find($data['player_id']);
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig === null) {
            return response()->json(['message' => 'No rig found for this player.'], 404);
        }

        if (!$this->rigService->hasEnoughCredsForUpgrade($player)) {
            return response()->json(['message' => 'Insufficient creds to upgrade this stat.'], 422);
        }

        try {
            ['rig' => $rig, 'tax' => $tax] = $this->rigService->upgradeStat($rig, $data['stat']);
        } catch (\OverflowException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'stat_upgraded' => $data['stat'],
            'tax_event'     => $tax,
            'rig_id'        => $rig->id,
            'current_ss'    => $rig->current_ss,
            'max_ss'        => $this->rigService->maxSs($rig),
            'stats'         => $this->rigService->effectiveStats($rig, $player),
            'points'        => [
                'spent' => $this->rigService->totalPointsSpent($rig),
                'cap'   => $rig->chassis->total_point_cap,
            ],
        ]);
    }

    /**
     * POST /api/rig/repair
     *
     * Restores the player's rig SS to its maximum value.
     * Optionally repairs all damaged peripherals.
     *
     * Request body:
     *   player_id          string (uuid)
     *   repair_peripherals boolean (optional, default false)
     */
    public function repair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id'          => 'required|uuid',
            'repair_peripherals' => 'boolean',
        ]);

        $player = Player::find($data['player_id']);
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig === null) {
            return response()->json(['message' => 'No rig found for this player.'], 404);
        }

        $rig = $this->rigService->repair(
            $rig,
            $player,
            (bool) ($data['repair_peripherals'] ?? false),
        );

        return response()->json([
            'rig_id'     => $rig->id,
            'current_ss' => $rig->current_ss,
            'max_ss'     => $this->rigService->maxSs($rig),
        ]);
    }
}
