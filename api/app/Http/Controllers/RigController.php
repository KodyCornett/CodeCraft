<?php

namespace App\Http\Controllers;

use App\Models\ChassisTemplate;
use App\Models\Player;
use App\Services\CyberDocService;
use App\Services\RigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RigController extends Controller
{
    public function __construct(
        private readonly RigService      $rigService,
        private readonly CyberDocService $cyberDocService,
    ) {}

    /**
     * GET /api/rig?player_id={uuid}
     *
     * Returns the rig's current effective stats for the given player,
     * including per-stat peripheral boosts and total point accounting.
     */
    public function show(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();

        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $rig = $this->rigService->getRigForPlayer($player);

        if ($rig === null) {
            return response()->json(['message' => 'No rig found for this player.'], 404);
        }

        return response()->json([
            'rig_id'      => $rig->id,
            'chassis'     => $rig->chassis->name,
            'is_limping'  => $rig->is_limping,
            'current_ss'  => $rig->current_ss,
            'max_ss'      => $this->rigService->maxSs($rig),
            'stats'       => $this->rigService->effectiveStats($rig, $player),
            'points'      => [
                'spent' => $this->rigService->totalPointsSpent($rig),
                'cap'   => $rig->chassis->total_point_cap,
            ],
        ]);
    }

    /**
     * POST /api/rig/damage
     *
     * Applies PvE damage to the player's rig.
     * The server resolves the node ICE and computes the damage itself using the
     * player's current effective Firewall — the client must not send an amount.
     *
     * Formula: max(1, nodeICE − effectiveFirewall)
     *
     * Request body:
     *   node_canvas_id  string  — canvas ID of the node where the breach failed
     *   source          string  — 'pve' (only pve is accepted here; pvp damage
     *                             is handled server-side in CombatController)
     *
     * Response includes the updated rig snapshot and a nullable `event` field:
     *   null              — SS reduced but rig still standing
     *   'critical_failure'— SS hit 0; pocket wiped, bounty reset, spawn returned
     */
    public function damage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'node_canvas_id' => 'required|string',
            'source'         => 'required|string|in:pve',
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();

        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $rig = $this->rigService->getRigForPlayer($player);

        if ($rig === null) {
            return response()->json(['message' => 'No rig found for this player.'], 404);
        }

        // Resolve the node and compute server-authoritative PvE damage
        $node          = \App\Models\Node::where('canvas_id', $data['node_canvas_id'])->first();
        $nodeIce       = $node?->ice ?? 3;   // default ICE 3 if node lookup fails
        $stats         = $this->rigService->effectiveStats($rig, $player);
        $effectiveFw   = $stats['firewall']['effective'] ?? 1;
        $amount        = max(1, $nodeIce - $effectiveFw);

        [
            'rig'             => $rig,
            'event'           => $event,
            'deactivated_ram' => $deactivatedRam,
            'deactivated_cpu' => $deactivatedCpu,
        ] = $this->rigService->applyDamage(
            $rig,
            $amount,
            $data['source'],
            $player,
        );

        $response = [
            'event'               => $event,
            'rig_id'              => $rig->id,
            'is_limping'          => $rig->is_limping,
            'current_ss'          => $rig->current_ss,
            'max_ss'              => $this->rigService->maxSs($rig),
            // Slots/commands deactivated by SS degradation — client uses these to
            // flash a warning and update the loadout display without a round-trip.
            'deactivated_commands' => [
                'ram_cap' => $deactivatedRam, // slot numbers dropped (e.g. [4, 3])
                'cpu_cap' => $deactivatedCpu, // command_ids over the CPU level cap
            ],
        ];

        // On critical failure include the data the client needs to sync state
        // and teleport the player to the spawn node they were actually sent to.
        // criticalFailure() already wrote current_node_id — re-use that record
        // so the client canvas_id matches where the server persisted the player.
        if ($event === 'critical_failure') {
            $spawnCanvasId = \App\Models\Node::find($player->current_node_id)?->canvas_id;

            $response['critical_failure'] = [
                'pocket_creds'      => 0,
                'bounty_level'      => 0,
                'bounty_multiplier' => 1.0,
                'is_open_season'    => false,
                'respawn_canvas_id' => $spawnCanvasId,
                'repair_cost'       => $this->cyberDocService->repairCost($player),
            ];
        }

        return response()->json($response);
    }

    /**
     * POST /api/rig/upgrade
     *
     * Upgrades one stat level on the player's rig.
     * If the chassis point cap is reached the Parasite Stat is automatically
     * taxed; the tax event is returned so the client can animate the change.
     *
     * Request body:
     *   player_id  string (uuid)
     *   stat       string — one of: cpu, ram, firewall, storage, os
     *
     * 422 returned when:
     *   - stat is already at the chassis maximum
     *   - all other stats are at minimum (tax impossible)
     *   - player has insufficient creds or Tech Points
     */
    public function upgrade(Request $request): JsonResponse
    {
        $data = $request->validate([
            'stat' => 'required|string|in:cpu,ram,firewall,storage,os',
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $node = \App\Models\Node::find($player->current_node_id);
        if ($node === null || $node->type !== 'cyberdoc') {
            return response()->json(['message' => 'You must be at a CyberDoc terminal.'], 403);
        }

        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig === null) {
            return response()->json(['message' => 'No rig found for this player.'], 404);
        }

        // Cost is always computed server-side — the client never controls the price.
        $cost     = $this->rigService->statUpgradeCost($rig, $data['stat']);
        $credCost = $cost['creds'];
        $tpCost   = $cost['tp'];

        if (($player->wallet_creds ?? 0) < $credCost) {
            return response()->json(['message' => 'Insufficient creds to upgrade this stat.'], 422);
        }
        if (($player->tech_points ?? 0) < $tpCost) {
            return response()->json(['message' => 'Insufficient Tech Points to upgrade this stat.'], 422);
        }

        // Wrap deduction + upgrade in a transaction so a failed upgrade (stat already
        // capped, all others at minimum, etc.) rolls back the cred/TP spend.
        try {
            ['rig' => $rig, 'tax' => $tax] = DB::transaction(function () use ($player, $rig, $data, $credCost, $tpCost) {
                // Row-lock so two rapid upgrade requests cannot both pass the afford
                // check above and double-deduct from the same stale balance.
                $locked = Player::where('id', $player->id)->lockForUpdate()->first();
                if (($locked->wallet_creds ?? 0) < $credCost) {
                    throw new \RuntimeException('Insufficient creds to upgrade this stat.');
                }
                if (($locked->tech_points ?? 0) < $tpCost) {
                    throw new \RuntimeException('Insufficient Tech Points to upgrade this stat.');
                }
                $locked->wallet_creds = (int)   $locked->wallet_creds - $credCost;
                $locked->tech_points  = round((float) $locked->tech_points - $tpCost, 2);
                $locked->save();

                return $this->rigService->upgradeStat($rig, $data['stat']);
            });
        } catch (\OverflowException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'stat_upgraded' => $data['stat'],
            'tax_event'     => $tax,
            'rig_id'        => $rig->id,
            'current_ss'    => $rig->current_ss,
            'max_ss'        => $this->rigService->maxSs($rig),
            'stats'         => $this->rigService->effectiveStats($rig, $player),
            'points'        => [
                'spent' => $this->rigService->totalPointsSpent($rig),
                'cap'   => $rig->chassis->total_point_cap,
            ],
            'wallet_creds' => (int)   $player->fresh()->wallet_creds,
            'tech_points'  => (float) $player->fresh()->tech_points,
        ]);
    }

    /**
     * POST /api/rig/chassis-upgrade
     *
     * Upgrades the player's chassis from BlackHat (Tier 1) to a NullTek Series 2.
     *
     * Pre-conditions (all enforced):
     *   - Current chassis is Tier 1
     *   - All upgrade points are invested (chassis maxed at v1.9)
     *   - Player can afford the server-computed cred + TP cost
     *   - chassis_name is one of the three valid NullTek Series 2 chassis
     *
     * On success:
     *   - Deducts cost (always server-computed) from wallet_creds and tech_points
     *   - Swaps chassis_template_id on the PlayerRig
     *   - Resets all invested stat levels to 0
     *   - Recalculates current_ss from new chassis base_os_level
     *   - Returns the full new rig snapshot (same shape as /api/player/me rig block)
     *
     * Request body:
     *   chassis_name string — 'NullTek GX-7 Ghost' | 'NullTek BR-9 Breaker' | 'NullTek VT-3 Vault'
     *
     * Note: cred_cost and tp_cost are intentionally NOT accepted from the client —
     * costs are always derived server-side via RigService::chassisUpgradeCost().
     */
    public function chassisUpgrade(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chassis_name' => 'required|string|in:NullTek GX-7 Ghost,NullTek BR-9 Breaker,NullTek VT-3 Vault',
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $node = \App\Models\Node::find($player->current_node_id);
        if ($node === null || $node->type !== 'cyberdoc') {
            return response()->json(['message' => 'You must be at a CyberDoc terminal.'], 403);
        }

        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig === null) {
            return response()->json(['message' => 'No rig found for this player.'], 404);
        }

        // Must be on Tier 1 to upgrade
        if ($rig->chassis->tier !== 1) {
            return response()->json(['message' => 'Chassis upgrade only available from Tier 1.'], 422);
        }

        // Must have invested all points (chassis fully maxed)
        if ($this->rigService->totalPointsSpent($rig) < $rig->chassis->total_point_cap) {
            return response()->json(['message' => 'Max out your BlackHat before upgrading.'], 422);
        }

        // Cost is always computed server-side — the client never controls the price.
        $cost     = $this->rigService->chassisUpgradeCost($data['chassis_name']);
        $credCost = $cost['creds'];
        $tpCost   = $cost['tp'];

        // Affordability check
        if (($player->wallet_creds ?? 0) < $credCost) {
            return response()->json(['message' => 'Insufficient creds.'], 422);
        }
        if (($player->tech_points ?? 0) < $tpCost) {
            return response()->json(['message' => 'Insufficient Tech Points.'], 422);
        }

        // Resolve new chassis template
        $newChassis = ChassisTemplate::where('name', $data['chassis_name'])->first();
        if ($newChassis === null) {
            return response()->json(['message' => 'Chassis template not found — re-run the seeder.'], 500);
        }

        // Wrap cost deduction + chassis swap in a transaction so a failed rig save
        // cannot permanently orphan the wallet deduction (same pattern as upgrade()).
        DB::transaction(function () use ($player, $rig, $newChassis, $credCost, $tpCost) {
            // Row-lock so a rapid double-submission cannot deduct the chassis cost twice.
            $locked = Player::where('id', $player->id)->lockForUpdate()->first();
            if (($locked->wallet_creds ?? 0) < $credCost) {
                throw new \RuntimeException('Insufficient creds.');
            }
            if (($locked->tech_points ?? 0) < $tpCost) {
                throw new \RuntimeException('Insufficient Tech Points.');
            }
            $locked->wallet_creds = max(0, (int)   $locked->wallet_creds - $credCost);
            $locked->tech_points  = max(0, (float) $locked->tech_points  - $tpCost);
            $locked->save();

            // Swap chassis — reset invested levels, recalculate SS, clear limp
            $rig->chassis_template_id = $newChassis->id;
            $rig->cpu_level           = 0;
            $rig->ram_level           = 0;
            $rig->firewall_level      = 0;
            $rig->storage_level       = 0;
            $rig->os_level            = 0;
            $rig->current_ss          = 100;  // SS is always 100 — chassis does not affect max SS
            $rig->is_limping          = false;
            $rig->current_uplink      = (int) $newChassis->base_uplink;
            $rig->save();
        });

        $rig->load('chassis');  // refresh relationship after chassis swap
        $freshPlayer = $player->fresh();

        return response()->json([
            'rig_id'           => $rig->id,
            'chassis'          => $rig->chassis->name,
            'tier'             => (int) $rig->chassis->tier,
            'current_ss'       => (int) $rig->current_ss,
            'max_ss'           => $this->rigService->maxSs($rig),
            'is_limping'       => false,
            'uplink'           => (int) $rig->chassis->base_uplink,
            'current_uplink'   => (int) $rig->current_uplink,
            'stats'            => $this->rigService->effectiveStats($rig, $player),
            'caps' => [
                'cpu'      => (int) $rig->chassis->cap_cpu,
                'ram'      => (int) $rig->chassis->cap_ram,
                'firewall' => (int) $rig->chassis->cap_firewall,
                'storage'  => (int) $rig->chassis->cap_storage,
                'os'       => (int) $rig->chassis->cap_os,
            ],
            'invested' => [
                'cpu' => 0, 'ram' => 0, 'firewall' => 0, 'storage' => 0, 'os' => 0,
            ],
            'points' => [
                'spent' => 0,
                'cap'   => (int) $rig->chassis->total_point_cap,
            ],
            'peripheral_slots' => (int) $rig->chassis->peripheral_slots,
            'wallet_creds'     => (int)   ($freshPlayer->wallet_creds ?? 0),
            'tech_points'      => (float) ($freshPlayer->tech_points  ?? 0),
        ]);
    }

    /**
     * POST /api/rig/repair
     *
     * Restores the player's rig SS to its maximum value.
     * Optionally repairs all damaged peripherals.
     *
     * Request body:
     *   player_id          string (uuid)
     *   repair_peripherals boolean (optional, default false)
     */
    public function repair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'repair_peripherals' => 'boolean',
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $rig = $this->rigService->getRigForPlayer($player);
        if ($rig === null) {
            return response()->json(['message' => 'No rig found for this player.'], 404);
        }

        $rig = $this->rigService->repair(
            $rig,
            $player,
            (bool) ($data['repair_peripherals'] ?? false),
        );

        return response()->json([
            'rig_id'     => $rig->id,
            'current_ss' => $rig->current_ss,
            'max_ss'     => $this->rigService->maxSs($rig),
        ]);
    }
}
