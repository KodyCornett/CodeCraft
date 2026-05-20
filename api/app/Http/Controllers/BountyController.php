<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\BountyService;
use App\Services\CyberDocService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BountyController extends Controller
{
    public function __construct(
        private readonly BountyService   $bountyService,
        private readonly CyberDocService $cyberDocService,
    ) {}

    /**
     * GET /api/leaderboard/bounty
     *
     * Returns all players currently on the bounty board, sorted by bounty level.
     */
    public function bountyLeaderboard(): JsonResponse
    {
        $board = $this->bountyService->getBountyLeaderboard();

        return response()->json([
            'leaderboard' => $board->map(fn ($p) => [
                'player_id'               => $p->id,
                'handle'                  => $p->handle,
                'bounty_level'            => $p->bounty_level,
                'current_district'        => $p->bounty_district_snapshot,
                'nodes_hacked'            => $p->nodes_hacked_this_run,
                'pvp_wins_this_run'       => $p->pvp_wins_this_run,
                'bounty_multiplier'       => $p->bounty_multiplier,
                'is_open_season'          => $p->is_open_season,
                'pocket_creds'            => (int) ($p->pocket_creds ?? 0),
            ]),
        ]);
    }

    /**
     * GET /api/leaderboard/open-season
     *
     * Returns the all-time Open Season hall of fame.
     */
    public function openSeasonHallOfFame(): JsonResponse
    {
        $fame = $this->bountyService->getOpenSeasonHallOfFame();

        return response()->json([
            'hall_of_fame' => $fame->map(fn ($p) => [
                'player_id'            => $p->id,
                'handle'               => $p->handle,
                'best_open_season_wins' => $p->open_season_best_wins,
            ]),
        ]);
    }

    /**
     * POST /api/player/{player_id}/extract
     *
     * Player banks their run at the CyberDoc.
     * Transfers pocket_creds to safe wallet, resets all bounty/run counters,
     * and restores uplink for the next run.
     *
     * Called by the Kotlin engine when the player physically reaches a CyberDoc.
     * Mirrors what POST /api/cyberdoc/bank does for the SPA.
     */
    public function extract(Request $request, string $playerId): JsonResponse
    {
        $player = Player::find($playerId);

        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        // Ownership check — the session user must own this player.
        // When the Kotlin engine is active it authenticates via Bearer token
        // with its own user account; revisit this check at that point.
        $sessionPlayer = Player::where('user_id', $request->user()->id)->value('id');
        if ($sessionPlayer !== $player->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $multiplierAtExtract  = (float) $player->bounty_multiplier;
        $bountyLevelAtExtract = (int)   $player->bounty_level;

        // bankCreds(): pocket → wallet, reset run state, restore uplink
        $result = $this->cyberDocService->bankCreds($player);
        $fresh  = $player->fresh();

        return response()->json([
            'message'               => 'Run extracted successfully.',
            'pocket_banked'         => $result['pocket_banked'],
            'multiplier_at_extract' => $multiplierAtExtract,
            'bounty_level_banked'   => $bountyLevelAtExtract,
            'player' => [
                'player_id'             => $fresh->id,
                'wallet_creds'          => (int)   ($fresh->wallet_creds  ?? 0),
                'pocket_creds'          => (int)   ($fresh->pocket_creds  ?? 0),
                'bounty_level'          => $fresh->bounty_level,
                'nodes_hacked_this_run' => $fresh->nodes_hacked_this_run,
                'pvp_wins_this_run'     => $fresh->pvp_wins_this_run,
                'bounty_multiplier'     => $fresh->bounty_multiplier,
                'is_open_season'        => $fresh->is_open_season,
            ],
        ]);
    }
}
