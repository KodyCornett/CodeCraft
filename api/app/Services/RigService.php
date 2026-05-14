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

    /** System Stability granted per OS level. */
    private const SS_PER_OS_LEVEL = 10;

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
        $taxEvent = null;
        $osChanged = false;

        // A single stat cannot exceed the chassis total cap (would leave all
        // other stats at zero, which the ring cannot recover from).
        if ($rig->$column >= $chassis->total_point_cap) {
            throw new \OverflowException(
                "'{$stat}' is already at the chassis maximum ({$chassis->total_point_cap})."
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
            $osChanged = ($taxedStat === 'os');
        }

        $rig->$column += 1;

        if ($stat === 'os' || $osChanged) {
            $rig->current_ss = $rig->os_level * self::SS_PER_OS_LEVEL;
        }

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
     * Cred check stub — always passes until the creds system is implemented.
     */
    public function hasEnoughCredsForUpgrade(Player $player): bool
    {
        return true;
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
     * When SS reaches 0 the outcome depends on the damage source:
     *   - 'pve' → Limp Mode: rig stays alive at 1 SS, is_limping set on both rig
     *             and player record.
     *   - 'pvp' → Street Doc Reset: rig restored to full SS, limp cleared on both
     *             records, player position reset to last_street_doc_id.
     *             Stat loss and cred steal are handled by POST /api/combat/result.
     *
     * Returns an array with the updated rig, player, and a nullable event string
     * ('limp_mode' | 'street_doc_reset' | null).
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
            if ($source === 'pvp') {
                $event = $this->resetToStreetDoc($rig, $player);
            } else {
                $event = $this->enterLimpMode($rig, $player);
            }
        }

        $rig->save();
        $player?->save();

        return ['rig' => $rig, 'player' => $player, 'event' => $event];
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

        $numericStats = [
            'cpu'      => [$rig->cpu_level,      $chassis->base_cpu],
            'ram'      => [$rig->ram_level,       $chassis->base_ram],
            'firewall' => [$rig->firewall_level,  $chassis->base_firewall],
            'storage'  => [$rig->storage_level,   $chassis->base_storage],
        ];

        $stats = [];
        foreach ($numericStats as $name => [$level, $base]) {
            $boost = $boosts[$name];
            $stats[$name] = [
                'level'            => $level,
                'base'             => $base,
                'peripheral_boost' => $boost,
                'effective'        => $base + $level + $boost,
            ];
        }

        // OS has a string base (OS name) and a numeric level; effective = level + boost.
        $osBoost = $boosts['os'];
        $stats['os'] = [
            'level'            => $rig->os_level,
            'base'             => $chassis->base_os,
            'peripheral_boost' => $osBoost,
            'effective'        => $rig->os_level + $osBoost,
        ];

        return $stats;
    }

    /**
     * Maximum System Stability for the rig, derived from os_level.
     */
    public function maxSs(PlayerRig $rig): int
    {
        return $rig->os_level * self::SS_PER_OS_LEVEL;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function enterLimpMode(PlayerRig $rig, ?Player $player): string
    {
        $rig->is_limping = true;
        $rig->current_ss = 1;

        if ($player) {
            $player->is_limping = true;
        }

        return 'limp_mode';
    }

    private function resetToStreetDoc(PlayerRig $rig, ?Player $player): string
    {
        // Lose 1 level on a randomly chosen stat that is above the minimum.
        $eligible = array_filter(
            self::VALID_STATS,
            fn (string $s) => $rig->{"{$s}_level"} > self::MIN_LEVEL,
        );

        if (!empty($eligible)) {
            $lost   = array_values($eligible)[array_rand($eligible)];
            $rig->{"{$lost}_level"} -= 1;
        }

        $rig->is_limping = false;
        // Recalculate max_ss after potential OS loss before restoring current_ss.
        $rig->current_ss = $this->maxSs($rig);

        if ($player) {
            $player->is_limping    = false;
            $player->current_node_id = $player->last_street_doc_id;
        }

        return 'street_doc_reset';
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

        PlayerPeripheral::where('player_id', $player->id)
            ->where('is_installed', true)
            ->where('is_damaged', false)
            ->with('peripheral')
            ->get()
            ->each(function (PlayerPeripheral $pp) use (&$boosts): void {
                $stat = $pp->peripheral->stat_boosted;
                $boosts[$stat] += $pp->peripheral->boost_amount;
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
}
