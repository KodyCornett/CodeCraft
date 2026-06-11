<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorialController extends Controller
{
    /**
     * GET /api/tutorial/state
     *
     * Returns the player's current tutorial_state JSON blob.
     * null means the player has never interacted with the tutorial.
     */
    public function state(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();

        if (! $player) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        return response()->json([
            'tutorial_state' => $player->tutorial_state,
        ]);
    }

    /**
     * PATCH /api/tutorial/state
     *
     * Persists the full tutorial_state blob sent by the client.
     * The client is the source of truth for tutorial UI state — the server
     * just stores and returns it.
     */
    public function updateState(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tutorial_state'                  => ['required', 'array'],
            'tutorial_state.tutorialSeen'     => ['boolean'],
            'tutorial_state.tutorialSkipped'  => ['boolean'],
            'tutorial_state.stepsDone'        => ['array'],
            'tutorial_state.questsRewarded'   => ['array'],
            'tutorial_state.hasBadge'         => ['boolean'],
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();

        if (! $player) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $player->update(['tutorial_state' => $data['tutorial_state']]);

        return response()->json([
            'tutorial_state' => $player->fresh()->tutorial_state,
        ]);
    }

    /**
     * POST /api/tutorial/reward
     *
     * Credits a quest completion reward directly to wallet_creds.
     * Wallet creds are safe — they cannot be stolen by other players in PvP.
     *
     * This endpoint trusts the client's reported quest ID and amount
     * (both validated within allowed ranges) since tutorial rewards are low-stakes.
     */
    public function reward(Request $request): JsonResponse
    {
        $data = $request->validate([
            'quest_id' => ['required', 'string', 'max:64'],
            'amount'   => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();

        if (! $player) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $player->increment('wallet_creds', $data['amount']);

        return response()->json([
            'wallet_creds' => $player->fresh()->wallet_creds,
            'quest_id'     => $data['quest_id'],
        ]);
    }
}
