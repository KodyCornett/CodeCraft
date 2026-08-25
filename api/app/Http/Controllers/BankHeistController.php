<?php

namespace App\Http\Controllers;

use App\Models\Node;
use App\Models\Player;
use App\Services\BankHeistService;
use App\Services\RigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles Bank Heist outcome events.
 *
 * Responsibilities (only):
 *   - Validate input, player, and that the target node is one of the 19
 *     fixed Bank Heist targets.
 *   - Delegate all game logic (SS damage, bounty, rewards, cooldown) to
 *     BankHeistService.
 *   - Return updated rig/player state so the frontend can sync without a
 *     second round-trip.
 *
 * No game math lives here. The countertrace/Anomaly Countdown timers and
 * the Spoofed Handshake puzzle run client-side (see BankHeistService's
 * class doc for why) — this controller only resolves discrete outcomes.
 */
class BankHeistController extends Controller
{
    public function __construct(
        private readonly BankHeistService $bankHeistService,
        private readonly RigService       $rigService,
    ) {}

    /** Shared lookups + validation for every endpoint below. */
    private function resolvePlayerAndBank(Request $request, string $canvasId): array
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return [null, null, response()->json(['message' => 'Player not found.'], 404)];
        }

        $node = Node::where('canvas_id', $canvasId)->where('is_bank_target', true)->first();
        if ($node === null) {
            return [null, null, response()->json(['message' => 'Not a Bank Heist target.'], 404)];
        }

        return [$player, $node, null];
    }

    private function rigStatePayload($rig, ?string $event): array
    {
        return [
            'current_ss' => $rig?->current_ss,
            'max_ss'     => $rig ? $this->rigService->maxSs($rig) : null,
            'event'      => $event,
        ];
    }

    // =========================================================================
    // POST /api/bank-heist/{canvasId}/gate1-failed
    // =========================================================================

    /**
     * "Denied at the door" — Gate 1's MitM Handshake Hijack timed out on any
     * of its three steps (SYN/SYN-ACK/ACK), or Gate 2's Global Trace Meter
     * overran to 100%. Both use the same uniform cost stack (SS + bounty +
     * node cooldown), per BANK_HEIST_BUILD_PLAN.md — the approach field is
     * informational except for 'phase2_overrun', which also discards the
     * player's staged Phase 2 harvest buffer (see
     * BankHeistService::resolveGate1Failure). Named `gate1Failed` for
     * historical reasons (it predates the current two-gate shape) but is
     * genuinely shared by both gates now.
     *
     * Body:
     *   approach  string  'mitm_handshake' | 'phase2_overrun'
     */
    public function gate1Failed(Request $request, string $canvasId): JsonResponse
    {
        $data = $request->validate([
            'approach' => ['required', 'string', 'in:mitm_handshake,phase2_overrun'],
        ]);

        [$player, $node, $error] = $this->resolvePlayerAndBank($request, $canvasId);
        if ($error) return $error;

        $result = $this->bankHeistService->resolveGate1Failure($player, $node, $data['approach']);

        return response()->json([
            'ss_damage'          => $result['ss_damage'],
            'bounty_hacks_added' => $result['bounty_hacks_added'],
            'cooldown_minutes'   => $result['cooldown_minutes'],
            'cooldown_until'     => $result['cooldown_until'],
            'bounty_level'       => $player->bounty_level,
            'bounty_multiplier'  => $player->bounty_multiplier,
            ...$this->rigStatePayload($result['rig'], $result['event']),
        ]);
    }

    // =========================================================================
    // POST /api/bank-heist/{canvasId}/phase2-inject
    // =========================================================================

    /**
     * Resolves one Gate 2 Phase 2 successful token injection. Rewards are
     * always rolled server-side from the reported band/currency — never
     * trusted from the client — and accumulated in a short-lived staged
     * buffer that only phase2Extract() moves into the player's permanent
     * balance. See BankHeistService's Gate 2 Phase 2 section for why.
     *
     * Body:
     *   band      string  'easy' | 'hard'
     *   currency  string  'CRED' | 'TECH_PT'
     */
    public function phase2Inject(Request $request, string $canvasId): JsonResponse
    {
        $data = $request->validate([
            'band'     => ['required', 'string', 'in:easy,hard'],
            'currency' => ['required', 'string', 'in:CRED,TECH_PT'],
        ]);

        [$player, $node, $error] = $this->resolvePlayerAndBank($request, $canvasId);
        if ($error) return $error;

        $result = $this->bankHeistService->resolvePhase2Inject($player, $node, $data['band'], $data['currency']);

        return response()->json([
            'reward'       => $result['reward'],
            'staged_creds' => $result['staged_creds'],
            'staged_tech'  => $result['staged_tech'],
        ]);
    }

    // =========================================================================
    // POST /api/bank-heist/{canvasId}/phase2-extract
    // =========================================================================

    /**
     * EXTRACT — banks this run's entire staged Phase 2 buffer to the
     * player's permanent balance and clears it. Ends the mini-game cleanly.
     */
    public function phase2Extract(Request $request, string $canvasId): JsonResponse
    {
        [$player, $node, $error] = $this->resolvePlayerAndBank($request, $canvasId);
        if ($error) return $error;

        $result = $this->bankHeistService->resolvePhase2Extract($player, $node);

        return response()->json([
            'creds_extracted' => $result['creds_extracted'],
            'tech_extracted'  => $result['tech_extracted'],
            'pocket_creds'    => $player->pocket_creds,
            'tech_points'     => $player->tech_points,
        ]);
    }
}
