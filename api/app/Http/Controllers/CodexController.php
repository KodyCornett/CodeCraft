<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\CodexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CodexController
 *
 * Thin — every method resolves the requesting player then delegates to
 * CodexService. No game logic lives here, per CLAUDE.md's controller rule.
 */
class CodexController extends Controller
{
    public function __construct(private readonly CodexService $codexService) {}

    /**
     * GET /api/codex/state
     *
     * One-shot state for the Codex Archive app: whether the player has any
     * active codex threads, which earned keys are waiting to be resolved,
     * and the full History (tracking) list.
     */
    public function state(Request $request): JsonResponse
    {
        $player = $this->resolvePlayer($request);
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        return response()->json([
            'has_active_codex' => $this->codexService->hasActiveCodex($player),
            'unresolved_keys'  => $this->codexService->getUnresolvedKeys($player),
            'history'          => $this->codexService->getHistory($player),
        ]);
    }

    /**
     * POST /api/codex/archive-win
     *
     * Called when the player wins Archive Extraction while a codex thread
     * is active — playable at any node, entirely separate from a normal
     * node hack. Rolls a chance to drop a key; a miss is a normal outcome,
     * not an error.
     */
    public function archiveWin(Request $request): JsonResponse
    {
        $player = $this->resolvePlayer($request);
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        try {
            $key = $this->codexService->grantKeyFromWin($player);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['dropped' => $key !== null, 'key_id' => $key?->id]);
    }

    /**
     * POST /api/codex/resolve
     * Body: { key_id: string }
     */
    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate(['key_id' => ['required', 'string']]);

        $player = $this->resolvePlayer($request);
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        try {
            $result = $this->codexService->resolveKey($player, $data['key_id']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * GET /api/codex/page/{slug}
     */
    public function showPage(Request $request, string $slug): JsonResponse
    {
        $player = $this->resolvePlayer($request);
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        try {
            $page = $this->codexService->getPageBySlug($player, $slug);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json($page);
    }

    /**
     * POST /api/codex/page/{splicePageId}/solve
     * Body: { answers: { [credentialLabel]: string } }
     *
     * One answer per required credential, keyed by label — easy Codexes
     * have one, harder ones have several.
     */
    public function solvePage(Request $request, string $splicePageId): JsonResponse
    {
        $data = $request->validate([
            'answers'   => ['required', 'array'],
            'answers.*' => ['nullable', 'string'],
        ]);

        $player = $this->resolvePlayer($request);
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        try {
            $result = $this->codexService->solveLogin($player, $splicePageId, $data['answers']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    private function resolvePlayer(Request $request): ?Player
    {
        return Player::where('user_id', $request->user()->id)->first();
    }
}
