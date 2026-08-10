<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\DocChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocChatController extends Controller
{
    public function __construct(private readonly DocChatService $docChatService) {}

    /**
     * GET /api/doc-chat/{hubCanvasId}/messages
     *
     * Recent history for one DOC's hub chat room. Requires the player be
     * physically at that hub right now — same rule the Reverb channel auth
     * enforces for the live socket subscription.
     */
    public function index(Request $request, string $hubCanvasId): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        if (!$this->docChatService->playerIsAtHub($player, $hubCanvasId)) {
            return response()->json(['message' => 'You must be at this CyberDoc to view its channel.'], 403);
        }

        return response()->json([
            'messages' => $this->docChatService->recentMessages($hubCanvasId),
        ]);
    }

    /**
     * POST /api/doc-chat/{hubCanvasId}/messages
     *
     * Body: { body: string }
     */
    public function store(Request $request, string $hubCanvasId): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:240'],
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        if (!$this->docChatService->playerIsAtHub($player, $hubCanvasId)) {
            return response()->json(['message' => 'You must be at this CyberDoc to speak in its channel.'], 403);
        }

        try {
            $message = $this->docChatService->postMessage($player, $hubCanvasId, $data['body']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $this->docChatService->present($message),
        ]);
    }
}
