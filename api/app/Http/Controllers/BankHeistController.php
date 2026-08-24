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
     * Gate 1 failure — either the Spoofed Handshake countertrace timer hit
     * zero, or a Brute Force run got caught before its timer completed.
     * Both use Gate 1's uniform cost stack (SS + bounty + node cooldown),
     * per BANK_HEIST_BUILD_PLAN.md — the approach field is informational
     * only, it doesn't change the consequence.
     *
     * Body:
     *   approach  string  'spoofed_handshake' | 'brute_force'
     */
    public function gate1Failed(Request $request, string $canvasId): JsonResponse
    {
        $request->validate([
            'approach' => ['required', 'string', 'in:spoofed_handshake,brute_force'],
        ]);

        [$player, $node, $error] = $this->resolvePlayerAndBank($request, $canvasId);
        if ($error) return $error;

        $result = $this->bankHeistService->resolveGate1Failure($player, $node);

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
    // POST /api/bank-heist/{canvasId}/brute-force-clean-exit
    // =========================================================================

    /**
     * A Brute Force run that survived its timer and extracted clean.
     * Unavoidable flat +1 bounty tax — going loud always leaves a mark,
     * even on a perfect run. Distinct from an account crack's own outcome,
     * which is reported separately via accountResult().
     */
    public function bruteForceCleanExit(Request $request, string $canvasId): JsonResponse
    {
        [$player, $node, $error] = $this->resolvePlayerAndBank($request, $canvasId);
        if ($error) return $error;

        $result = $this->bankHeistService->resolveBruteForceCleanExit($player);

        return response()->json([
            'bounty_hacks_added' => $result['bounty_hacks_added'],
            'bounty_level'       => $player->bounty_level,
            'bounty_multiplier'  => $player->bounty_multiplier,
        ]);
    }

    // =========================================================================
    // POST /api/bank-heist/{canvasId}/account-result
    // =========================================================================

    /**
     * Resolves one Gate 2 Phase 2 account-crack attempt. Rewards are always
     * computed server-side from account ICE — never trusted from the
     * client — mirroring NodeController::deplete()'s pattern exactly.
     *
     * Body:
     *   account_type    string  'normal' | 'investment'
     *   outcome         string  'success' | 'clean_failed' | 'abandoned'
     *   detection_band  int     0-4, computed client-side from the running
     *                           detection bar. A band of 4 always resolves
     *                           as a forced Lockdown regardless of outcome.
     */
    public function accountResult(Request $request, string $canvasId): JsonResponse
    {
        $data = $request->validate([
            'account_type'   => ['required', 'string', 'in:normal,investment'],
            'outcome'        => ['required', 'string', 'in:success,clean_failed,abandoned'],
            'detection_band' => ['required', 'integer', 'min:0', 'max:4'],
        ]);

        [$player, $node, $error] = $this->resolvePlayerAndBank($request, $canvasId);
        if ($error) return $error;

        $result = $this->bankHeistService->resolveAccountEvent(
            $player,
            $node,
            $data['account_type'],
            $data['outcome'],
            $data['detection_band'],
        );

        return response()->json([
            'outcome'            => $result['outcome'],
            'reward'             => $result['reward'],
            'ss_damage'          => $result['ss_damage'],
            'bounty_hacks_added' => $result['bounty_hacks_added'],
            'failure_jump'       => $result['failure_jump'] ?? 0.0,
            'pocket_creds'       => $player->pocket_creds,
            'tech_points'        => $player->tech_points,
            'bounty_level'       => $player->bounty_level,
            'bounty_multiplier'  => $player->bounty_multiplier,
            ...$this->rigStatePayload($result['rig'], $result['event']),
        ]);
    }
}
