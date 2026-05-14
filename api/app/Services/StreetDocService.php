<?php

namespace App\Services;

use App\Models\HardwareEncrypt;
use App\Models\Player;
use App\Models\PlayerPeripheral;
use App\Models\StreetDoc;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * All Street Doc interactions: repair, loadout, installs, stat reallocation, extract.
 *
 * Rules:
 *  - Loadout can only be changed at a Street Doc.
 *  - Active command count is capped by the player's effective RAM stat.
 *  - Hardware encrypts must be installed here to take effect.
 *  - Stat reallocation moves 1 level from one stat to another for a cred fee.
 *    It does NOT trigger the ring tax (it's a direct swap, not an upgrade).
 *  - visitStreetDoc registers the visit and triggers extract if the player has
 *    an active bounty run.
 */
class StreetDocService
{
    /** Minimum level any stat may be reduced to (mirrors RigService::MIN_LEVEL). */
    private const MIN_LEVEL   = 1;
    private const VALID_STATS = ['os', 'ram', 'cpu', 'storage', 'firewall'];

    public function __construct(
        private readonly RigService    $rigService,
        private readonly BountyService $bountyService,
    ) {}

    // -------------------------------------------------------------------------
    // Visit registration
    // -------------------------------------------------------------------------

    /**
     * Register that the player arrived at a Street Doc.
     * Sets last_street_doc_id and triggers extract if the player has an active run.
     *
     * @throws InvalidArgumentException When the Street Doc is not found.
     */
    public function visitStreetDoc(Player $player, string $streetDocId): StreetDoc
    {
        $doc = StreetDoc::find($streetDocId);
        if ($doc === null) {
            throw new InvalidArgumentException("Street Doc '{$streetDocId}' not found.");
        }

        $player->last_street_doc_id = $streetDocId;

        // Extract if player has an active bounty run (any nodes hacked or PvP wins)
        if ($player->nodes_hacked_this_run > 0 || $player->pvp_wins_this_run > 0) {
            $this->bountyService->extractToStreetDoc($player);
        } else {
            $player->save();
        }

        return $doc;
    }

    // -------------------------------------------------------------------------
    // SS Repair
    // -------------------------------------------------------------------------

    /**
     * Restore the player's SS to maximum.
     * The cred cost is validated (stub — creds system not yet built).
     *
     * @throws RuntimeException When the player has no rig.
     */
    public function repairSS(Player $player, int $credCost): void
    {
        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig === null) {
            throw new RuntimeException('Player has no rig to repair.');
        }

        // Stub: cred deduction — always passes until creds system is implemented.
        // TODO: deduct $credCost from player credits when cred system is built.

        $this->rigService->repair($rig, $player, repairPeripherals: false);

        // Clear limp mode on the player record as well
        if ($player->is_limping) {
            $player->is_limping = false;
            $player->save();
        }
    }

    /**
     * Calculate the cred cost to repair the player's SS.
     * Formula: missing_ss × 2 (minimum 0).
     *
     * @throws RuntimeException When the player has no rig.
     */
    public function repairCost(Player $player): int
    {
        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig === null) {
            throw new RuntimeException('Player has no rig.');
        }

        $maxSs    = $rig->os_level * 10;
        $missingSs = max(0, $maxSs - $rig->current_ss);
        return $missingSs * 2;
    }

    // -------------------------------------------------------------------------
    // Hardware Encrypt Installation
    // -------------------------------------------------------------------------

    /**
     * Install a hardware encrypt from the player's inventory onto their rig.
     * Creates a PlayerPeripheral record (is_installed = true) and marks the
     * encrypt as installed so it cannot be installed again.
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

        // Check port capacity
        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig !== null) {
            $usedPorts   = PlayerPeripheral::where('player_id', $player->id)
                ->where('is_installed', true)
                ->join('peripherals', 'peripherals.id', '=', 'player_peripherals.peripheral_id')
                ->sum('peripherals.port_cost');
            $totalSlots  = $rig->chassis->peripheral_slots ?? 4;

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
     *  - All command IDs must be owned by the player (in player_commands).
     *  - Active count may not exceed the player's effective RAM stat.
     *
     * @param  array<string> $activeCommandIds  UUIDs of commands to mark active.
     * @throws InvalidArgumentException On invalid commands or slot overflow.
     * @throws RuntimeException         When the player has no rig (can't determine RAM).
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

        // Validate ownership
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
            // Deactivate all
            DB::table('player_commands')
                ->where('player_id', $player->id)
                ->update(['is_active' => false]);

            // Activate selected
            if (!empty($activeCommandIds)) {
                DB::table('player_commands')
                    ->where('player_id', $player->id)
                    ->whereIn('command_id', $activeCommandIds)
                    ->update(['is_active' => true]);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Stat reallocation
    // -------------------------------------------------------------------------

    /**
     * Move exactly 1 upgrade level from $fromStat to $toStat.
     *
     * This is NOT an upgrade — the ring tax does NOT apply.
     * It is a direct swap that costs creds (stub).
     *
     * Recalculates current_ss if OS is involved.
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

        // Stub: cred fee deduction — always passes for now.

        $rig->$fromCol -= 1;
        $rig->$toCol   += 1;

        // Recalculate SS if OS changed
        if ($fromStat === 'os' || $toStat === 'os') {
            $rig->current_ss = min($rig->current_ss, $rig->os_level * 10);
        }

        $rig->save();

        return [
            'rig'    => $rig,
            'from'   => ['stat' => $fromStat, 'new_level' => $rig->$fromCol],
            'to'     => ['stat' => $toStat,   'new_level' => $rig->$toCol],
        ];
    }
}
