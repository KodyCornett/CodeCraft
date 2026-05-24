<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorialController extends Controller
{
    /**
     * POST /api/tutorial/reward
     *
     * Credits a quest completion reward directly to wallet_creds.
     * Wallet creds are safe — they cannot be stolen by other players in PvP.
     *
     * Quest completion state is tracked client-side in localStorage.
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
