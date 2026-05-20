<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerPeripheral;
use App\Models\PlayerRig;
use InvalidArgumentException;
use RuntimeException;

class RigService
{
    /**
     * The cyclic downgrade order for the Parasite Stat mechanic.
     * When the point cap is full, the next stat after the upgrade target
     * in this ring (that is above minimum) is automatically downgraded.
     *
     * OS → RAM → CPU → Storage → Firewall → OS
     */
    private const STAT_RING = ['os', 'ram', 'cpu', 'storage', 'firewall'];

    private const VALID_STATS = ['cpu', 'ram', 'firewall', 'storage', 'os'];

    /** System Stability is a flat 100 for every rig — chassis and OS do not affect it. */
    private const MAX_SS = 100;

    /**
     * SS-loss percentage thresholds at which stat penalties kick in.
     * Every 20% SS lost strips 1 point from CPU, RAM, Firewall, and OS.
     * Penalties are cumulative — 80% lost means all four tiers apply (−4 each).
     * Storage is never penalised — it is physical capacity, not performance.
     *
     * At 0 SS a Critical System Failure is triggered (see criticalFailure()).
     */
    private const DEGRADATION_TIERS = [
        20 => ['cpu' => -1, 'ram' => -1, 'firewall' => -1, 'os' => -1],
        40 => ['cpu' => -1, 'ram' => -1, 'firewall' => -1, 'os' => -1],
        60 => ['cpu' => -1, 'ram' => -1, 'firewall' => -1, 'os' => -1],
        80 => ['cpu' => -1, 'ram' => -1, 'firewall' => -1, 'os' => -1],
    ];

    /** Minimum level any stat may reach (cannot be downgraded below this). */
    private const MIN_LEVEL = 1;

    /**
     * Upgrade a single stat on a rig by one level.
     *
     * If the rig is already at its chassis point cap, the Parasite Stat
     * (the next stat in the ring after $stat that is above minimum level)
     * is downgraded by one level to make room before the upgrade is applied.
     *
     * current_ss is recalculated whenever os_level changes, whether the OS
     * was the stat being upgraded or the one downgraded by the parasite.
     *
     * Returns ['rig' => PlayerRig, 'tax' => null|array].
     * Tax array: ['stat' => string, 'old_level' => int, 'new_level' => int].
     *
     * @throws InvalidArgumentException If $stat is not a recognised stat name.
     * @throws \OverflowException       If the stat is already at the per-stat cap.
     * @throws RuntimeException         If the point cap is full and every other
     *                                  stat is already at minimum level.
     */
    public function upgradeStat(PlayerRig $rig, string $stat): array
    {
        if (!in_array($stat, self::VALID_STATS, true)) {
            throw new InvalidArgumentException(
                "'{$stat}' is not a valid stat. Expected one of: " . implode(', ', self::VALID_STATS)
            );
        }

        $chassis  = $rig->chassis;
        $column   = "{$stat}_level";
        $capKey   = "cap_{$stat}";
        $taxEvent = null;

        // Enforce per-stat chassis cap (e.g. cap_cpu = 5 for BlackHat v1.0)
        if ($rig->$column >= $chassis->$capKey) {
            throw new \OverflowException(
                "'{$stat}' is already at the chassis cap ({$chassis->$capKey})."
            );
        }

        if ($this->totalPointsSpent($rig) >= $chassis->total_point_cap) {
            $taxedStat = $this->applyParasiteDowngrade($rig, $stat);
            $taxedCol  = "{$taxedStat}_level";
            // applyParasiteDowngrade already decremented — old level = current + 1
            $taxEvent  = [
                'stat'      => $taxedStat,
                'old_level' => $rig->$taxedCol + 1,
                'new_level' => $rig->$taxedCol,
            ];
        }

        $rig->$column += 1;

        $rig->save();

        return ['rig' => $rig, 'tax' => $taxEvent];
    }

    /**
     * Restore a rig's current_ss to its maximum value.
     * Optionally clear the is_damaged flag on all of the player's peripherals.
     */
    public function repair(PlayerRig $rig, Player $player, bool $repairPeripherals = false): PlayerRig
    {
        $rig->current_ss = $this->maxSs($rig);
        $rig->save();

        if ($repairPeripherals) {
            PlayerPeripheral::where('player_id', $player->id)
                ->where('is_damaged', true)
                ->update(['is_damaged' => false]);
        }

        return $rig;
    }

