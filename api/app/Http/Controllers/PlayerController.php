<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\RigService;
use Illuminate\Http\JsonResponse;

class PlayerController extends Controller
{
    public function __construct(private readonly RigService $rigService) {}

    /**
     * GET /api/player/{player_id}/status
     *
     * Primary endpoint for the Kotlin engine to fetch full player state.
     * Returns player position/status and the complete rig snapshot in one call.
     */
    public function status(string $playerId): JsonResponse
    {
        $player = Player::find($playerId);

        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $rig = $this->rigService->getRigForPlayer($player);

        return response()->json([
            'player' => [
                'id'                       => $player->id,
                'handle'                   => $player->handle,
                'current_node_id'          => $player->current_node_id,
                'current_district'         => $player->current_district,
                'bounty_level'             => $player->bounty_level,
                'is_open_season'           => $player->is_open_season,
                'is_limping'               => $player->is_limping,
                'post_combat_silent_moves' => $player->post_combat_silent_moves,
                'last_street_doc_id'       => $player->last_street_doc_id,
            ],
            'rig' => $rig ? [
                'rig_id'     => $rig->id,
                'chassis'    => $rig->chassis->name,
                'is_limping' => $rig->is_limping,
                'current_ss' => $rig->current_ss,
                'max_ss'     => $this->rigService->maxSs($rig),
                'stats'      => $this->rigService->effectiveStats($rig, $player),
                'points'     => [
                    'spent' => $this->rigService->totalPointsSpent($rig),
                    'cap'   => $rig->chassis->total_point_cap,
                ],
            ] : null,
        ]);
    }
}
