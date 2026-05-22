<?php

namespace App\Http\Controllers;

use App\Models\Command;
use App\Models\Node;
use App\Models\Player;
use App\Services\RigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PlayerController extends Controller
{
    public function __construct(private readonly RigService $rigService) {}

    /**
     * GET /api/player/me
     *
     * Full player + rig snapshot for the authenticated session.
     * Called by useAuth.js on boot to hydrate the client game state.
     */
    public function me(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();

        if ($player === null) {
            return response()->json(['message' => 'No player found for this account.'], 404);
        }

        $rig = $this->rigService->getRigForPlayer($player);

        // Resolve canvas_id from the player's last persisted node so the client
        // can restore their exact map position on reload without re-spawning.
        $currentNodeCanvasId = $player->current_node_id
            ? \App\Models\Node::find($player->current_node_id)?->canvas_id
            : null;

        return response()->json([
            'player' => [
                'id'                      => $player->id,
                'handle'                  => $player->handle,
                'current_node_id'         => $player->current_node_id,
                'current_node_canvas_id'  => $currentNodeCanvasId,
                'current_district'        => $player->current_district,
                // Bounty
                'bounty_level'          => (int)  $player->bounty_level,
                'bounty_multiplier'     => (float) $player->bounty_multiplier,
                'is_open_season'        => (bool)  $player->is_open_season,
                // Run stats
                'nodes_hacked_this_run' => (int)  $player->nodes_hacked_this_run,
                'pvp_wins_this_run'     => (int)  $player->pvp_wins_this_run,
                // Status
                'is_limping'            => (bool)  $player->is_limping,
                // Economy
                'wallet_creds'          => (int)  ($player->wallet_creds  ?? 0),
                'pocket_creds'          => (int)  ($player->pocket_creds  ?? 0),
                'tech_points'           => (float) ($player->tech_points   ?? 0),
                // Active command effects (move-countdown, restored on reload)
                'active_effects'        => $player->active_effects ?? (object) [],
            ],
            'rig' => $rig ? [
                'rig_id'      => $rig->id,
                'chassis'     => $rig->chassis->name,
                'tier'        => (int) $rig->chassis->tier,
                // System stability
                'current_ss'  => (int) $rig->current_ss,
                'max_ss'      => $this->rigService->maxSs($rig),
                'is_limping'  => (bool) $rig->is_limping,
                // Chassis-locked uplink (max pool for this chassis)
                'uplink'         => (int) ($rig->chassis->base_uplink ?? 3),
                // Persisted remaining uplink for the current run.
                // null means the run hasn't started yet — client treats it as full pool.
                'current_uplink' => $rig->current_uplink !== null
                    ? (int) $rig->current_uplink
                    : (int) ($rig->chassis->base_uplink ?? 3),
                // Effective stats (each entry: base, level, peripheral_boost, effective)
                'stats'       => $this->rigService->effectiveStats($rig, $player),
                // Stat caps from chassis
                'caps' => [
                    'cpu'      => (int) $rig->chassis->cap_cpu,
                    'ram'      => (int) $rig->chassis->cap_ram,
                    'firewall' => (int) $rig->chassis->cap_firewall,
                    'storage'  => (int) $rig->chassis->cap_storage,
                    'os'       => (int) $rig->chassis->cap_os,
                ],
                // Invested point levels (0 = untouched)
                'invested' => [
                    'cpu'      => (int) $rig->cpu_level,
                    'ram'      => (int) $rig->ram_level,
                    'firewall' => (int) $rig->firewall_level,
                    'storage'  => (int) $rig->storage_level,
                    'os'       => (int) $rig->os_level,
                ],
                // Point budget
                'points' => [
                    'spent' => $this->rigService->totalPointsSpent($rig),
                    'cap'   => (int) $rig->chassis->total_point_cap,
                ],
                'peripheral_slots' => (int) ($rig->chassis->peripheral_slots ?? 0),
            ] : null,
        ]);
    }

    /**
     * GET /api/player/commands
     *
     * Returns the full command catalog with ownership + loadout state
     * for the authenticated player.
     *
     * Each command includes:
     *   owned    — true if the player has purchased it
     *   equipped — true if it is in the active loadout (is_active on pivot)
     *   cooldown — always false on load (client manages cooldown in-session)
     */
    public function commands(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();

        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        // Index owned commands by UUID for O(1) lookup
        $owned = $player->commands()
            ->withPivot('is_active', 'level')
            ->get()
            ->keyBy('id');

        $catalog = \App\Models\Command::orderBy('tier')->orderBy('name')->get();

        $commands = $catalog->map(function ($cmd) use ($owned) {
            $playerCmd = $owned->get($cmd->id);
            return [
                'id'          => $cmd->id,
                'name'        => $cmd->name,
                'tier'        => $cmd->tier,
                'type'        => $cmd->type,
                'description' => $cmd->description,
                'price'       => [
                    'creds'      => $cmd->price_creds,
                    'techPoints' => $cmd->price_tp,
                ],
                'upgrade' => [
                    'maxLevel' => (int) $cmd->max_level,
                    'costTp'   => (int) $cmd->upgrade_cost_tp,
                ],
                'targetType'  => $cmd->target_type,
                'duration'    => $cmd->duration,   // array|null via cast
                'mapEffect'   => $cmd->map_effect,
                'hackEffect'  => $cmd->hack_effect,
                // Player-specific state
                'owned'       => $playerCmd !== null,
                'equipped'    => $playerCmd?->pivot->is_active ?? false,
                'level'       => $playerCmd ? (int) $playerCmd->pivot->level : 1,
                'cooldown'    => false,   // always reset on load; client manages in-session
            ];
        });

        return response()->json(['commands' => $commands]);
    }

    /**
     * GET /api/player/inventory
     *
     * Returns the player's consumable and peripheral inventory.
     * Peripherals live on the PlayerPeripheral model.
     * Consumables have no table yet — returns empty until that system is built.
     */
    public function inventory(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();

        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        // Peripherals — installed hardware
        $peripherals = $player->playerPeripherals()
            ->with('peripheral')
            ->get()
            ->map(fn ($pp) => [
                'id'           => $pp->id,
                'name'         => $pp->peripheral->name,
                'category'     => 'peripheral',
                'stat_boosted' => $pp->peripheral->stat_boosted,
                'boost_amount' => $pp->peripheral->boost_amount,
                'is_installed' => (bool) $pp->is_installed,
                'is_damaged'   => (bool) $pp->is_damaged,
            ]);

        return response()->json([
            'consumables' => [],        // no table yet
            'peripherals' => $peripherals,
        ]);
    }

    /**
     * POST /api/player/position
     *
     * Persists the player's current map position (node + district).
     * Called on every move so node presence detection works.
     *
     * Security guards:
     *   1. Destination node must exist in the DB.
     *   2. Destination must be adjacent to the player's current node
     *      (or a spawn node when the player has no current position yet).
     *   3. Player must have at least 1 uplink remaining (not 0).
     */
    public function position(Request $request): JsonResponse
    {
        $data = $request->validate([
            'canvas_node_id' => ['required', 'string'],
            'district'       => ['nullable', 'string'],
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        // ── 1. Destination must be a real node ────────────────────────────────
        $node = Node::where('canvas_id', $data['canvas_node_id'])->first();
        if ($node === null) {
            return response()->json(['message' => 'Unknown node.'], 422);
        }

        // ── 2. Adjacency check ────────────────────────────────────────────────
        if ($player->current_node_id === null) {
            // First move of a new account or post-CF respawn before server writes
            // current_node_id — only allow landing on a designated spawn node.
            if (!$node->is_spawn) {
                return response()->json(['message' => 'First move must be to a spawn node.'], 422);
            }
        } else {
            // All subsequent moves: destination must be a direct neighbour.
            $isAdjacent = Node::find($player->current_node_id)
                ?->adjacentNodes()
                ->where('nodes.id', $node->id)
                ->exists();

            if (!$isAdjacent) {
                return response()->json(['message' => 'Destination node is not adjacent to your current position.'], 422);
            }
        }

        // ── 3. Uplink check ───────────────────────────────────────────────────
        $rig = $player->rig()->with('chassis')->first();
        if ($rig !== null) {
            $baseUplink  = (int) ($rig->chassis->base_uplink ?? 3);
            $curUplink   = $rig->current_uplink ?? $baseUplink;
            if ($curUplink <= 0) {
                return response()->json(['message' => 'No uplink remaining — bank your run at a Street Doc first.'], 422);
            }
        }

        $player->current_node_id  = $node->id;
        $player->current_district = $data['district'] ?? $player->current_district;
        $player->last_seen_at     = Carbon::now();

        // Decrement every move-counted command effect by 1, prune expired ones.
        // Ghost Protocol, Dark Mode, Firewall Patch, Blackout, etc. all use this.
        $effects = $player->active_effects ?? [];
        foreach ($effects as $slug => $movesLeft) {
            $effects[$slug] = max(0, $movesLeft - 1);
        }
        $effects = array_filter($effects, fn ($m) => $m > 0);
        $player->active_effects = !empty($effects) ? $effects : null;

        // Decrement post-combat challenge immunity window each move (floor 0).
        if (($player->post_combat_silent_moves ?? 0) > 0) {
            $player->post_combat_silent_moves = max(0, $player->post_combat_silent_moves - 1);
        }

        $player->save();

        // Decrement persisted uplink by 1 each move (floor 0).
        // $rig is already loaded from the uplink guard above.
        $remainingUplink = null;
        if ($rig !== null) {
            $baseUplink          = (int) ($rig->chassis->base_uplink ?? 3);
            $current             = $rig->current_uplink ?? $baseUplink;
            $rig->current_uplink = max(0, $current - 1);
            $rig->save();
            $remainingUplink = $rig->current_uplink;
        }

        return response()->json([
            'ok'               => true,
            'active_effects'   => $player->active_effects ?? (object) [],
            'remaining_uplink' => $remainingUplink,
        ]);
    }

    /**
     * POST /api/player/heartbeat
     *
     * Lightweight presence ping sent by the client every 45 seconds.
     * Also fires via sendBeacon on beforeunload so tab/browser closes
     * are handled immediately rather than waiting for the timeout window.
     *
     * NodeController::players() filters out players whose last_seen_at
     * is older than 2 minutes, removing ghosts from the world automatically.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();

        if ($player === null) {
            return response()->json(['ok' => false], 404);
        }

        $player->last_seen_at = Carbon::now();
        $player->save();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/player/activate-command
     *
     * Called by the client when a player fires a self-targeted command.
     * Writes the command's move-duration into active_effects so the server
     * can enforce its effects across requests (trace suppression, etc.).
     *
     * Body:
     *   command_id  string  UUID of the command being activated
     *
     * Effects are decremented by one per position() call (one per move).
     * The client mirrors this countdown locally for UI display.
     *
     * Returns:
     *   slug          string  snake_case command name key
     *   moves_left    int     starting move count
     *   active_effects object updated full effects map
     */
    public function activateCommand(Request $request): JsonResponse
    {
        $data = $request->validate([
            'command_id' => ['required', 'uuid'],
        ]);

        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        // Verify the player owns this command and has it equipped
        $cmd = $player->commands()
            ->withPivot('is_active')
            ->where('commands.id', $data['command_id'])
            ->first();

        if ($cmd === null) {
            return response()->json(['message' => 'Command not owned by this player.'], 422);
        }
        if (! $cmd->pivot->is_active) {
            return response()->json(['message' => 'Command is not equipped in loadout.'], 422);
        }

        // Derive the slug from the command name ("Ghost Protocol" → "ghost_protocol")
        $slug      = Str::snake($cmd->name);
        $duration  = $cmd->duration ?? [];
        $movesLeft = (int) ($duration['moves'] ?? 0);

        if ($movesLeft <= 0) {
            // Instant or time-only commands don't need server-side move tracking
            return response()->json([
                'slug'           => $slug,
                'moves_left'     => 0,
                'active_effects' => $player->active_effects ?? (object) [],
            ]);
        }

        $effects         = $player->active_effects ?? [];
        $effects[$slug]  = $movesLeft;
        $player->active_effects = $effects;
        $player->save();

        return response()->json([
            'slug'           => $slug,
            'moves_left'     => $movesLeft,
            'active_effects' => $effects,
        ]);
    }

    /**
     * GET /api/player/{player_id}/status
     *
     * Full player + rig state, primarily for the Kotlin engine (Bearer token).
     *
     * Authorization:
     *   - Bearer token (engine): may query any player.
     *   - Session cookie (SPA):  may only query their own player record.
     *     Querying another player's UUID returns 403 to prevent free scouting.
     */
    public function status(Request $request, string $playerId): JsonResponse
    {
        $player = Player::find($playerId);

        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        // Session-authenticated SPA requests may only read their own player.
        // The Kotlin engine authenticates via Bearer token (accessToken guard),
        // so currentAccessToken() is non-null for engine calls — those pass through.
        if ($request->user()->currentAccessToken() === null) {
            $myPlayerId = Player::where('user_id', $request->user()->id)->value('id');
            if ($myPlayerId !== $player->id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        $rig = $this->rigService->getRigForPlayer($player);

        return response()->json([
            'player' => [
                'id'                       => $player->id,
                'handle'                   => $player->handle,
                'current_node_id'          => $player->current_node_id,
                'current_district'         => $player->current_district,
                'bounty_level'             => $player->bounty_level,
                'is_open_season'           => $player->is_open_season,
                'is_limping'               => $player->is_limping,
                'post_combat_silent_moves' => $player->post_combat_silent_moves,
            ],
            'rig' => $rig ? [
                'rig_id'     => $rig->id,
                'chassis'    => $rig->chassis->name,
                'is_limping' => $rig->is_limping,
                'current_ss' => $rig->current_ss,
                'max_ss'     => $this->rigService->maxSs($rig),
                'stats'      => $this->rigService->effectiveStats($rig, $player),
                'points'     => [
                    'spent' => $this->rigService->totalPointsSpent($rig),
                    'cap'   => $rig->chassis->total_point_cap,
                ],
            ] : null,
        ]);
    }
}
