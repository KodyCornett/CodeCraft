<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\BountyService;
use App\Services\RigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles combat resolution callbacks from the Kotlin engine.
 *
 * The engine resolves combat state. When a winner is determined it calls
 * POST /api/combat/result so Laravel can persist the consequences:
 *   - PvP damage applied to the loser's rig (SS drop, potential stat loss)
 *   - post_combat_silent_moves set to 2 on both players
 *   - Bounty/open-season events recorded for the winner
 *   - Steal percentage calculated and returned so the engine can apply it
 */
class CombatController extends Controller
{
    public function __construct(
        private readonly RigService    $rigService,
        private readonly BountyService $bountyService,
    ) {}

    /**
     * POST /api/combat/result
     *
     * Body:
     *   winner_id           string (UUID)
     *   loser_id            string (UUID)
     *   node_id             string
     *   stolen_cred_amount  int    (optional, default 0)
     *   stolen_item_id      string (UUID, nullable)
     */
    public function result(Request $request): JsonResponse
    {
        $data = $request->validate([
            'winner_id'          => 'required|uuid',
            'loser_id'           => 'required|uuid',
            'node_id'            => 'required|string',
            'stolen_cred_amount' => 'sometimes|integer|min:0',
            'stolen_item_id'     => 'sometimes|nullable|uuid',
        ]);

        $winner = Player::find($data['winner_id']);
        $loser  = Player::find($data['loser_id']);

        if ($winner === null) {
            return response()->json(['message' => 'Winner player not found.'], 404);
        }
        if ($loser === null) {
            return response()->json(['message' => 'Loser player not found.'], 404);
        }

        // --- Calculate steal percentage BEFORE resetting loser state ---
        $stealPercentage = $this->bountyService->calculateStealPercentage($loser);

        // --- Apply PvP damage to the loser ---
        $loserRig    = $this->rigService->getRigForPlayer($loser);
        $damageEvent = null;

        if ($loserRig !== null) {
            $damageResult = $this->rigService->applyDamage(
                rig:    $loserRig,
                amount: $loserRig->current_ss,
                source: 'pvp',
                player: $loser,
            );
            $loserRig    = $damageResult['rig'];
            $damageEvent = $damageResult['event'];
        }

        // --- Post-combat silent mode ---
        $winner->post_combat_silent_moves = 2;
        $loser->post_combat_silent_moves  = 2;
        $winner->save();
        $loser->save();

        // --- Bounty tracking ---
        $bountyEvent = $this->bountyService->recordPvpWin($winner);

        // --- Build response ---
        $winnerEffective = $winner->rig
            ? $this->rigService->effectiveStats($winner->rig, $winner)
            : null;
        $loserEffective = $loserRig
            ? $this->rigService->effectiveStats($loserRig, $loser)
            : null;

        return response()->json([
            'steal_percentage' => $stealPercentage,
            'bounty_event'     => [
                'type' => $bountyEvent->type,
                'data' => $bountyEvent->data,
            ],
            'winner' => [
                'player_id'                => $winner->id,
                'handle'                   => $winner->handle,
                'post_combat_silent_moves' => $winner->post_combat_silent_moves,
                'bounty_level'             => $winner->bounty_level,
                'bounty_multiplier'        => $winner->bounty_multiplier,
                'is_open_season'           => $winner->is_open_season,
                'effective_stats'          => $winnerEffective,
            ],
            'loser' => [
                'player_id'                => $loser->id,
                'handle'                   => $loser->handle,
                'post_combat_silent_moves' => $loser->post_combat_silent_moves,
                'is_limping'               => $loser->is_limping,
                'effective_stats'          => $loserEffective,
                'damage_event'             => $damageEvent,
            ],
        ]);
    }
}