    /**
     * Resolve the rig that belongs to a Player.
     * Returns null if the player has no rig yet.
     */
    public function getRigForPlayer(Player $player): ?PlayerRig
    {
        return $player->rig()->with('chassis')->first();
    }

    /**
     * Apply damage to a rig's current_ss.
     *
     * When SS reaches 0: Critical System Failure (unified for both PvE and PvP).
     *   - Pocket creds wiped
     *   - Bounty run reset
     *   - Player teleported to last Street Doc node
     *   - SS stays at 0 — player must pay for repair before hacking again
     *
     * Returns an array with the updated rig, player, and a nullable event string
     * ('critical_failure' | null).
     *
     * @throws InvalidArgumentException If $source is not 'pve' or 'pvp'.
     */
    public function applyDamage(PlayerRig $rig, int $amount, string $source, ?Player $player = null): array
    {
        if (!in_array($source, ['pve', 'pvp'], true)) {
            throw new InvalidArgumentException("Invalid damage source '{$source}'. Expected 'pve' or 'pvp'.");
        }

        $rig->current_ss = max(0, $rig->current_ss - $amount);

        $event = null;

        if ($rig->current_ss === 0) {
            $event = $this->criticalFailure($rig, $player);
        }

        $rig->save();
        $player?->save();

        // Enforce stat-cap consequences triggered by the new SS level.
        // RAM cap: deactivate loadout slots from the top down if effective RAM dropped.
        // CPU cap: deactivate commands whose level now exceeds effective CPU.
        $deactivatedRam = [];
        $deactivatedCpu = [];

        if ($player !== null) {
            $newStats       = $this->effectiveStats($rig, $player);
            $deactivatedRam = $this->enforceRamCap($player, $newStats['ram']['effective']);
            $deactivatedCpu = $this->enforceCpuCommandCap($player, $newStats['cpu']['effective']);
        }

        return [
            'rig'             => $rig,
            'player'          => $player,
            'event'           => $event,
            'deactivated_ram' => $deactivatedRam,
            'deactivated_cpu' => $deactivatedCpu,
        ];
    }

    /**
     * Build the effective-stats payload for a rig.
     *
     * Each stat entry contains: level, chassis base value, peripheral boost,
     * and the final effective value the engine should use.
     * Pass $player to include real peripheral boosts; omit for a raw stat view.
     *
     * @return array<string, mixed>
     */
    public function effectiveStats(PlayerRig $rig, ?Player $player = null): array
    {
        $chassis = $rig->chassis;

        $boosts = $player
            ? $this->peripheralBoosts($player)
            : ['cpu' => 0, 'ram' => 0, 'firewall' => 0, 'storage' => 0, 'os' => 0];

        $penalties = $this->degradationPenalties($rig);

        $numericStats = [
            'cpu'      => [$rig->cpu_level,      $chassis->base_cpu],
            'ram'      => [$rig->ram_level,       $chassis->base_ram],
            'firewall' => [$rig->firewall_level,  $chassis->base_firewall],
            'storage'  => [$rig->storage_level,   $chassis->base_storage],
        ];

        $stats = [];
        foreach ($numericStats as $name => [$level, $base]) {
            $boost   = $boosts[$name];
            $penalty = $penalties[$name] ?? 0;
            $stats[$name] = [
                'level'            => $level,
                'base'             => $base,
                'peripheral_boost' => $boost,
                'degradation'      => $penalty,
                'effective'        => max(1, $base + $level + $boost + $penalty),
            ];
        }

        // OS: base_os is a string name (e.g. "BlackHat OS 1.0"); the numeric base
        // is base_os_level. Player-invested OS points are stored in os_level (starts
        // at 0, same as other stats). effective = base_os_level + os_level + boost + penalty.
        $osBoost   = $boosts['os'];
        $osPenalty = $penalties['os'] ?? 0;
        $stats['os'] = [
            'level'            => $rig->os_level,
            'base'             => $chassis->base_os_level,
            'peripheral_boost' => $osBoost,
            'degradation'      => $osPenalty,
            'effective'        => max(1, $chassis->base_os_level + $rig->os_level + $osBoost + $osPenalty),
        ];

        return $stats;
    }

