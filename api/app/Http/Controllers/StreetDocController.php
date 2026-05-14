<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\RigService;
use App\Services\StreetDocService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreetDocController extends Controller
{
    public function __construct(
        private readonly StreetDocService $streetDocService,
        private readonly RigService       $rigService,
    ) {}

    /**
     * POST /api/street-doc/visit
     *
     * Registers the player's arrival at a Street Doc.
     * Triggers extract if they have an active bounty run.
     *
     * Body: { player_id, street_doc_id }
     */
    public function visit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id'    => 'required|uuid',
            'street_doc_id' => 'required|uuid',
        ]);

        $player = Player::find($data['player_id']);
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        try {
            $doc = $this->streetDocService->visitStreetDoc($player, $data['street_doc_id']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'           => "Arrived at {$doc->name}.",
            'street_doc'        => ['id' => $doc->id, 'name' => $doc->name],
            'last_street_doc_id' => $player->last_street_doc_id,
            'bounty_level'      => $player->fresh()->bounty_level,
        ]);
    }

    /**
     * POST /api/street-doc/repair
     *
     * Repair the player's SS to maximum.
     *
     * Body: { player_id, cred_cost? }
     */
    public function repair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id' => 'required|uuid',
            'cred_cost' => 'sometimes|integer|min:0',
        ]);

        $player = Player::find($data['player_id']);
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        try {
            $cost = $data['cred_cost'] ?? $this->streetDocService->repairCost($player);
            $this->streetDocService->repairSS($player, $cost);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $rig = $this->rigService->getRigForPlayer($player->fresh());

        return response()->json([
            'message'    => 'SS restored to maximum.',
            'current_ss' => $rig?->current_ss,
            'max_ss'     => $rig ? $rig->os_level * 10 : null,
            'is_limping' => $player->fresh()->is_limping,
        ]);
    }

    /**
     * POST /api/street-doc/install
     *
     * Install a hardware encrypt from the player's inventory.
     *
     * Body: { player_id, encrypt_id }
     */
    public function install(Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id'  => 'required|uuid',
            'encrypt_id' => 'required|uuid',
        ]);

        $player = Player::find($data['player_id']);
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        try {
            $playerPeripheral = $this->streetDocService->installEncrypt($player, $data['encrypt_id']);
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
     * POST /api/street-doc/loadout
     *
     * Set the player's active command loadout.
     *
     * Body: { player_id, active_command_ids: string[] }
     */
    public function loadout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id'          => 'required|uuid',
            'active_command_ids' => 'required|array',
            'active_command_ids.*' => 'uuid',
        ]);

        $player = Player::find($data['player_id']);
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        try {
            $this->streetDocService->setLoadout($player, $data['active_command_ids']);
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
     * POST /api/street-doc/reallocate
     *
     * Move 1 upgrade level from one stat to another.
     *
     * Body: { player_id, from_stat, to_stat }
     */
    public function reallocate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id' => 'required|uuid',
            'from_stat' => 'required|string|in:os,ram,cpu,storage,firewall',
            'to_stat'   => 'required|string|in:os,ram,cpu,storage,firewall',
        ]);

        $player = Player::find($data['player_id']);
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        try {
            $result = $this->streetDocService->reallocateStats($player, $data['from_stat'], $data['to_stat']);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $rig = $result['rig'];

        return response()->json([
            'message'         => "Moved 1 level from {$data['from_stat']} to {$data['to_stat']}.",
            'from'            => $result['from'],
            'to'              => $result['to'],
            'effective_stats' => $this->rigService->effectiveStats($rig, $player),
        ]);
    }
}
