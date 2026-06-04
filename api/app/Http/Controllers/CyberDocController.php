<?php

namespace App\Http\Controllers;

use App\Models\Node;
use App\Models\Player;
use App\Services\CyberDocService;
use App\Services\RigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CyberDocController extends Controller
{
    public function __construct(
        private readonly CyberDocService $cyberDocService,
        private readonly RigService      $rigService,
    ) {}

    /**
     * Assert the player is physically at a CyberDoc node.
     * Returns a 403 JsonResponse if not, or null if the check passes.
     */
    private function assertAtCyberDoc(Player $player): ?JsonResponse
    {
        $node = Node::find($player->current_node_id);
        if ($node === null || $node->type !== 'cyberdoc') {
            return response()->json(['message' => 'You must be at a CyberDoc terminal.'], 403);
        }
        return null;
    }

    /**
     * POST /api/cyberdoc/visit
     *
     * Called when the player opens the CyberDoc storefront.
     * Validates that the player is physically at a CyberDoc node, then
     * resets current_uplink to chassis base and returns the new value.
     *
     * Body: {} (empty — identity comes from session)
     */
    public function visit(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        if ($err = $this->assertAtCyberDoc($player)) return $err;

        $rig           = $this->rigService->getRigForPlayer($player);
        $currentUplink = null;
        if ($rig !== null) {
            $currentUplink = $this->rigService->restoreUplinkToFull($rig, $player);
        }

        return response()->json([
            'message'        => 'CyberDoc terminal accessed.',
            'current_uplink' => $currentUplink,
        ]);
    }

    /**
     * POST /api/cyberdoc/bank
     *
     * Banks all pocket_creds into the player's safe wallet.
     * Resets bounty run counters.
     *
     * Body: { player_id }
     */
    public function bank(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        if ($err = $this->assertAtCyberDoc($player)) return $err;

        $cyberdocCanvasId = $request->input('cyberdoc_canvas_id');
        $result = $this->cyberDocService->bankCreds($player, $cyberdocCanvasId);
        $fresh  = $player->fresh();

        return response()->json([
            'message'       => 'Creds banked.',
            'pocket_banked' => $result['pocket_banked'],
            'wallet_creds'  => (int) ($fresh->wallet_creds ?? 0),
            'player' => [
                'bounty_level'      => $fresh->bounty_level,
                'bounty_multiplier' => $fresh->bounty_multiplier,
                'is_open_season'    => $fresh->is_open_season,
                'pocket_creds'      => $fresh->pocket_creds,
                'is_limping'        => $fresh->is_limping,
            ],
        ]);
    }

    /**
     * POST /api/cyberdoc/repair
     *
     * Repair the player's SS to maximum.
     * Cost is always computed server-side — the client never controls the price.
     *
     * Body: { player_id }
     */
    public function repair(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        if ($err = $this->assertAtCyberDoc($player)) return $err;

        try {
            $cost = $this->cyberDocService->repairCost($player);
            $this->cyberDocService->repairSS($player, $cost);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $rig   = $this->rigService->getRigForPlayer($player->fresh());
        $fresh = $player->fresh();

        return response()->json([
            'message'      => 'SS restored to maximum.',
            'current_ss'   => $rig?->current_ss,
            'max_ss'       => $rig ? $this->rigService->maxSs($rig) : 100,
            'is_limping'   => $fresh->is_limping,
            'wallet_creds' => (int) $fresh->wallet_creds,
        ]);
    }

    /**
     * POST /api/cyberdoc/install
     *
     * Install a hardware encrypt from the player's inventory.
     *
     * Body: { player_id, encrypt_id }
     */
    public function install(Request $request): JsonResponse
    {
        $data = $request->validate([
            'encrypt_id' => 'required|uuid',
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        if ($err = $this->assertAtCyberDoc($player)) return $err;

        try {
            $playerPeripheral = $this->cyberDocService->installEncrypt($player, $data['encrypt_id']);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $rig = $this->rigService->getRigForPlayer($player);

        return response()->json([
            'message'         => 'Hardware encrypt installed.',
            'peripheral_id'   => $playerPeripheral->peripheral_id,
            'effective_stats' => $rig ? $this->rigService->effectiveStats($rig, $player) : null,
        ]);
    }

    /**
     * POST /api/cyberdoc/loadout
     *
     * Set the player's active command loadout.
     * Can be called from anywhere — CyberDoc presence is only required to purchase commands.
     *
     * Body: { player_id, active_command_ids: string[] }
     */
    public function loadout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'active_command_ids'   => 'required|array',
            'active_command_ids.*' => 'uuid',
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        try {
            $this->cyberDocService->setLoadout($player, $data['active_command_ids']);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $activeCommands = $player->commands()->wherePivot('is_active', true)->get(['id', 'name', 'type']);

        return response()->json([
            'message'         => 'Loadout updated.',
            'active_commands' => $activeCommands,
        ]);
    }

    /**
     * POST /api/cyberdoc/upgrade-command
     *
     * Upgrade an owned command by one level (costs tech_points).
     *
     * Body: { player_id, command_id }
     */
    public function upgradeCommand(Request $request): JsonResponse
    {
        $data = $request->validate([
            'command_id' => 'required|uuid',
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        if ($err = $this->assertAtCyberDoc($player)) return $err;

        try {
            $newLevel = $this->cyberDocService->upgradeCommand($player, $data['command_id']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'     => 'Command upgraded.',
            'command_id'  => $data['command_id'],
            'new_level'   => $newLevel,
            'tech_points' => (float) $player->fresh()->tech_points,
        ]);
    }

    /**
     * POST /api/cyberdoc/reallocate
     *
     * Move 1 upgrade level from one stat to another.
     *
     * Body: { player_id, from_stat, to_stat }
     */
    public function reallocate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_stat' => 'required|string|in:os,ram,cpu,storage,firewall',
            'to_stat'   => 'required|string|in:os,ram,cpu,storage,firewall',
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        if ($err = $this->assertAtCyberDoc($player)) return $err;

        try {
            $result = $this->cyberDocService->reallocateStats($player, $data['from_stat'], $data['to_stat']);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $rig = $result['rig'];

        return response()->json([
            'message'         => "Moved 1 level from {$data['from_stat']} to {$data['to_stat']}.",
            'from'            => $result['from'],
            'to'              => $result['to'],
            'effective_stats' => $this->rigService->effectiveStats($rig, $player),
            // Commands deactivated because the reallocation lowered RAM or CPU.
            // Client should use these to update the loadout display without a round-trip.
            'deactivated_commands' => [
                'ram_cap' => $result['deactivated_ram'],
                'cpu_cap' => $result['deactivated_cpu'],
            ],
        ]);
    }
}
