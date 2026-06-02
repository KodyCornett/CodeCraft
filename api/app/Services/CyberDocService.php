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
 *  - Loadout slot count is chassis-based and expandable via command_module peripherals.
 *  - Hardware encrypts must be installed here to take effect.
 *  - Stat reallocation moves 1 level from one stat to another for a cred fee.
 *    It does NOT trigger the ring tax (it's a direct swap, not an upgrade).
 *  - bankCreds banks all pocket_creds into the wallet, resets bounty counters.
 *    This is the extract operation.
 */
class CyberDocService
{
    /** Minimum level any stat may be reduced to (mirrors RigService::MIN_LEVEL). */
    private const MIN_LEVEL             = 1;
    private const VALID_STATS           = ['os', 'ram', 'cpu', 'storage', 'firewall'];
    private const CYBERDOC_COOLDOWN_SEC = 600;  // 10 minutes

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
     * All mutations (pocket zero, wallet credit, uplink restore)
     * run inside a single DB transaction so a mid-operation failure cannot
     * permanently destroy the player's pocket without crediting the wallet.
     *
     * Returns ['pocket_banked' => int].
     */
    public function bankCreds(Player $player, ?string $cyberdocCanvasId = null): array
    {
        $pocketBanked = 0;

        DB::transaction(function () use ($player, $cyberdocCanvasId, &$pocketBanked) {
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

            // Record cooldown for this CyberDoc if a canvas ID was provided.
            if ($cyberdocCanvasId !== null) {
                $this->recordCooldown($player, $cyberdocCanvasId);
            }

            $player->save();
            // Note: current_uplink is reset in CyberDocController::visit(), not here.
            // Uplink restores when the player opens the storefront, not when they bank.
        });

        return ['pocket_banked' => $pocketBanked];
    }

    /**
     * Return the seconds remaining on a CyberDoc cooldown, or 0 if clear.
     */
    public function cooldownRemaining(Player $player, string $cyberdocCanvasId): int
    {
        $cooldowns = $player->cyberdoc_cooldowns ?? [];
        if (!isset($cooldowns[$cyberdocCanvasId])) {
            return 0;
        }

        $elapsed = time() - (int) $cooldowns[$cyberdocCanvasId];
        return max(0, self::CYBERDOC_COOLDOWN_SEC - $elapsed);
    }

    /**
     * Record a visit cooldown for the given CyberDoc canvas ID.
     * Call this inside a DB transaction that already has the player locked.
     */
    private function recordCooldown(Player $player, string $cyberdocCanvasId): void
    {
        $cooldowns                      = $player->cyberdoc_cooldowns ?? [];
        $cooldowns[$cyberdocCanvasId]   = time();
        $player->cyberdoc_cooldowns     = $cooldowns;
    }

    /**
     * Throw if the given CyberDoc is still on cooldown for this player.
     *
     * @throws RuntimeException
     */
    private function assertCooldown(Player $player, string $cyberdocCanvasId): void
    {
        $remaining = $this->cooldownRemaining($player, $cyberdocCanvasId);
        if ($remaining > 0) {
            $mins = (int) ceil($remaining / 60);
            throw new RuntimeException(
                "This CyberDoc is on cooldown. Available again in {$mins} minute(s)."
            );
        }
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
        });
    }

    /**
     * Calculate the cred cost to repair the player's SS.
     *
     * Linear formula — 150 ₡ per 25% of max SS lost:
     *   cost = floor((missing / max) × 600)
     *
     * Examples (max_ss = 100):
     *   25 missing (25%) → 150 ₡
     *   50 missing (50%) → 300 ₡
     *   75 missing (75%) → 450 ₡
     *  100 missing (100%) → 600 ₡
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

        return (int) floor(($missingSs / $maxSs) * 600);
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
     * Set the player's active command loadout.
     *
     * Rules:
     *  - All command IDs must be owned by the player.
     *  - Slot capacity comes from the chassis base slots + installed command_module
     *    peripherals (Nav Wraith = map slot, ICE Pick = hack slot). RAM no longer
     *    gates slot count.
     *  - Map commands may occupy map slots or open slots.
     *  - Hack commands may occupy hack slots or open slots.
     *  - Total active commands may not exceed total available slots.
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

        $slots = $this->rigService->loadoutSlots($rig, $player);

        if (count($activeCommandIds) > $slots['total']) {
            throw new InvalidArgumentException(
                "Loadout exceeds available slots. Max: {$slots['total']}, requested: " . count($activeCommandIds) . '.'
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

        // Validate typed slot capacity: map commands need map or open slots;
        // hack commands need hack or open slots.
        if (!empty($activeCommandIds)) {
            $commandContexts = DB::table('commands')
                ->whereIn('id', $activeCommandIds)
                ->pluck('context', 'id');

            $mapCount  = collect($commandContexts)->filter(fn ($c) => $c === 'map')->count();
            $hackCount = collect($commandContexts)->filter(fn ($c) => $c === 'hack')->count();

            if ($mapCount > ($slots['map'] + $slots['open'])) {
                throw new InvalidArgumentException(
                    "Too many map commands. Available map/open slots: " . ($slots['map'] + $slots['open']) . ", requested: {$mapCount}."
                );
            }
            if ($hackCount > ($slots['hack'] + $slots['open'])) {
                throw new InvalidArgumentException(
                    "Too many hack commands. Available hack/open slots: " . ($slots['hack'] + $slots['open']) . ", requested: {$hackCount}."
                );
            }
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
