<?php

namespace App\Services;

use App\Models\Command;
use App\Models\HardwareEncrypt;
use App\Models\Player;
use App\Models\PlayerPeripheral;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * All CyberDoc interactions: banking, repair, loadout, installs,
 * stat reallocation, command upgrading.
 *
 * Rules:
 *  - Loadout can only be changed at a CyberDoc.
 *  - Active command count is capped by the player's effective RAM stat.
 *  - Hardware encrypts must be installed here to take effect.
 *  - Stat reallocation moves 1 level from one stat to another for a cred fee.
 *    It does NOT trigger the ring tax (it's a direct swap, not an upgrade).
 *  - bankCreds banks all pocket_creds into the wallet, resets bounty counters,
 *    and clears the cache. This is the extract operation.
 */
class CyberDocService
{
    /** Minimum level any stat may be reduced to (mirrors RigService::MIN_LEVEL). */
    private const MIN_LEVEL   = 1;
    private const VALID_STATS = ['os', 'ram', 'cpu', 'storage', 'firewall'];

    public function __construct(
        private readonly RigService    $rigService,
        private readonly BountyService $bountyService,
    ) {}

    // -------------------------------------------------------------------------
    // Banking — pocket → wallet extract
    // -------------------------------------------------------------------------

    /**
     * Bank all pocket_creds into the player's safe wallet.
     * Resets bounty run counters and clears the run state.
     *
     * All mutations (pocket zero, wallet credit, cache clear, uplink restore)
     * run inside a single DB transaction so a mid-operation failure cannot
     * permanently destroy the player's pocket without crediting the wallet.
     *
     * Returns ['pocket_banked' => int].
     */
    public function bankCreds(Player $player): array
    {
        $pocketBanked = 0;

        DB::transaction(function () use ($player, &$pocketBanked) {
            // Run extraction whenever there is any run state to reset — not just
            // when pocket_creds > 0. A player with 0 pocket but nonzero hacks or
            // bounty still needs their counters cleared and bounty reset.
            if ($player->nodes_hacked_this_run > 0 || $player->pvp_wins_this_run > 0 || $player->pocket_creds > 0 || $player->bounty_level > 0) {
                $pocketBanked = $this->bountyService->extractToCyberDoc($player);

                if ($pocketBanked > 0) {
                    $player->wallet_creds = (int) ($player->wallet_creds ?? 0) + $pocketBanked;
                    $player->save();
                }
            }

            // Clear the hack cache so the next run starts unblocked.
            $player->cache = 0;
            $player->save();

            // Reset current_uplink to chassis base so the next run starts with a full pool.
            $rig = $this->rigService->getRigForPlayer($player);
            if ($rig !== null) {
                $rig->current_uplink = (int) ($rig->chassis->base_uplink ?? 3);
                $rig->save();
            }
        });

        return ['pocket_banked' => $pocketBanked];
    }

    // -------------------------------------------------------------------------
    // SS Repair
    // -------------------------------------------------------------------------

    /**
     * Restore the player's SS to maximum.
     *
     * @throws RuntimeException When the player has no rig.
     */
    public function repairSS(Player $player, int $credCost): void
    {
        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig === null) {
            throw new RuntimeException('Player has no rig to repair.');
        }

        if ($credCost > 0 && ($player->wallet_creds ?? 0) < $credCost) {
            throw new RuntimeException(
                "Insufficient creds. Need {$credCost}, have " . ($player->wallet_creds ?? 0) . '.'
            );
        }

        // Wrap deduction + repair in a transaction so a repair() failure cannot
        // permanently orphan the wallet deduction (same pattern as RigController::upgrade).
        DB::transaction(function () use ($player, $rig, $credCost) {
            if ($credCost > 0) {
                $player->wallet_creds = (int) ($player->wallet_creds ?? 0) - $credCost;
                $player->save();
            }

            $this->rigService->repair($rig, $player, repairPeripherals: false);

            if ($player->is_limping) {
                $player->is_limping = false;
                $player->save();
            }
        });
    }

    /**
     * Calculate the cred cost to repair the player's SS.
     *
     * Nonlinear formula — deeper damage = more expensive per SS point:
     *   cost = floor((missing / max) × missing × 25)
     *
     * @throws RuntimeException When the player has no rig.
     */
    public function repairCost(Player $player): int
    {
        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig === null) {
            throw new RuntimeException('Player has no rig.');
        }

        $maxSs     = $this->rigService->maxSs($rig);
        $missingSs = max(0, $maxSs - $rig->current_ss);

        if ($missingSs === 0 || $maxSs === 0) return 0;

        return (int) floor(($missingSs / $maxSs) * $missingSs * 25);
    }

    // -------------------------------------------------------------------------
    // Hardware Encrypt Installation
    // -------------------------------------------------------------------------

    /**
     * Install a hardware encrypt from the player's inventory onto their rig.
     *
     * @throws InvalidArgumentException When the encrypt is not found or already installed.
     * @throws RuntimeException         When the rig has insufficient port capacity.
     */
    public function installEncrypt(Player $player, string $encryptId): PlayerPeripheral
    {
        $encrypt = HardwareEncrypt::where('id', $encryptId)
            ->where('player_id', $player->id)
            ->first();

        if ($encrypt === null) {
            throw new InvalidArgumentException("Hardware encrypt '{$encryptId}' not found for this player.");
        }

        if ($encrypt->is_installed) {
            throw new InvalidArgumentException("Hardware encrypt '{$encryptId}' is already installed.");
        }

        $peripheral = $encrypt->peripheral;

        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig !== null) {
            $usedPorts  = PlayerPeripheral::where('player_id', $player->id)
                ->where('is_installed', true)
                ->join('peripherals', 'peripherals.id', '=', 'player_peripherals.peripheral_id')
                ->sum('peripherals.port_cost');
            $totalSlots = $rig->chassis->peripheral_slots ?? 4;

            if (($usedPorts + $peripheral->port_cost) > $totalSlots) {
                throw new RuntimeException(
                    "Insufficient port slots. Used: {$usedPorts}, needed: {$peripheral->port_cost}, total: {$totalSlots}."
                );
            }
        }

        return DB::transaction(function () use ($player, $encrypt, $peripheral) {
            $encrypt->is_installed = true;
            $encrypt->save();

            return PlayerPeripheral::create([
                'player_id'     => $player->id,
                'peripheral_id' => $peripheral->id,
                'is_installed'  => true,
                'is_damaged'    => false,
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Loadout management
    // -------------------------------------------------------------------------

    /**
     * Set the player's active combat command loadout.
     *
     * Rules:
     *  - All command IDs must be owned by the player.
     *  - Active count may not exceed the player's effective RAM stat.
     *
     * @param  array<string> $activeCommandIds  UUIDs of commands to mark active.
     * @throws InvalidArgumentException On invalid commands or slot overflow.
     * @throws RuntimeException         When the player has no rig.
     */
    public function setLoadout(Player $player, array $activeCommandIds): void
    {
        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig === null) {
            throw new RuntimeException('Player has no rig — cannot determine loadout slot count.');
        }

        $effectiveStats = $this->rigService->effectiveStats($rig, $player);
        $maxSlots       = $effectiveStats['ram']['effective'];

        if (count($activeCommandIds) > $maxSlots) {
            throw new InvalidArgumentException(
                "Loadout exceeds available slots. Max: {$maxSlots}, requested: " . count($activeCommandIds) . '.'
            );
        }

        $ownedIds = DB::table('player_commands')
            ->where('player_id', $player->id)
            ->pluck('command_id')
            ->toArray();

        $unowned = array_diff($activeCommandIds, $ownedIds);
        if (!empty($unowned)) {
            throw new InvalidArgumentException(
                'Player does not own command(s): ' . implode(', ', $unowned)
            );
        }

        DB::transaction(function () use ($player, $activeCommandIds) {
            DB::table('player_commands')
                ->where('player_id', $player->id)
                ->update(['is_active' => false]);

            foreach ($activeCommandIds as $index => $commandId) {
                DB::table('player_commands')
                    ->where('player_id', $player->id)
                    ->where('command_id', $commandId)
                    ->update(['is_active' => true, 'loadout_slot' => $index + 1]);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Command upgrading
    // -------------------------------------------------------------------------

    /**
     * Upgrade an owned command by one level (costs tech_points).
     *
     * @throws InvalidArgumentException When the command is not owned, already maxed, or TP is insufficient.
     */
    public function upgradeCommand(Player $player, string $commandId): int
    {
        $command = Command::find($commandId);
        if ($command === null) {
            throw new InvalidArgumentException("Command '{$commandId}' not found.");
        }

        $pivot = DB::table('player_commands')
            ->where('player_id', $player->id)
            ->where('command_id', $commandId)
            ->first();

        if ($pivot === null) {
            throw new InvalidArgumentException('Player does not own this command.');
        }

        $currentLevel = (int) $pivot->level;
        $maxLevel     = (int) $command->max_level;

        if ($currentLevel >= $maxLevel) {
            throw new InvalidArgumentException(
                "Command '{$command->name}' is already at max level ({$maxLevel})."
            );
        }

        $cost = (int) $command->upgrade_cost_tp;
        if (($player->tech_points ?? 0) < $cost) {
            throw new InvalidArgumentException(
                "Insufficient Tech Points. Need {$cost}, have " . ($player->tech_points ?? 0) . '.'
            );
        }

        DB::transaction(function () use ($player, $commandId, $currentLevel, $cost) {
            DB::table('player_commands')
                ->where('player_id', $player->id)
                ->where('command_id', $commandId)
                ->update(['level' => $currentLevel + 1]);

            // tech_points is stored as decimal:2 — use float arithmetic, not (int),
            // to avoid truncating the fractional part before the subtraction.
            $player->tech_points = round((float) ($player->tech_points ?? 0) - $cost, 2);
            $player->save();
        });

        return $currentLevel + 1;
    }

    // -------------------------------------------------------------------------
    // Stat reallocation
    // -------------------------------------------------------------------------

    /**
     * Move exactly 1 upgrade level from $fromStat to $toStat.
     * This is NOT an upgrade — the ring tax does NOT apply.
     *
     * @throws InvalidArgumentException On invalid stat names or insufficient level.
     * @throws RuntimeException         When the player has no rig.
     */
    public function reallocateStats(Player $player, string $fromStat, string $toStat): array
    {
        if (!in_array($fromStat, self::VALID_STATS, true)) {
            throw new InvalidArgumentException("Invalid stat '{$fromStat}'.");
        }
        if (!in_array($toStat, self::VALID_STATS, true)) {
            throw new InvalidArgumentException("Invalid stat '{$toStat}'.");
        }
        if ($fromStat === $toStat) {
            throw new InvalidArgumentException('Cannot reallocate a stat onto itself.');
        }

        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig === null) {
            throw new RuntimeException('Player has no rig.');
        }

        $fromCol = "{$fromStat}_level";
        $toCol   = "{$toStat}_level";

        if ($rig->$fromCol <= self::MIN_LEVEL) {
            throw new InvalidArgumentException(
                "Cannot reallocate from '{$fromStat}' — already at minimum level ({$rig->$fromCol})."
            );
        }

        $toCapKey = "cap_{$toStat}";
        if ($rig->$toCol >= $rig->chassis->$toCapKey) {
            throw new InvalidArgumentException(
                "Cannot reallocate to '{$toStat}' — already at chassis cap ({$rig->chassis->$toCapKey})."
            );
        }

        $rig->$fromCol -= 1;
        $rig->$toCol   += 1;
        $rig->save();

        // Reducing RAM or CPU can invalidate the current loadout:
        //   • RAM drop: too many active commands for the new slot count
        //   • CPU drop: equipped commands whose level now exceeds effective CPU
        // enforceStatCaps() deactivates any overflowing commands automatically.
        $caps = $this->rigService->enforceStatCaps($player, $rig);

        return [
            'rig'             => $rig,
            'from'            => ['stat' => $fromStat, 'new_level' => $rig->$fromCol],
            'to'              => ['stat' => $toStat,   'new_level' => $rig->$toCol],
            'deactivated_ram' => $caps['deactivated_ram'],
            'deactivated_cpu' => $caps['deactivated_cpu'],
        ];
    }
}