    /**
     * Maximum System Stability.
     * Fixed at 100 for all rigs — chassis and OS do not affect this value.
     * Firewall is the only stat that influences SS (by reducing incoming damage).
     */
    public function maxSs(PlayerRig $rig): int
    {
        return self::MAX_SS;
    }

    /**
     * Restore a rig's current_uplink to its chassis base value (full restore).
     *
     * Called when the player successfully hacks an uplink node.
     * Matches what CyberDoc and critical-failure recovery both do — full pool reset.
     *
     * Returns the restored current_uplink value.
     */
    public function restoreUplinkToFull(PlayerRig $rig): int
    {
        $rig->current_uplink = (int) ($rig->chassis->base_uplink ?? 3);
        $rig->save();
        return $rig->current_uplink;
    }

    /**
     * Restore up to $amount points of SS from a consumable repair kit.
     * Caps at maxSs. Returns the number of SS points actually restored.
     */
    public function repairPartial(PlayerRig $rig, int $amount): int
    {
        $max      = $this->maxSs($rig);
        $before   = (int) $rig->current_ss;
        $restored = min($amount, $max - $before);

        if ($restored > 0) {
            $rig->current_ss = $before + $restored;
            $rig->save();
        }

        return $restored;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Critical System Failure — SS hit 0.
     *
     * Unified outcome for both PvE and PvP:
     *   • Pocket creds wiped (pocket was at-risk; failure means you lost the run)
     *   • Bounty run counters reset
     *   • Player teleported to their last visited Street Doc node
     *   • SS stays at 0 — must pay for repair to hack again
     *   • is_limping cleared (this is beyond limping — it's full failure)
     */
    private function criticalFailure(PlayerRig $rig, ?Player $player): string
    {
        $rig->is_limping     = false;
        // Reset uplink so the next run starts with a full pool after repair.
        $rig->current_uplink = (int) ($rig->chassis->base_uplink ?? 3);
        // current_ss remains 0 — locked until repaired

        if ($player) {
            $player->pocket_creds          = 0;
            $player->cache                 = 0;
            $player->bounty_level          = 0;
            $player->bounty_multiplier     = 1.0;
            $player->is_open_season        = false;
            $player->nodes_hacked_this_run = 0;
            $player->pvp_wins_this_run     = 0;
            $player->is_limping            = false;

            // Teleport to a spawn node
            $spawnNode = \App\Models\Node::where('is_spawn', true)->inRandomOrder()->first();
            if ($spawnNode) {
                $player->current_node_id = $spawnNode->id;
            }
        }

        return 'critical_failure';
    }

    /**
     * Sum the boost amounts from every installed, undamaged peripheral the player
     * owns, grouped by stat name.
     *
     * Only peripherals where is_installed = true AND is_damaged = false contribute.
     * Peripheral boosts never trigger the dependency ring tax.
     *
     * @return array<string, int>
     */
    public function peripheralBoosts(Player $player): array
    {
        $boosts = ['cpu' => 0, 'ram' => 0, 'firewall' => 0, 'storage' => 0, 'os' => 0];

        // Re-use the already-loaded relationship to avoid an extra DB query when
        // this method is called inside a loop (e.g. NodeController::players()).
        $rows = $player->relationLoaded('playerPeripherals')
            ? $player->playerPeripherals
            : PlayerPeripheral::where('player_id', $player->id)
                ->where('is_installed', true)
                ->where('is_damaged', false)
                ->with('peripheral')
                ->get();

        $rows
            ->where('is_installed', true)
            ->where('is_damaged', false)
            ->each(function (PlayerPeripheral $pp) use (&$boosts): void {
                if ($pp->peripheral === null) return;
                $stat = $pp->peripheral->stat_boosted;
                if (array_key_exists($stat, $boosts)) {
                    $boosts[$stat] += $pp->peripheral->boost_amount;
                }
            });

        return $boosts;
    }

    /**
     * Sum of all five stat levels — the total points currently allocated.
     */
    public function totalPointsSpent(PlayerRig $rig): int
    {
        return $rig->cpu_level
            + $rig->ram_level
            + $rig->firewall_level
            + $rig->storage_level
            + $rig->os_level;
    }

    /**
     * Flat cred + TP cost to upgrade from BlackHat (Tier 1) to a NullTek Series 2 chassis.
     * Cost is the same regardless of which NullTek variant the player chooses.
     * This table is the server-authoritative source — the client must never provide costs.
     */
    private const CHASSIS_UPGRADE_COSTS = [
        'NullTek GX-7 Ghost'   => ['creds' => 5000, 'tp' => 25],
        'NullTek BR-9 Breaker' => ['creds' => 5000, 'tp' => 25],
        'NullTek VT-3 Vault'   => ['creds' => 5000, 'tp' => 25],
    ];

    /**
     * Return the cred + TP cost to upgrade to the named NullTek chassis.
     * Always computed server-side — the client must never control this value.
     *
     * @return array{creds: int, tp: int}
     */
    public function chassisUpgradeCost(string $chassisName): array
    {
        return self::CHASSIS_UPGRADE_COSTS[$chassisName] ?? ['creds' => 5000, 'tp' => 25];
    }

    /**
     * Compute the cred and TP cost for the next point in a given stat.
     *
     * Mirrors the formula in useUpgradeCosts.js exactly so the server is
     * always authoritative — the client-provided cred_cost/tp_cost must never
     * be trusted.
     *
     *   cost = baseCost
     *        × (1.60 ^ sameStatInvested)   — same-stat scaling
     *        × (1.25 ^ totalInvested)       — global progression scaling
     *        × (1.80 ^ (chassisTier − 1))  — chassis tier scaling
     *
     * @return array{creds: int, tp: int}
     * @throws InvalidArgumentException If $stat is not a recognised stat name.
     */
    public function statUpgradeCost(PlayerRig $rig, string $stat): array
    {
        $baseCosts = [
            'cpu'      => ['creds' => 150, 'tp' => 2],
            'ram'      => ['creds' => 100, 'tp' => 1],
            'os'       => ['creds' => 120, 'tp' => 1],
            'storage'  => ['creds' =>  80, 'tp' => 0],
            'firewall' => ['creds' => 200, 'tp' => 3],
        ];

        if (!isset($baseCosts[$stat])) {
            throw new InvalidArgumentException("Unknown stat '{$stat}'.");
        }

        $base = $baseCosts[$stat];

        // Points already invested in this specific stat (column starts at 0).
        $sameStatInvested = (int) $rig->{"{$stat}_level"};

        // Total points invested across all stats.
        $totalInvested = $this->totalPointsSpent($rig);

        // Chassis tier (1 for BlackHat, 2 for NullTek Series 2).
        $chassisTier = (int) ($rig->chassis->tier ?? 1);

        $multiplier = pow(1.60, $sameStatInvested)
                    * pow(1.25, $totalInvested)
                    * pow(1.80, $chassisTier - 1);

        return [
            'creds' => (int) round($base['creds'] * $multiplier),
            'tp'    => (int) round($base['tp']    * $multiplier),
        ];
    }

    /**
     * Walk the stat ring starting immediately after $upgradeStat and downgrade
     * the first stat found whose level is above the minimum.
     *
     * Returns the name of the stat that was downgraded.
     *
     * @throws RuntimeException If every other stat is already at minimum level.
     */
    private function applyParasiteDowngrade(PlayerRig $rig, string $upgradeStat): string
    {
        $ring = self::STAT_RING;
        $ringSize = count($ring);
        $startIndex = array_search($upgradeStat, $ring, true);

        // $upgradeStat may not be in the ring (e.g. a future peripheral stat),
        // so fall back to starting at index 0.
        if ($startIndex === false) {
            $startIndex = 0;
        }

        for ($offset = 1; $offset < $ringSize; $offset++) {
            $candidate = $ring[($startIndex + $offset) % $ringSize];
            $column = "{$candidate}_level";

            if ($rig->$column > self::MIN_LEVEL) {
                $rig->$column -= 1;
                return $candidate;
            }
        }

        throw new RuntimeException(
            "Cannot upgrade '{$upgradeStat}': the point cap is full and all other stats are at minimum level."
        );
    }

    /**
     * Enforce both RAM and CPU stat caps for a player after a stat change
     * (reallocation, peripheral install, etc.).
     *
     * - RAM cap: deactivates loadout slots from the top down if effective RAM dropped.
     * - CPU cap: deactivates commands whose level now exceeds effective CPU.
     *
     * Returns the deactivated slot numbers and command IDs so callers can
     * surface the changes to the client.
     *
     * @return array{ deactivated_ram: int[], deactivated_cpu: string[] }
     */
    public function enforceStatCaps(Player $player, PlayerRig $rig): array
    {
        // Guarantee the chassis relationship is loaded so effectiveStats() can
        // access $rig->chassis->base_* without a null dereference — even if the
        // rig was fetched without eager-loading (e.g. PlayerRig::find($id)).
        $rig->loadMissing('chassis');

        $stats = $this->effectiveStats($rig, $player);

        // CPU check runs FIRST — deactivates commands whose level exceeds effective CPU.
        // Those commands are now is_active = false before the RAM check reads the
        // active list, so they cannot appear in both return arrays. This eliminates
        // duplicate reporting without any post-hoc de-duplication.
        $droppedByCpu = $this->enforceCpuCommandCap($player, $stats['cpu']['effective']);

        // RAM check runs SECOND — only sees the commands still active after the CPU
        // filter, so its slot-drop count is always accurate.
        $droppedByRam = $this->enforceRamCap($player, $stats['ram']['effective']);

        return [
            'deactivated_ram' => $droppedByRam,
            'deactivated_cpu' => $droppedByCpu,
        ];
    }

    /**
     * Deactivate loadout slots from the highest slot downward until the active
     * command count fits within $maxSlots (the new effective RAM value).
     *
     * Returns an array of slot numbers that were dropped (e.g. [4, 3]).
     */
    private function enforceRamCap(Player $player, int $maxSlots): array
    {
        return \DB::transaction(function () use ($player, $maxSlots) {
            $active = \DB::table('player_commands')
                ->where('player_id', $player->id)
                ->where('is_active', true)
                ->whereNotNull('loadout_slot')
                ->orderBy('loadout_slot', 'desc')
                ->lockForUpdate()
                ->get(['command_id', 'loadout_slot']);

            $excess = $active->count() - $maxSlots;
            if ($excess <= 0) {
                return [];
            }

            $toDrop    = $active->take($excess);
            $dropIds   = $toDrop->pluck('command_id')->toArray();
            $dropSlots = $toDrop->pluck('loadout_slot')->toArray();

            \DB::table('player_commands')
                ->where('player_id', $player->id)
                ->whereIn('command_id', $dropIds)
                ->update(['is_active' => false, 'loadout_slot' => null]);

            return $dropSlots;
        });
    }

    /**
     * Deactivate any active command whose installed level exceeds $maxLevel
     * (the new effective CPU value — CPU is the command-level cap).
     *
     * Returns an array of command_ids that were deactivated.
     */
    private function enforceCpuCommandCap(Player $player, int $maxLevel): array
    {
        return \DB::transaction(function () use ($player, $maxLevel) {
            $overCap = \DB::table('player_commands')
                ->where('player_id', $player->id)
                ->where('is_active', true)
                ->where('level', '>', $maxLevel)
                ->lockForUpdate()
                ->pluck('command_id')
                ->toArray();

            if (empty($overCap)) {
                return [];
            }

            \DB::table('player_commands')
                ->where('player_id', $player->id)
                ->whereIn('command_id', $overCap)
                ->update(['is_active' => false, 'loadout_slot' => null]);

            return $overCap;
        });
    }

    /**
     * Compute temporary stat penalties based on how much SS has been lost.
     *
     * Penalties are cumulative across tiers and lift automatically when SS
     * is restored by the Street Doc. No DB columns are written — purely derived.
     *
     * Returns a map of stat → penalty (all values ≤ 0).
     */
    private function degradationPenalties(PlayerRig $rig): array
    {
        $penalties = ['cpu' => 0, 'ram' => 0, 'os' => 0, 'firewall' => 0, 'storage' => 0];

        $maxSs = $this->maxSs($rig);
        if ($maxSs === 0 || $rig->current_ss >= $maxSs) {
            return $penalties;
        }

        $lostPct = (($maxSs - $rig->current_ss) / $maxSs) * 100;

        foreach (self::DEGRADATION_TIERS as $threshold => $deltas) {
            if ($lostPct >= $threshold) {
                foreach ($deltas as $stat => $delta) {
                    $penalties[$stat] += $delta;
                }
            }
        }

        return $penalties;
    }
}
