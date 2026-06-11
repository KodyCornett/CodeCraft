<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\WatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatcherController extends Controller
{
    public function __construct(
        private readonly WatcherService $watcherService,
    ) {}

    /**
     * GET /api/watcher/unread
     *
     * Returns all unread Watcher signals for the player.
     * Called on game boot and after stage completion.
     * Tokens ({persona}, {persona_desc}) resolved server-side.
     */
    public function unread(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        return response()->json([
            'signals'    => $this->watcherService->getUnread($player),
            'has_unread' => $this->watcherService->hasUnread($player),
        ]);
    }

    /**
     * GET /api/watcher/all
     *
     * Returns all Watcher signals for the player (read + unread).
     * Used by the Watcher channel Splice page.
     */
    public function all(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        return response()->json([
            'signals' => $this->watcherService->getAll($player),
        ]);
    }

    /**
     * POST /api/watcher/read-all
     *
     * Marks all unread signals as read.
     * Called when the player opens the Watcher channel page.
     */
    public function readAll(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $this->watcherService->markAllRead($player);

        return response()->json(['ok' => true]);
    }
}
