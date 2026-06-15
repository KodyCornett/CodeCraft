<?php

namespace App\Http\Controllers;

use App\Models\CyberDoc;
use App\Models\PacketHijackMatch;
use App\Models\Player;
use App\Services\PacketHijackLifecycleService;
use App\Services\PacketHijackMatchSetupService;
use App\Services\QuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TutorialController extends Controller
{
    public function __construct(
        private readonly QuestService                $questService,
        private readonly PacketHijackLifecycleService $lifecycleService,
        private readonly PacketHijackMatchSetupService $setupService,
    ) {}

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
            'tutorial_state'                       => ['required', 'array'],
            'tutorial_state.tutorialSeen'          => ['boolean'],
            'tutorial_state.tutorialSkipped'       => ['boolean'],
            'tutorial_state.tutorialComplete'      => ['boolean'],
            'tutorial_state.cortexInstallSeen'     => ['boolean'],
            'tutorial_state.stepsDone'             => ['array'],
            'tutorial_state.questsRewarded'        => ['array'],
            'tutorial_state.hasBadge'              => ['boolean'],
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

    /**
     * POST /api/tutorial/packet-hijack/start
     *
     * Creates a solo practice PacketHijackMatch for the tutorial.
     * No real opponent — defender_id is null, is_practice is true.
     * Challenger data is seeded with easy difficulty stats (FW 1, OS 2).
     *
     * The player runs the full Phase 1 → Phase 2 → Phase 3 sequence solo.
     * On transfer, the economy is skipped and a practice-complete response is
     * returned inline (no WebSocket needed).
     */
    public function practiceStart(Request $request): JsonResponse
    {
        $player = Player::with(['rig.chassis', 'playerPeripherals.peripheral'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $player) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        // Cancel any stale practice match so the player always gets a fresh one.
        PacketHijackMatch::where('challenger_id', $player->id)
            ->where('is_practice', true)
            ->whereIn('status', ['phase1', 'phase2'])
            ->update(['status' => 'abandoned', 'completed_at' => now()]);

        // Seed the challenger's board (the only side that exists in practice).
        // We use a dummy target with low FW (1) and low OS (2) so the exploit
        // chain is short and the suspect board is easy to scan.
        $practiceTargetIp = $this->lifecycleService->generateRigIp();

        $suspects = $this->lifecycleService->generateNodeConnections($practiceTargetIp, /* os */ 2);

        $rig   = $player->rig;
        $ports = $rig ? $this->setupService->generatePortTopology($rig, $player) : [];

        // Fingerprint describes the *target* system — use dummy stat context.
        $fingerprint = $this->setupService->generatePracticeFingerprint();
        $filesystem  = $this->setupService->generateFilesystem();

        $match = PacketHijackMatch::create([
            'id'                     => (string) Str::uuid(),
            'challenger_id'          => $player->id,
            'defender_id'            => null,
            'status'                 => 'phase1',
            'is_practice'            => true,
            'challenger_target_ip'   => $practiceTargetIp,
            'challenger_suspects'    => $suspects,
            'challenger_ports'       => $ports,
            'challenger_fingerprint' => $fingerprint,
            'challenger_filesystem'  => $filesystem,
            'challenger_phase'       => 1,
            'started_at'             => now(),
            'expires_at'             => now()->addMinutes(30),
        ]);

        return response()->json([
            'match_id' => $match->id,
            'role'     => 'challenger',
        ]);
    }

    /**
     * POST /api/tutorial/complete
     *
     * Called by the client when all tutorial quests are rewarded.
     * Unlocks the entry arc (Knuckle / BA-hub) so it appears active
     * in the quest log immediately — no CyberDoc visit required.
     *
     * Idempotent: initArcForDoc skips arcs that are already initialised.
     */
    public function complete(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();

        if (! $player) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $entryDoc = CyberDoc::whereHas('questArcs', fn ($q) => $q->where('is_entry_arc', true))->first();

        if ($entryDoc) {
            $this->questService->initArcForDoc($player, $entryDoc);
        }

        return response()->json(['ok' => true]);
    }
}
