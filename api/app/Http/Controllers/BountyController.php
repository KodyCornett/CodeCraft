<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\BountyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BountyController extends Controller
{
    public function __construct(private readonly BountyService $bountyService) {}

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
     * Player banks their run at a Street Doc.
     * Resets all bounty counters and pays out the multiplier bonus (stub — cred
     * transfer is handled in Prompt 11 StreetDocService::visitStreetDoc).
     */
    public function extract(Request $request, string $playerId): JsonResponse
    {
        $player = Player::find($playerId);

        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $multiplierAtExtract = (float) $player->bounty_multiplier;
        $bountyLevelAtExtract = (int) $player->bounty_level;

        $this->bountyService->extractToStreetDoc($player);

        return response()->json([
            'message'               => 'Run extracted successfully.',
            'multiplier_at_extract' => $multiplierAtExtract,
            'bounty_level_banked'   => $bountyLevelAtExtract,
            'player' => [
                'player_id'            => $player->id,
                'bounty_level'         => $player->bounty_level,
                'nodes_hacked_this_run' => $player->nodes_hacked_this_run,
                'pvp_wins_this_run'    => $player->pvp_wins_this_run,
                'bounty_multiplier'    => $player->bounty_multiplier,
                'is_open_season'       => $player->is_open_season,
            ],
        ]);
    }
}
