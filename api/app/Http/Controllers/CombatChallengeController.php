<?php

namespace App\Http\Controllers;

use App\Events\CombatChallengeReceived;
use App\Events\PacketHijackStarted;
use App\Events\PlayerCombatStateChanged;
use App\Models\CombatChallenge;
use App\Models\Node;
use App\Models\PacketHijackMatch;
use App\Models\Player;
use App\Services\BountyService;
use App\Services\PacketHijackService;
use App\Services\RigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Manages PvP combat challenge handshake.
 *
 * Flow:
 *   1. Challenger POSTs to /combat/challenge  → creates a pending challenge
 *   2. Target polls  GET  /combat/pending      → sees the incoming challenge
 *   3. Target POSTs  to  /combat/challenge/{id}/accept or /decline
 *   4. On accept: PacketHijackMatch is created; PacketHijackStarted events
 *      are broadcast to both players; both clients launch the terminal UI
 *   5. On decline: decliner takes 20 SS damage + bounty-scaled pocket steal
 *   6. Match resolution is handled server-side by PacketHijackController
 *      when the winning player submits a verified malware inject payload
 *
 * Decline penalty (same cost as losing a fight — running is never free):
 *   - 20 flat SS damage (can trigger critical failure if SS ≤ 20)
 *   - Pocket creds transferred to challenger at the standard steal percentage
 *     (scaled by decliner's bounty level — same formula as a real PvP loss)
 *   - Bounty is NOT changed — only cyberdoc extract or elimination resets it
 *
 * 3-player protection:
 *   A player already in an active (pending/accepted) combat cannot be challenged
 *   by a third party. The challenger receives a 422 with 'Target is already in combat.'
 */
class CombatChallengeController extends Controller
{
    /** Challenge TTL in seconds — target must respond within this window */
    private const TTL_SECONDS = 30;

    /** Flat SS damage applied when a player declines a challenge */
    private const DECLINE_SS_DAMAGE = 20;

    public function __construct(
        private readonly BountyService       $bountyService,
        private readonly RigService          $rigService,
        private readonly PacketHijackService $phService,
    ) {}

    /**
     * POST /api/combat/challenge
     *
     * Initiates a PvP challenge. Clears any stale pending challenge from this
     * challenger first so the table stays clean.
     *
     * 422s if the target is already in an active combat (pending or accepted).
     */
    public function challenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_id'     => ['required', 'uuid'],
            'node_canvas_id'=> ['required', 'string'],
        ]);

        $me = Player::where('user_id', $request->user()->id)->first();
        if ($me === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $target = Player::find($data['target_id']);
        if ($target === null) {
            return response()->json(['message' => 'Target player not found.'], 404);
        }

        if ($target->id === $me->id) {
            return response()->json(['message' => 'Cannot challenge yourself.'], 422);
        }

        // Block challenge if the node is a safe zone
        $node = Node::where('canvas_id', $data['node_canvas_id'])->first();
        if ($node?->is_safe_zone) {
            return response()->json(['message' => 'PvP is not permitted in safe zones.'], 422);
        }

        // Block challenge if target is already in an active combat.
        // Pending challenges past their TTL are not considered active — they
        // only become 'expired' in the DB when the pending() poll fires, so
        // we must gate on expires_at here rather than relying on status alone.
        $targetInCombat = CombatChallenge::where(function ($q) use ($target) {
            $q->where('challenger_id', $target->id)
              ->orWhere('target_id', $target->id);
        })->where(function ($q) {
            $q->where('status', 'accepted')
              ->orWhere(function ($q2) {
                  $q2->where('status', 'pending')
                     ->where('expires_at', '>', now());
              });
        })->exists();

        if ($targetInCombat) {
            return response()->json(['message' => 'Target is already in combat.'], 422);
        }

        // Block challenge if target is within their post-combat immunity window
        if (($target->post_combat_silent_moves ?? 0) > 0) {
            return response()->json(['message' => 'Target is in post-combat cooldown.'], 422);
        }

        // Block challenge if target has Blackout active
        if (($target->active_effects['blackout'] ?? 0) > 0) {
            return response()->json(['message' => 'Target has BLACKOUT active — challenge blocked.'], 422);
        }

        // Clear stale outgoing challenges from this player
        CombatChallenge::where('challenger_id', $me->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->update(['status' => 'expired']);

        $challenge = CombatChallenge::create([
            'id'             => (string) Str::uuid(),
            'challenger_id'  => $me->id,
            'target_id'      => $target->id,
            'node_canvas_id' => $data['node_canvas_id'],
            'status'         => 'pending',
            'expires_at'     => now()->addSeconds(self::TTL_SECONDS),
        ]);

        CombatChallengeReceived::dispatch($challenge);

        return response()->json([
            'challenge_id' => $challenge->id,
            'expires_in'   => self::TTL_SECONDS,
        ], 201);
    }

    /**
     * GET /api/combat/pending
     *
     * Returns the most recent non-expired pending challenge targeting this player.
     * Clients poll this every 2s while on a node with other players.
     */
    public function pending(Request $request): JsonResponse
    {
        $me = Player::where('user_id', $request->user()->id)->first();
        if ($me === null) {
            return response()->json(['challenge' => null]);
        }

        // Expire stale challenges and clear their in_combat state in Reverb
        $stale = CombatChallenge::where('target_id', $me->id)
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($stale as $expired) {
            $expired->update(['status' => 'expired']);
            PlayerCombatStateChanged::dispatch($expired->challenger_id, $expired->node_canvas_id, false);
            PlayerCombatStateChanged::dispatch($expired->target_id,     $expired->node_canvas_id, false);
        }

        $challenge = CombatChallenge::where('target_id', $me->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with(['challenger', 'challenger.rig.chassis', 'challenger.playerPeripherals.peripheral'])
            ->latest()
            ->first();

        if ($challenge === null) {
            return response()->json(['challenge' => null]);
        }

        return response()->json([
            'challenge' => [
                'id'             => $challenge->id,
                'challenger'     => [
                    'id'                 => $challenge->challenger->id,
                    'handle'             => $challenge->challenger->handle,
                    'bounty_level'       => $challenge->challenger->bounty_level,
                    'is_open_season'     => $challenge->challenger->is_open_season,
                    'pocket_creds'       => (int) ($challenge->challenger->pocket_creds ?? 0),
                    'effective_firewall' => $challenge->challenger->rig
                        ? $this->rigService->effectiveStats($challenge->challenger->rig, $challenge->challenger)['firewall']['effective']
                        : 1,
                ],
                'node_canvas_id' => $challenge->node_canvas_id,
                'expires_at'     => $challenge->expires_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/combat/challenge/{id}/status
     *
     * Polled by the challenger after sending a challenge.
     * Returns the current challenge status so the challenger knows when the
     * target has accepted (and can launch GridBreach) or declined (and can
     * receive the decline penalty payload).
     */
    public function status(Request $request, string $id): JsonResponse
    {
        $challenge = CombatChallenge::find($id);
        if ($challenge === null) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $me = Player::where('user_id', $request->user()->id)->first();
        if ($me?->id !== $challenge->challenger_id) {
            return response()->json(['message' => 'Not your challenge.'], 403);
        }

        if ($challenge->isExpired() && $challenge->status === 'pending') {
            $challenge->status = 'expired';
            $challenge->save();

            PlayerCombatStateChanged::dispatch($challenge->challenger_id, $challenge->node_canvas_id, false);
            PlayerCombatStateChanged::dispatch($challenge->target_id,     $challenge->node_canvas_id, false);
        }

        return response()->json(['status' => $challenge->status]);
    }

    /**
     * POST /api/combat/challenge/{id}/accept
     *
     * Target accepts the challenge — creates a Packet Hijack match, broadcasts
     * PacketHijackStarted to both players, and returns the match_id + roles
     * so each client can subscribe to the match channel and render the terminal.
     */
    public function accept(Request $request, string $id): JsonResponse
    {
        $challenge = CombatChallenge::find($id);
        if ($challenge === null || $challenge->status !== 'pending' || $challenge->isExpired()) {
            return response()->json(['message' => 'Challenge not found or expired.'], 404);
        }

        $me = Player::where('user_id', $request->user()->id)->first();
        \Log::error('ACCEPT DEBUG', ['me' => $me?->id, 'target' => $challenge?->target_id, 'challenge' => $id, 'user' => $request->user()?->id]);
        if ($me?->id !== $challenge->target_id) {
            return response()->json(['message' => 'Not your challenge.'], 403);
        }

        $challenge->status = 'accepted';
        $challenge->save();

        PlayerCombatStateChanged::dispatch($challenge->challenger_id, $challenge->node_canvas_id, true);
        PlayerCombatStateChanged::dispatch($challenge->target_id,     $challenge->node_canvas_id, true);

        // ── Create Packet Hijack match ────────────────────────────────────────
        $challenger = Player::with(['rig.chassis', 'playerPeripherals.peripheral'])
            ->find($challenge->challenger_id);
        $defender   = Player::with(['rig.chassis', 'playerPeripherals.peripheral'])
            ->find($challenge->target_id);

        // Generate unique rig IPs for each player (what the opponent must locate)
        $challengerRigIp = $this->phService->generateRigIp();
        $defenderRigIp   = $this->phService->generateRigIp();

        // Ensure the two generated IPs are distinct
        while ($defenderRigIp === $challengerRigIp) {
            $defenderRigIp = $this->phService->generateRigIp();
        }

        // challenger_target_ip = the IP the CHALLENGER must find = DEFENDER's rig
        // defender_target_ip   = the IP the DEFENDER must find   = CHALLENGER's rig
        $challengerRig = $challenger?->rig;
        $defenderRig   = $defender?->rig;

        $challengerStats = $challengerRig ? $this->rigService->effectiveStats($challengerRig, $challenger) : null;
        $defenderStats   = $defenderRig   ? $this->rigService->effectiveStats($defenderRig,   $defender)   : null;
        $challengerOs    = (int) ($challengerStats['os']['effective'] ?? 1);
        $defenderOs      = (int) ($defenderStats['os']['effective']   ?? 1);

        // Suspect boards: challenger hunts defenderRigIp in a board seeded by defender's OS.
        // Defender hunts challengerRigIp in a board seeded by challenger's OS.
        $challengerSuspects = $this->phService->generateNodeConnections($defenderRigIp,   $defenderOs);
        $defenderSuspects   = $this->phService->generateNodeConnections($challengerRigIp, $challengerOs);

        $challengerRig = $challengerRig ?? $this->rigService->getRigForPlayer($challenger);
        $defenderRig   = $defenderRig   ?? $this->rigService->getRigForPlayer($defender);

        $challengerPorts = $challengerRig && $challenger
            ? $this->phService->generatePortTopology($challengerRig, $challenger)
            : [];

        $defenderPorts = $defenderRig && $defender
            ? $this->phService->generatePortTopology($defenderRig, $defender)
            : [];

        // Phase 2 fingerprints — challenger attacks defender's system, defender attacks challenger's.
        // Fingerprint derives Tier 1 credential prefixes from the TARGET's dominant stat.
        $challengerFingerprint = ($defenderRig && $defender)
            ? $this->phService->generateFingerprint($defenderRig, $defender, $defenderPorts)
            : [];

        $defenderFingerprint = ($challengerRig && $challenger)
            ? $this->phService->generateFingerprint($challengerRig, $challenger, $challengerPorts)
            : [];

        // Phase 3 filesystems — one per player, wallet randomised per match.
        $challengerFilesystem = $this->phService->generateFilesystem();
        $defenderFilesystem   = $this->phService->generateFilesystem();

        /** @var PacketHijackMatch $match */
        $match = PacketHijackMatch::create([
            'id'                       => (string) Str::uuid(),
            'challenger_id'            => $challenge->challenger_id,
            'defender_id'              => $challenge->target_id,
            'status'                   => 'phase1',
            'challenger_target_ip'     => $defenderRigIp,
            'defender_target_ip'       => $challengerRigIp,
            'challenger_suspects'      => $challengerSuspects,
            'defender_suspects'        => $defenderSuspects,
            'challenger_ports'         => $challengerPorts,
            'challenger_fingerprint'   => $challengerFingerprint,
            'challenger_filesystem'    => $challengerFilesystem,
            'defender_ports'           => $defenderPorts,
            'defender_fingerprint'     => $defenderFingerprint,
            'defender_filesystem'      => $defenderFilesystem,
            'challenger_phase'         => 1,
            'defender_phase'           => 1,
            'started_at'               => now(),
        ]);

        // ── Broadcast started events — blank terminal, player types netstat ───
        PacketHijackStarted::dispatch(
            matchId:  $match->id,
            playerId: $challenge->challenger_id,
            role:     'challenger',
        );

        PacketHijackStarted::dispatch(
            matchId:  $match->id,
            playerId: $challenge->target_id,
            role:     'defender',
        );

        return response()->json([
            'match_id'     => $match->id,
            'challenger'   => [
                'id'           => $challenge->challenger_id,
                'handle'       => $challenger?->handle,
                'pocket_creds' => (int) ($challenger?->pocket_creds ?? 0),
            ],
            'target' => [
                'id'           => $me->id,
                'handle'       => $me->handle,
                'pocket_creds' => (int) ($me->pocket_creds ?? 0),
                'role'         => 'defender',
            ],
        ]);
    }

    /**
     * POST /api/combat/challenge/{id}/decline
     *
     * Declining carries the same financial cost as losing a fight:
     *   - 20 SS damage to the decliner (can trigger critical failure)
     *   - Pocket creds stolen at the standard bounty-scaled steal percentage
     *     (winner takes steal_pct%, decliner keeps the rest on survivable decline;
     *      if the SS damage eliminates them, pocket is fully zeroed)
     *
     * Bounty is NOT modified — only extract or elimination changes bounty.
     */
    public function decline(Request $request, string $id): JsonResponse
    {
        $challenge = CombatChallenge::find($id);
        if ($challenge === null || $challenge->status !== 'pending' || $challenge->isExpired()) {
            return response()->json(['message' => 'Challenge not found or expired.'], 404);
        }

        $me = Player::where('user_id', $request->user()->id)->first();
        if ($me?->id !== $challenge->target_id) {
            return response()->json(['message' => 'Not your challenge.'], 403);
        }

        $challenger = Player::find($challenge->challenger_id);

        // ── 1. Apply 20 SS damage to the decliner ────────────────────────────
        $rig         = $this->rigService->getRigForPlayer($me);
        $damageEvent = null;

        if ($rig !== null) {
            $damageResult = $this->rigService->applyDamage(
                rig:    $rig,
                amount: self::DECLINE_SS_DAMAGE,
                source: 'pvp',
                player: $me,
            );
            $rig         = $damageResult['rig'];
            $damageEvent = $damageResult['event'];
        }

        // ── 2. Resolve pocket transfer (same formula as combat loss) ──────────
        $isElimination = $damageEvent === 'critical_failure';
        $loot          = $challenger !== null
            ? $this->bountyService->resolvePvpLoot($challenger, $me, $isElimination)
            : ['pocket_before' => 0, 'steal_pct' => 0, 'stolen' => 0, 'seized_by_ice' => 0];

        // ── 3. Persist the loser's updated pocket state ───────────────────────
        // criticalFailure() already saves the player (via applyDamage) for eliminations.
        // For survivable declines the pocket deduction from resolvePvpLoot() is still
        // in memory — resetAfterPvpLoss() writes it to the DB alongside is_limping.
        if (!$isElimination) {
            $this->bountyService->resetAfterPvpLoss($me);
        }

        // ── 4. Mark challenge declined ────────────────────────────────────────
        $challenge->status = 'declined';
        $challenge->save();

        PlayerCombatStateChanged::dispatch($challenge->challenger_id, $challenge->node_canvas_id, false);
        PlayerCombatStateChanged::dispatch($challenge->target_id,     $challenge->node_canvas_id, false);

        $response = [
            'ok'          => true,
            'decliner_id' => $me->id,
            'penalty'     => [
                'ss_damage'    => self::DECLINE_SS_DAMAGE,
                'damage_event' => $damageEvent,
                'stolen'       => $loot['stolen'],
                'steal_pct'    => $loot['steal_pct'],
                'pocket_after' => (int) ($me->fresh()->pocket_creds ?? 0),
            ],
            'challenger_pocket' => $challenger ? (int) ($challenger->fresh()->pocket_creds ?? 0) : null,
        ];

        // Include CF payload so client can teleport if eliminated.
        // criticalFailure() already persisted current_node_id — look up that node's
        // canvas_id so the client teleports to the same place the server recorded.
        if ($isElimination) {
            $spawnCanvasId = \App\Models\Node::find($me->current_node_id)?->canvas_id;
            $response['critical_failure'] = [
                'pocket_creds'      => 0,
                'bounty_level'      => 0,
                'bounty_multiplier' => 1.0,
                'is_open_season'    => false,
                'respawn_canvas_id' => $spawnCanvasId,
                'repair_cost'       => $rig ? $this->rigService->maxSs($rig) * 25 : 0,
            ];
        }

        return response()->json($response);
    }
}
