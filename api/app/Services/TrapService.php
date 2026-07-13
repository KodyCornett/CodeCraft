<?php

namespace App\Services;

use App\Events\TrapTriggered;
use App\Models\Node;
use App\Models\NodeTrap;
use App\Models\Player;
use App\Models\PlayerRig;
use Illuminate\Support\Carbon;

/**
 * TrapService — trap TTL ticking and trigger logic.
 *
 * Extracted from PlayerController::position() per SoC rules.
 * No HTTP objects, no request data — pure game logic and model mutations.
 */
class TrapService
{
    public function __construct(private readonly RigService $rigService) {}

    // =========================================================================
    // TTL management
    // =========================================================================

    /**
     * Decrement the move countdown on every trap placed by $player.
     * Traps that hit 0 moves are pruned immediately — they will never fire.
     *
     * Called once per position() request, before the destination trap check.
     */
    public function tickPlacerTraps(Player $player): void
    {
        NodeTrap::where('placer_id', $player->id)
            ->where('consumed', false)
            ->where('placer_moves_left', '>', 0)
            ->decrement('placer_moves_left');

        NodeTrap::where('placer_id', $player->id)
            ->where('placer_moves_left', '<=', 0)
            ->delete();
    }

    // =========================================================================
    // Trap detection and consumption
    // =========================================================================

    /**
     * Find the first active trap at $node placed by someone other than $player.
     *
     * If found:
     *   - Marks it consumed (one-shot guard against double-trigger).
     *   - For timed stat effects (OS Exploit, Buffer Overflow, RootKit): merges the
     *     effect slug into $player->active_effects. Caller must call $player->save().
     *   - Fires TrapTriggered broadcast to the placer.
     *
     * Slug convention: lowercase, spaces → underscores.
     * Str::snake is NOT used — it mishandles abbreviations ("OS" → "o_s").
     *
     * @return array{command_name: string, effect: array}|null
     */
    public function findAndConsume(Node $node, Player $player): ?array
    {
        $trap = NodeTrap::where('node_id', $node->id)
            ->where('placer_id', '!=', $player->id)
            ->where('consumed', false)
            ->where('placer_moves_left', '>', 0)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($trap === null) {
            return null;
        }

        $trap->consumed = true;
        $trap->save();

        $effect = $trap->effect_data ?? [];

        // Timed stat effects (moves key present, no uplink_drain key) — merge into active_effects.
        if (isset($effect['moves']) && !isset($effect['uplink_drain'])) {
            $slug                   = strtolower(preg_replace('/\s+/', '_', $trap->command_name));
            $effects                = $player->active_effects ?? [];
            $effects[$slug]         = (int) $effect['moves'];
            $player->active_effects = $effects;
        }

        // Notify the placer in real-time — their trap just fired.
        TrapTriggered::dispatch(
            $trap->placer_id,
            $trap->command_name,
            $player->handle,
            $node->canvas_id,
        );

        return [
            'command_name' => $trap->command_name,
            'effect'       => $effect,
        ];
    }

    // =========================================================================
    // Rig effect application
    // =========================================================================

    /**
     * Apply trap rig effects after the per-move uplink cost has already been deducted
     * from $rig->current_uplink by the caller.
     *
     * Handles:
     *   uplink_drain  (Crash)        — additional uplink deduction stacked on move cost.
     *   ss_damage     (Packet Flood) — PvP SS damage; applyDamage() handles the rig save.
     *
     * Returns true if applyDamage() saved the rig — caller must skip its own $rig->save().
     * Returns false if the caller still needs to save the rig.
     */
    public function applyRigEffects(array $effect, PlayerRig $rig, Player $player): bool
    {
        if (isset($effect['uplink_drain'])) {
            $rig->current_uplink = max(0, $rig->current_uplink - (int) $effect['uplink_drain']);
        }

        if (isset($effect['ss_damage'])) {
            // applyDamage persists both rig and player (handles critical failure path).
            $this->rigService->applyDamage($rig, (int) $effect['ss_damage'], 'pvp', $player);
            return true;
        }

        return false;
    }
}
