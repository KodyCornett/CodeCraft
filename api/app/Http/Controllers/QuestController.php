<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\QuestStage;
use App\Services\QuestLogService;
use App\Services\QuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestController extends Controller
{
    public function __construct(
        private readonly QuestService    $questService,
        private readonly QuestLogService $questLogService,
    ) {}

    /**
     * GET /api/quests
     *
     * Returns the full quest + reputation state for the authenticated player.
     * This is the primary payload consumed by the Splice quest log terminal.
     */
    public function index(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        return response()->json($this->questService->getPlayerQuestState($player));
    }

    /**
     * POST /api/quests/stage/{stageId}/complete
     *
     * Marks a quest stage as complete for the authenticated player.
     *
     * Body (optional for branch stages):
     *   { "turned_into_doc_id": "uuid" }
     *
     * For branch stages, turned_into_doc_id routes the rep reward to the chosen doc.
     * If omitted on a non-branch stage, rep goes to the arc's owning doc.
     */
    public function completeStage(Request $request, string $stageId): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $stage = QuestStage::find($stageId);
        if ($stage === null) {
            return response()->json(['message' => 'Stage not found.'], 404);
        }

        $turnedIntoDocId = null;
        if ($stage->is_branch) {
            $request->validate(['turned_into_doc_id' => 'required|uuid|exists:cyber_docs,id']);
            $turnedIntoDocId = $request->input('turned_into_doc_id');
        }

        $result = $this->questService->completeStage($player, $stageId, $turnedIntoDocId);

        return response()->json($result);
    }

    /**
     * GET /api/quests/archive
     *
     * Returns the full chronological story log for the player.
     * Consumed by the Archive Splice page.
     */
    public function archive(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        return response()->json([
            'events' => $this->questLogService->getForPlayer($player),
        ]);
    }
}
