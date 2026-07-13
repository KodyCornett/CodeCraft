<?php

namespace App\Services;

use App\Events\PlayerCombatStateChanged;
use App\Models\CombatChallenge;
use App\Models\PacketHijackMatch;
use App\Models\Player;

/**
 * PvpResolutionService — economy, damage, and bounty mutations for a completed PH match.
 *
 * Extracted from PacketHijackController::resolveMatch() per SoC rules.
 *
 * Owns:
 *   - PvP damage formula
 *   - Loot resolution via BountyService
 *   - RigService::applyDamage on the loser
 *   - Post-combat silent move windows on both players
 *   - Bounty reset (loser) and escalation (winner)
 *   - CombatChallenge record closure
 *   - PlayerCombatStateChanged broadcast to both players
 *
 * Does NOT own:
 *   - PacketHijackMatch record save / status update (controller)
 *   - PacketHijackMatchComplete broadcast (controller — owns broadcast ordering)
 *   - PacketHijackCommandResult broadcast (controller)
 *   - HTTP responses (controller)
 */
class PvpResolutionService
{
    public function __construct(
        private readonly RigService    $rigService,
        private readonly BountyService $bountyService,
    ) {}

    /**
     * Resolve all economy and game-state consequences of a completed PH match.
     *
     * Saves winner, loser, and their rigs. Closes the originating CombatChallenge.
     * Broadcasts PlayerCombatStateChanged to both players so the combat overlay clears.
     *
     * Throws \RuntimeException if the opponent player record cannot be found.
     *
     * @return array{loser_id: string, loot_stolen: int, damage_event: string|null}
     */
    public function resolve(PacketHijackMatch $match, Player $winner): array
    {
        $loserId = $match->opponentIdOf($winner->id);
        $loser   = Player::find($loserId);

        if ($loser === null) {
            throw new \RuntimeException('Opponent player not found.');
        }

        $loserRig  = $this->rigService->getRigForPlayer($loser);
        $winnerRig = $this->rigService->getRigForPlayer($winner);

        $loserFirewall = $loserRig
            ? $this->rigService->effectiveStats($loserRig, $loser)['firewall']['effective']
            : 0;
        $winnerCpu = $winnerRig
            ? $this->rigService->effectiveStats($winnerRig, $winner)['cpu']['effective']
            : 1;

        // PvP damage formula — mirrors CLAUDE.md spec
        $pvpDamage     = max(15, 20 + ($winnerCpu * 5) - ($loserFirewall * 5));
        $currentSs     = (int) ($loserRig?->current_ss ?? 0);
        $isElimination = $loserRig !== null && ($currentSs - $pvpDamage) <= 0;

        // ── 1. Resolve loot BEFORE damage ─────────────────────────────────────
        // BountyService reads pocket_creds before applyDamage wipes them on CF.
        $loot = $this->bountyService->resolvePvpLoot($winner, $loser, $isElimination);

        // ── 2. Apply PvP damage to loser ──────────────────────────────────────
        $damageEvent = null;
        if ($loserRig !== null) {
            $damageResult = $this->rigService->applyDamage(
                rig:    $loserRig,
                amount: $pvpDamage,
                source: 'pvp',
                player: $loser,
            );
            $damageEvent = $damageResult['event'];
        }

        // ── 3. Post-combat immunity + loser state reset ───────────────────────
        $winner->post_combat_silent_moves = 2;
        $winner->save();

        if ($damageEvent !== 'critical_failure') {
            $this->bountyService->resetAfterPvpLoss($loser);
        }
        $loser->post_combat_silent_moves = 2;
        $loser->save();

        // ── 4. Winner bounty escalation ───────────────────────────────────────
        $this->bountyService->recordPvpWin($winner);

        // ── 5. Close the originating CombatChallenge ──────────────────────────
        $challenge = CombatChallenge::where('challenger_id', $match->challenger_id)
            ->where('target_id', $match->defender_id)
            ->where('status', 'accepted')
            ->first();

        if ($challenge !== null) {
            $challenge->status = 'resolved';
            $challenge->save();
            PlayerCombatStateChanged::dispatch($winner->id, $challenge->node_canvas_id, false);
            PlayerCombatStateChanged::dispatch($loser->id,  $challenge->node_canvas_id, false);
        }

        return [
            'loser_id'     => $loser->id,
            'loot_stolen'  => $loot['stolen'],
            'damage_event' => $damageEvent,
        ];
    }
}
