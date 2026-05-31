<?php

namespace App\Http\Controllers;

use App\Events\PlayerCombatStateChanged;
use App\Models\CombatChallenge;
use App\Models\Player;
use App\Services\BountyService;
use App\Services\CyberDocService;
use App\Services\RigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Resolves PvP GridBreach combat results.
 *
 * Both players play to the time limit and submit their sequence count via
 * POST /api/combat/result. The server stores each score against the shared
 * challenge record. When both scores are in it determines the winner
 * (higher score wins; ties go to the challenger), applies consequences,
 * and caches the full result in result_payload.
 *
 * The first submitter receives { resolved: false } and polls
 * GET /api/combat/result/{id} until the second score arrives.
 * The second submitter receives the full resolved payload immediately.
 */
class CombatController extends Controller
{
    public function __construct(
        private readonly RigService      $rigService,
        private readonly BountyService   $bountyService,
        private readonly CyberDocService $cyberDocService,
    ) {}

    /**
     * POST /api/combat/result
     *
     * Submit this player's GridBreach score for a PvP challenge.
     * Called by each player independently after the duel timer expires.
     *
     * Body:
     *   challenge_id    string  UUID of the accepted challenge
     *   score           int     Number of full sequences completed
     *   node_canvas_id  string  Node where combat occurred
     */
    public function result(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_id'   => 'required|uuid',
            'score'          => 'required|integer|min:0',
            'node_canvas_id' => 'required|string',
        ]);

        $me = Player::where('user_id', $request->user()->id)->first();
        if ($me === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $challenge = CombatChallenge::find($data['challenge_id']);
        if ($challenge === null) {
            return response()->json(['message' => 'Challenge not found.'], 404);
        }

        // If already resolved, return the cached result directly
        if ($challenge->status === 'resolved' && $challenge->result_payload !== null) {
            return response()->json(array_merge(
                ['resolved' => true],
                $challenge->result_payload ?? []
            ));
        }

        if ($challenge->status !== 'accepted') {
            return response()->json(['message' => 'Challenge not active.'], 422);
        }

        // ── Score ceiling check ───────────────────────────────────────────────
        // Reject scores that are physically impossible given the player's stats.
        // GridBreach: game_length = 30s + (RAM × 5s), input_window = 3s + (OS × 0.3s).
        // Max sequences = floor(game_length / input_window).
        // We add a 20% buffer for any edge-case rounding, then clamp.
        $myRig = $this->rigService->getRigForPlayer($me);
        if ($myRig !== null) {
            $myStats     = $this->rigService->effectiveStats($myRig, $me);
            $gameLength  = 30 + ($myStats['ram']['effective'] * 5);
            $inputWindow = max(0.5, 3 + ($myStats['os']['effective'] * 0.3));
            $maxScore    = (int) ceil(($gameLength / $inputWindow) * 1.2); // 20 % buffer
            if ($data['score'] > $maxScore) {
                return response()->json([
                    'message' => "Score {$data['score']} exceeds the maximum possible for your rig ({$maxScore}).",
                ], 422);
            }
        }

        // Wrap score storage + resolution in a transaction with a pessimistic lock
        // so two simultaneous submissions cannot both enter resolve().
        return DB::transaction(function () use ($challenge, $me, $data) {
            // Re-fetch the row with a write lock inside the transaction
            $challenge = CombatChallenge::lockForUpdate()->find($challenge->id);

            if ($challenge === null) {
                return response()->json(['message' => 'Challenge not found.'], 404);
            }

            // If another request resolved it while we waited for the lock, return cached
            if ($challenge->status === 'resolved' && $challenge->result_payload !== null) {
                return response()->json(array_merge(
                    ['resolved' => true],
                    $challenge->result_payload ?? []
                ));
            }

            if ($me->id === $challenge->challenger_id) {
                $challenge->challenger_score = $data['score'];
            } elseif ($me->id === $challenge->target_id) {
                $challenge->target_score = $data['score'];
            } else {
                return response()->json(['message' => 'Not your challenge.'], 403);
            }

            $challenge->save();

            if ($challenge->challenger_score !== null && $challenge->target_score !== null) {
                return $this->resolve($challenge, $data['node_canvas_id']);
            }

            return response()->json(['resolved' => false]);
        });
    }

    /**
     * GET /api/combat/result/{id}
     *
     * Polled by the first submitter while waiting for the opponent's score.
     * Returns { resolved: false } until both scores are in, then the full payload.
     */
    public function getResult(Request $request, string $id): JsonResponse
    {
        $challenge = CombatChallenge::find($id);
        if ($challenge === null) {
            return response()->json(['resolved' => false]);
        }

        // Only the two participants may read the result
        $me = Player::where('user_id', $request->user()->id)->value('id');
        if ($me !== $challenge->challenger_id && $me !== $challenge->target_id) {
            return response()->json(['message' => 'Not your challenge.'], 403);
        }

        if ($challenge->status === 'resolved' && $challenge->result_payload !== null) {
            return response()->json(array_merge(
                ['resolved' => true],
                $challenge->result_payload ?? []
            ));
        }

        return response()->json(['resolved' => false]);
    }

    /**
     * Determine the winner by score, apply all PvP consequences, cache the
     * result in result_payload, and return the full resolution response.
     *
     * Higher score wins. Ties go to the challenger.
     */
    private function resolve(CombatChallenge $challenge, string $nodeCanvasId): JsonResponse
    {
        // Higher score wins; challenger wins ties
        $challengerWon = $challenge->challenger_score >= $challenge->target_score;

        $winner = Player::find($challengerWon ? $challenge->challenger_id : $challenge->target_id);
        $loser  = Player::find($challengerWon ? $challenge->target_id    : $challenge->challenger_id);

        if ($winner === null || $loser === null) {
            return response()->json(['message' => 'Player data missing.'], 500);
        }

        // ── 1. Calculate PvP damage and determine elimination before touching pocket ──
        // Formula: max(15, 20 + (winnerCpu × 5) − (loserFirewall × 5))
        // IMPORTANT: loot must be resolved before applyDamage() so that
        // criticalFailure() (which zeroes pocket_creds) does not wipe the loot
        // pool before the winner can claim it.
        $winnerRig   = $this->rigService->getRigForPlayer($winner);
        $loserRig    = $this->rigService->getRigForPlayer($loser);
        $damageEvent = null;

        // PvP damage always applies — if the loser has no rig record the flat
        // minimum of 15 is used so they can never avoid elimination by having
        // an incomplete account.
        $winnerCpu     = $winnerRig
            ? ($this->rigService->effectiveStats($winnerRig, $winner)['cpu']['effective'] ?? 1)
            : 1;
        $loserFirewall = $loserRig
            ? ($this->rigService->effectiveStats($loserRig, $loser)['firewall']['effective'] ?? 1)
            : 0;
        $pvpDamage = max(15, 20 + ($winnerCpu * 5) - ($loserFirewall * 5));

        // Pre-calculate elimination so loot can be resolved before damage is applied.
        $currentSs     = (int) ($loserRig?->current_ss ?? 0);
        $isElimination = $loserRig !== null && ($currentSs - $pvpDamage) <= 0;

        // ── 2. Resolve loot before applying damage ────────────────────────────
        // Survivable: winner takes steal_pct%, loser keeps the rest (ICE seizes nothing).
        // Elimination: winner takes steal_pct%, ICE seizes the rest, loser zeroed.
        // Must run before applyDamage() — criticalFailure() zeroes pocket_creds and
        // would wipe the loot pool if damage were applied first.
        $loot = $this->bountyService->resolvePvpLoot($winner, $loser, $isElimination);

        // ── 3. Apply PvP damage to the loser's rig ────────────────────────────
        if ($loserRig !== null) {
            $damageResult = $this->rigService->applyDamage(
                rig:    $loserRig,
                amount: $pvpDamage,
                source: 'pvp',
                player: $loser,
            );
            $loserRig    = $damageResult['rig'];
            $damageEvent = $damageResult['event'];
        }

        // ── 3. Post-combat silent moves ────────────────────────────────────────
        // Both players get 2 moves of challenge immunity — enough to leave the node
        // and change direction before they can be attacked again.
        $winner->post_combat_silent_moves = 2;
        $winner->save();

        // ── 4. Reset loser state ──────────────────────────────────────────────
        // Critical failure already wiped bounty/run state in RigService::criticalFailure()
        // and explicitly cleared is_limping (the player is beyond limping — they need repair).
        // Only set is_limping for a survivable loss; do not overwrite the false that
        // criticalFailure() already persisted.
        if ($damageEvent !== 'critical_failure') {
            $this->bountyService->resetAfterPvpLoss($loser);
        }

        // Give loser the same 2-move window — resetAfterPvpLoss() calls save() so
        // we set this after and save again.
        $loser->post_combat_silent_moves = 2;
        $loser->save();

        // ── 5. Record PvP win + bounty escalation for winner ──────────────────
        $bountyEvent = $this->bountyService->recordPvpWin($winner);

        // ── Build payload ──────────────────────────────────────────────────────
        $winnerEffective = $winnerRig
            ? $this->rigService->effectiveStats($winnerRig, $winner)
            : null;
        $loserEffective = $loserRig
            ? $this->rigService->effectiveStats($loserRig, $loser)
            : null;

        $payload = [
            'winner_id'     => $winner->id,
            'loser_id'      => $loser->id,
            'winner_score'  => $challengerWon ? $challenge->challenger_score : $challenge->target_score,
            'loser_score'   => $challengerWon ? $challenge->target_score     : $challenge->challenger_score,
            'loot' => [
                'loser_pocket_before' => $loot['pocket_before'],
                'steal_pct'           => $loot['steal_pct'],
                'stolen'              => $loot['stolen'],
                'seized_by_ice'       => $loot['seized_by_ice'],
            ],
            'bounty_event' => [
                'type' => $bountyEvent->type,
                'data' => $bountyEvent->data,
            ],
            'winner' => [
                'player_id'                => $winner->id,
                'handle'                   => $winner->handle,
                'post_combat_silent_moves' => $winner->post_combat_silent_moves,
                'bounty_level'             => $winner->bounty_level,
                'bounty_multiplier'        => $winner->bounty_multiplier,
                'is_open_season'           => $winner->is_open_season,
                'pocket_creds'             => $winner->pocket_creds,
                'effective_stats'          => $winnerEffective,
            ],
            'loser' => array_merge([
                'player_id'    => $loser->id,
                'handle'       => $loser->handle,
                'is_limping'   => $loser->is_limping,
                'bounty_level' => $loser->bounty_level,
                'pocket_creds' => (int) ($loser->pocket_creds ?? 0),
                'effective_stats' => $loserEffective,
                'damage_event'    => $damageEvent,
            ], $damageEvent === 'critical_failure' ? [
                'critical_failure' => [
                    // criticalFailure() already persisted current_node_id — look up that
                    // node's canvas_id so the client teleports to the same place.
                    'respawn_canvas_id' => \App\Models\Node::find($loser->current_node_id)?->canvas_id,
                    'repair_cost'       => $loserRig ? $this->cyberDocService->repairCost($loser) : 0,
                ],
            ] : []),
        ];

        // Cache result and mark resolved — stored as array; the model's 'array' cast
        // handles JSON encoding on write and decoding on read automatically.
        $challenge->result_payload = $payload;
        $challenge->status         = 'resolved';
        $challenge->save();

        PlayerCombatStateChanged::dispatch($winner->id, $nodeCanvasId, false);
        PlayerCombatStateChanged::dispatch($loser->id,  $nodeCanvasId, false);

        return response()->json(array_merge(['resolved' => true], $payload));
    }
}
