<?php

namespace App\Http\Controllers;

use App\Events\PacketHijackCommandResult;
use App\Events\PacketHijackMatchComplete;
use App\Events\PacketHijackPhaseTransition;
use App\Models\PacketHijackMatch;
use App\Models\Player;
use App\Models\PlayerCommand;
use App\Services\BountyService;
use App\Services\PacketHijackService;
use App\Services\RigService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Handles Packet Hijack match commands and state.
 *
 * Responsibilities (only):
 *   - Validate input and player participation.
 *   - Delegate all game logic to PacketHijackService.
 *   - Persist match state changes.
 *   - Dispatch broadcast events.
 *   - Apply PvP economy consequences on match completion.
 *
 * No game math lives here. No business logic lives here.
 */
class PacketHijackController extends Controller
{
    public function __construct(
        private readonly PacketHijackService $phService,
        private readonly RigService          $rigService,
        private readonly BountyService       $bountyService,
    ) {}

    // =========================================================================
    // POST /api/packet-hijack/{match}/command
    // =========================================================================

    /**
     * Execute one terminal command OR one rig command for the authenticated player.
     *
     * Body (exactly one of):
     *   input        string  Raw terminal string, e.g. "scan port 80"
     *   rig_command  string  Slug of an equipped hack command, e.g. "overclock"
     *
     * The response is intentionally thin — the real output arrives via the
     * PacketHijackCommandResult broadcast so both timing and sequencing are
     * driven by the WebSocket layer.
     */
    public function command(Request $request, string $matchId): JsonResponse
    {
        $data = $request->validate([
            'input'       => ['nullable', 'string', 'max:200', 'required_without:rig_command'],
            'rig_command' => ['nullable', 'string', 'max:100', 'required_without:input'],
        ]);

        $me = Player::where('user_id', $request->user()->id)->first();
        if ($me === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        // ── Load match with pessimistic lock inside a transaction ─────────────
        return DB::transaction(function () use ($me, $matchId, $data) {

            /** @var PacketHijackMatch|null $match */
            $match = PacketHijackMatch::lockForUpdate()->find($matchId);

            if ($match === null) {
                return response()->json(['message' => 'Match not found.'], 404);
            }

            $role = $match->roleOf($me->id);
            if ($role === null) {
                return response()->json(['message' => 'Not your match.'], 403);
            }

            if ($match->status === 'complete') {
                return response()->json(['message' => 'Match already complete.'], 409);
            }

            // ── Honeypot lock check ───────────────────────────────────────────
            if ($match->isLocked($role)) {
                $lockKey   = "{$role}_locked_until";
                $remaining = (int) ceil(now()->diffInSeconds($match->$lockKey, false) * -1);
                return response()->json([
                    'locked'     => true,
                    'lock_until' => $match->$lockKey->toIso8601String(),
                    'remaining'  => max(0, $remaining),
                ], 429);
            }

            // ── Rig command path (Phase 7) ────────────────────────────────────
            if (!empty($data['rig_command'])) {
                return $this->handleRigCommand($match, $role, $me, $data['rig_command']);
            }

            // ── Terminal command path ─────────────────────────────────────────
            $parsed = $this->phService->parseCommand($data['input']);

            if (!$parsed['valid']) {
                PacketHijackCommandResult::dispatch(
                    matchId:      $match->id,
                    playerId:     $me->id,
                    command:      $data['input'],
                    outputLines:  ["[ERROR]: {$parsed['error']}"],
                );
                return response()->json(['ok' => true]);
            }

            $command      = $parsed['command'];
            $args         = $parsed['args'];
            $currentPhase = $match->phaseOf($role);

            // ── Phase guard ───────────────────────────────────────────────────
            $phase1Commands = ['netstat', 'ping', 'traceroute', 'arp', 'whois', 'sniff', 'flush', 'inject'];
            $phase2Commands = ['probe', 'exploit', 'decode', 'breach'];

            if ($currentPhase === 1 && in_array($command, $phase2Commands, true)) {
                PacketHijackCommandResult::dispatch(
                    matchId:     $match->id,
                    playerId:    $me->id,
                    command:     $data['input'],
                    outputLines: ['[ERROR]: PHASE 2 COMMANDS UNAVAILABLE — COMPLETE RECON FIRST'],
                );
                return response()->json(['ok' => true]);
            }

            if ($currentPhase === 2 && in_array($command, $phase1Commands, true)) {
                PacketHijackCommandResult::dispatch(
                    matchId:     $match->id,
                    playerId:    $me->id,
                    command:     $data['input'],
                    outputLines: ['[ERROR]: RECON PHASE COMPLETE — TARGET ALREADY COMPROMISED'],
                );
                return response()->json(['ok' => true]);
            }

            // ── Dispatch to the appropriate command handler ───────────────────
            return match ($command) {
                'netstat'    => $this->handleNetstat($match, $role, $me, $data['input']),
                'ping'       => $this->handlePing($match, $role, $me, $data['input'], $args),
                'traceroute' => $this->handleTraceroute($match, $role, $me, $data['input'], $args),
                'arp'        => $this->handleArp($match, $role, $me, $data['input']),
                'whois'      => $this->handleWhois($match, $role, $me, $data['input'], $args),
                'sniff'      => $this->handleSniff($match, $role, $me, $data['input']),
                'flush'      => $this->handleFlush($match, $role, $me, $data['input'], $args),
                'inject'     => $this->handleInject($match, $role, $me, $data['input'], $args),
                'probe'      => $this->handleProbe($match, $role, $me, $data['input'], $args),
                'exploit'    => $this->handleExploit($match, $role, $me, $data['input'], $args),
                'decode'     => $this->handleDecode($match, $role, $me, $data['input'], $args),
                'breach'     => $this->handleBreach($match, $role, $me, $data['input'], $args),
                default      => response()->json(['message' => 'Unhandled command.'], 500),
            };
        });
    }

    // =========================================================================
    // GET /api/packet-hijack/{match}/state
    // =========================================================================

    /**
     * Fallback state poll — returns current match state for the authenticated
     * player. Used if the WebSocket connection drops mid-match.
     */
    public function state(Request $request, string $matchId): JsonResponse
    {
        $match = PacketHijackMatch::find($matchId);
        if ($match === null) {
            return response()->json(['message' => 'Match not found.'], 404);
        }

        $me   = Player::where('user_id', $request->user()->id)->first();
        $role = $match->roleOf($me?->id ?? '');

        if ($role === null) {
            return response()->json(['message' => 'Not your match.'], 403);
        }

        return response()->json([
            'match_id'  => $match->id,
            'status'    => $match->status,
            'role'      => $role,
            'phase'     => $match->phaseOf($role),
            'locked'    => $match->isLocked($role),
            'ports'     => $match->phaseOf($role) === 2 ? $match->portsFor($role) : null,
            'target_ip' => $match->phaseOf($role) === 2 ? $match->targetIpFor($role) : null,
        ]);
    }

    // =========================================================================
    // Private — terminal command handlers
    // =========================================================================

    private function handleNetstat(PacketHijackMatch $match, string $role, Player $me, string $raw): JsonResponse
    {
        $suspects = $match->suspectsFor($role);
        $result   = $this->phService->commandNetstat($suspects);

        $lines = [
            '[SCANNING NODE FOR ACTIVE CONNECTIONS...]',
            '[SUCCESS]: ' . count($result) . ' ACTIVE CONNECTIONS DETECTED ON THIS NODE',
            '[CASE FILE POPULATED — INVESTIGATE SUSPECTS TO IDENTIFY TARGET]',
        ];

        // Only send IP + flushed to the client on netstat — all other attributes
        // are revealed progressively through ping, traceroute, arp, whois.
        $bare = array_map(fn($s) => ['ip' => $s['ip'], 'flushed' => $s['flushed']], $match->suspectsFor($role));

        PacketHijackCommandResult::dispatch(
            matchId:         $match->id,
            playerId:        $me->id,
            command:         $raw,
            outputLines:     $lines,
            updatedSuspects: $bare,
        );

        return response()->json(['ok' => true]);
    }

    private function handlePing(PacketHijackMatch $match, string $role, Player $me, string $raw, array $args): JsonResponse
    {
        $ip       = $args[0] ?? '';
        $suspects = $match->suspectsFor($role);
        $result   = $this->phService->commandPing($suspects, $ip);

        if (!$result['found']) {
            $lines = ["[ERROR]: {$result['error']}"];
        } elseif ($result['latency_status'] === 'TIMEOUT') {
            $lines = [
                "[PING]: PROBING {$ip}...",
                "[RESULT]: REQUEST TIMEOUT — HOST UNREACHABLE",
            ];
        } else {
            $lines = [
                "[PING]: PROBING {$ip}...",
                "[RESULT]: RESPONSE {$result['latency_ms']}ms — {$result['latency_status']}",
            ];
        }

        // Update the suspect's revealed latency in the match record
        $key = "{$role}_suspects";
        $updated = $suspects;
        foreach ($updated as $i => $s) {
            if ($s['ip'] === $ip) {
                $updated[$i]['_ping_revealed'] = true;
                break;
            }
        }
        $match->$key = $updated;
        $match->save();

        PacketHijackCommandResult::dispatch(
            matchId:         $match->id,
            playerId:        $me->id,
            command:         $raw,
            outputLines:     $lines,
            suspectUpdate:   ['ip' => $ip, 'latency_ms' => $result['latency_ms'] ?? null, 'latency_status' => $result['latency_status'] ?? 'TIMEOUT'],
        );

        return response()->json(['ok' => true]);
    }

    private function handleTraceroute(PacketHijackMatch $match, string $role, Player $me, string $raw, array $args): JsonResponse
    {
        $ip       = $args[0] ?? '';
        $suspects = $match->suspectsFor($role);
        $result   = $this->phService->commandTraceroute($suspects, $ip);

        if (!$result['found']) {
            $lines = ["[ERROR]: {$result['error']}"];
        } else {
            $lines = [
                "[TRACEROUTE]: MAPPING ROUTE TO {$ip}...",
                "[RESULT]: {$result['hops']} HOP" . ($result['hops'] === 1 ? '' : 'S') . " — NETWORK RANGE: {$result['network_range']}",
            ];
        }

        PacketHijackCommandResult::dispatch(
            matchId:       $match->id,
            playerId:      $me->id,
            command:       $raw,
            outputLines:   $lines,
            suspectUpdate: $result['found'] ? ['ip' => $ip, 'hops' => $result['hops'], 'network_range' => $result['network_range']] : null,
        );

        return response()->json(['ok' => true]);
    }

    private function handleArp(PacketHijackMatch $match, string $role, Player $me, string $raw): JsonResponse
    {
        $suspects = $match->suspectsFor($role);
        $result   = $this->phService->commandArpScan($suspects);

        $lines = ['[ARP SCAN]: QUERYING NODE ADDRESS RESOLUTION TABLE...'];

        foreach ($result as $entry) {
            $age    = $entry['last_seen_seconds'];
            $label  = $age <= 5 ? 'JUST NOW' : ($age < 60 ? "{$age}s AGO" : floor($age / 60) . 'm AGO');
            $lines[] = "  {$entry['ip']}  —  LAST ACTIVE: {$label}";
        }

        PacketHijackCommandResult::dispatch(
            matchId:       $match->id,
            playerId:      $me->id,
            command:       $raw,
            outputLines:   $lines,
            arpScanResult: $result,
        );

        return response()->json(['ok' => true]);
    }

    private function handleWhois(PacketHijackMatch $match, string $role, Player $me, string $raw, array $args): JsonResponse
    {
        $ip       = $args[0] ?? '';
        $suspects = $match->suspectsFor($role);
        $result   = $this->phService->commandWhois($suspects, $ip);

        if (!$result['found']) {
            $lines = ["[ERROR]: {$result['error']}"];
        } elseif ($result['redacted']) {
            $lines = [
                "[WHOIS]: QUERYING REGISTRY FOR {$ip}...",
                "[RESULT]: DATA REDACTED — OPERATOR HAS MASKED THEIR SIGNATURE",
            ];
        } else {
            $lines = [
                "[WHOIS]: QUERYING REGISTRY FOR {$ip}...",
                "[RESULT]: NODE CLASS: {$result['class']}",
            ];
        }

        PacketHijackCommandResult::dispatch(
            matchId:       $match->id,
            playerId:      $me->id,
            command:       $raw,
            outputLines:   $lines,
            suspectUpdate: $result['found'] ? ['ip' => $ip, 'whois_class' => $result['class'], 'whois_redacted' => $result['redacted']] : null,
        );

        return response()->json(['ok' => true]);
    }

    private function handleSniff(PacketHijackMatch $match, string $role, Player $me, string $raw): JsonResponse
    {
        $targetIp = $match->targetIpFor($role);
        $clue     = $this->phService->commandSniff($targetIp);

        $lines = [
            '[SNIFFING LIVE PACKET STREAM...]',
            '[CAPTURED]: PACKET_ID #' . str_pad((string) random_int(100, 9999), 4, '0', STR_PAD_LEFT) . ' // INBOUND — ACTIVE SESSION',
            "[OCTET FRAGMENT ISOLATED]: [{$clue}]",
            '[CROSS-REFERENCE THIS AGAINST YOUR CASE FILE]',
        ];

        PacketHijackCommandResult::dispatch(
            matchId:     $match->id,
            playerId:    $me->id,
            command:     $raw,
            outputLines: $lines,
            octetClue:   $clue,
        );

        return response()->json(['ok' => true]);
    }

    private function handleFlush(PacketHijackMatch $match, string $role, Player $me, string $raw, array $args): JsonResponse
    {
        $ip       = $args[0] ?? '';
        $suspects = $match->suspectsFor($role);
        $result   = $this->phService->commandFlush($suspects, $ip);

        if (!$result['success']) {
            $lines = ["[ERROR]: {$result['error']}"];
        } else {
            $key        = "{$role}_suspects";
            $match->$key = $result['suspects'];
            $match->save();

            $lines = ["[FLUSH]: {$ip} PURGED FROM ACTIVE TRACE BUFFER"];
        }

        PacketHijackCommandResult::dispatch(
            matchId:      $match->id,
            playerId:     $me->id,
            command:      $raw,
            outputLines:  $lines,
            flushedIp:    $result['success'] ? $ip : null,
        );

        return response()->json(['ok' => true]);
    }

    private function handleInject(PacketHijackMatch $match, string $role, Player $me, string $raw, array $args): JsonResponse
    {
        $attempt  = $args[0] ?? '';
        $targetIp = $match->targetIpFor($role);
        $suspects = $match->suspectsFor($role);
        $result   = $this->phService->commandInject($targetIp, $attempt, $suspects);

        if ($result['success']) {
            // ── Phase transition ──────────────────────────────────────────────
            $phaseKey        = "{$role}_phase";
            $match->$phaseKey = 2;
            $match->status   = 'phase2';
            $match->save();

            // Notify the advancing player: send port topology
            $ports = $match->portsFor($role);
            PacketHijackPhaseTransition::dispatch(
                matchId:   $match->id,
                playerId:  $me->id,
                alertOnly: false,
                ports:     $ports,
                targetIp:  $targetIp,
            );

            // Notify the opponent: critical alert
            $opponentId = $match->opponentIdOf($me->id);
            PacketHijackPhaseTransition::dispatch(
                matchId:   $match->id,
                playerId:  $opponentId,
                alertOnly: true,
            );

            PacketHijackCommandResult::dispatch(
                matchId:       $match->id,
                playerId:      $me->id,
                command:       $raw,
                outputLines:   [
                    '[VERIFYING NODE GATEWAY ROUTE...]',
                    '[SUCCESS]: TARGET IP SIGNATURE CONFIRMED. TERMINAL COMPROMISED.',
                ],
                phaseAdvanced: true,
            );
        } elseif (isset($result['honeypot'])) {
            // ── Honeypot hit ──────────────────────────────────────────────────
            $lockKey         = "{$role}_locked_until";
            $match->$lockKey = $result['lock_until'];
            $match->save();

            PacketHijackCommandResult::dispatch(
                matchId:     $match->id,
                playerId:    $me->id,
                command:     $raw,
                outputLines: [
                    "[ERROR: INVALID HOSTILE HONEYPOT ENCOUNTERED — INPUT LOCKED FOR 3 SECONDS]",
                ],
                lockUntil: $result['lock_until']->toIso8601String(),
            );
        } else {
            PacketHijackCommandResult::dispatch(
                matchId:     $match->id,
                playerId:    $me->id,
                command:     $raw,
                outputLines: ["[ERROR]: {$attempt} NOT FOUND IN ACTIVE TRACE BUFFER"],
            );
        }

        return response()->json(['ok' => true]);
    }

    private function handleProbe(PacketHijackMatch $match, string $role, Player $me, string $raw, array $args): JsonResponse
    {
        // args: ['port', '<number>']
        $portNumber   = isset($args[1]) ? (int) $args[1] : 0;
        $ports        = $match->portsFor($role);
        $corruptPorts = $match->corruptPortsFor($role);
        $baitPorts    = $match->baitPortsFor($role);

        $result = $this->phService->commandProbePort($ports, $portNumber, $corruptPorts, $baitPorts);

        if ($result['found']) {
            $e      = $result['entry'];
            $flavor = $result['flavor'];
            $label  = $e['shattered']  ? 'SHATTERED'
                : ($e['unlocked']      ? 'UNLOCKED'
                : ($e['bias'] <= 25    ? 'CRITICAL LOW' : 'HIGH'));
            $lines = [
                "[PROBE]: PORT {$e['port']} [{$e['service']}]",
                "[{$flavor[0]}]",
                "[{$flavor[1]}]",
                "[DECRYPTION BIAS]: {$label} [{$e['bias']}%]",
            ];
        } else {
            $lines = ["[ERROR]: {$result['error']}"];
        }

        PacketHijackCommandResult::dispatch(
            matchId:     $match->id,
            playerId:    $me->id,
            command:     $raw,
            outputLines: $lines,
        );

        return response()->json(['ok' => true]);
    }

    private function handleExploit(PacketHijackMatch $match, string $role, Player $me, string $raw, array $args): JsonResponse
    {
        // args: ['port', '<number>']
        $portNumber  = isset($args[1]) ? (int) $args[1] : 0;
        $ports       = $match->portsFor($role);
        $rig         = $this->rigService->getRigForPlayer($me);

        if ($rig === null) {
            return response()->json(['message' => 'Rig not found.'], 422);
        }

        $overclocked = $match->overclockActiveFor($role);
        $baitPorts   = $match->baitPortsFor($role);

        $result = $this->phService->commandExploitPort($ports, $portNumber, $rig, $me, $overclocked, $baitPorts);

        if ($result['success']) {
            $portsKey        = "{$role}_ports";
            $match->$portsKey = $result['ports'];

            // Consume overclock after a successful exploit
            if ($overclocked) {
                $match->{"{$role}_overclock_active"} = false;
            }

            $match->save();

            $lines = [
                "[EXECUTING OVERRIDE ON PORT {$portNumber}...]",
                "[SUCCESS]: PORT {$portNumber} SHATTERED. RE-ROUTING TARGET SYSTEM MEMORY...",
                '[SYSTEM LOG]: DEFENSIVE OVERLAY SHIFTED!',
            ];

            foreach ($result['cascade_log'] as $cascade) {
                $lines[] = "[ALERT]: PORT {$cascade['port']} DECRYPTION BIAS CRIPPLED FROM [{$cascade['old_bias']}%] DOWN TO [{$cascade['new_bias']}%]!";
            }

            if ($result['exfil_unlocked']) {
                $lines[] = '[ALERT]: EXFILTRATION PORT 8080 UNLOCKED. SECURITY ENVELOPE NULLIFIED.';
            }

            PacketHijackCommandResult::dispatch(
                matchId:      $match->id,
                playerId:     $me->id,
                command:      $raw,
                outputLines:  $lines,
                updatedPorts: $result['ports'],
            );

        } elseif (isset($result['baited'])) {
            // ── Bait trap triggered ───────────────────────────────────────────
            $lockSeconds = $result['lock_seconds'];
            $lockKey     = "{$role}_locked_until";
            $newLock     = Carbon::now()->addSeconds($lockSeconds);
            $existing    = $match->$lockKey;

            if ($existing === null || $existing->isPast() || $newLock->gt($existing)) {
                $match->$lockKey = $newLock;
            }

            // Consume the bait entry
            $baitKey        = "{$role}_bait_ports";
            $match->$baitKey = array_values(array_filter(
                $match->$baitKey ?? [],
                fn($b) => (int) $b['port'] !== $portNumber
            ));

            $match->save();

            PacketHijackCommandResult::dispatch(
                matchId:     $match->id,
                playerId:    $me->id,
                command:     $raw,
                outputLines: [
                    "[ALERT]: HONEYPOT TRIGGERED ON PORT {$portNumber} — INPUT LOCKED FOR {$lockSeconds}s",
                ],
                lockUntil: $match->$lockKey->toIso8601String(),
            );

        } else {
            PacketHijackCommandResult::dispatch(
                matchId:     $match->id,
                playerId:    $me->id,
                command:     $raw,
                outputLines: ["[ERROR]: {$result['error']}"],
            );
        }

        return response()->json(['ok' => true]);
    }

    private function handleDecode(PacketHijackMatch $match, string $role, Player $me, string $raw, array $args): JsonResponse
    {
        // args: ['port', '<number>']
        $portNumber = isset($args[1]) ? (int) $args[1] : 0;
        $ports      = $match->portsFor($role);
        $rig        = $this->rigService->getRigForPlayer($me);

        if ($rig === null) {
            return response()->json(['message' => 'Rig not found.'], 422);
        }

        $result = $this->phService->commandDecodePort($ports, $portNumber, $rig, $me);

        if ($result['success']) {
            $portsKey        = "{$role}_ports";
            $match->$portsKey = $result['ports'];
            $match->save();

            $lines = [
                "[DECODE]: TARGETING PORT {$result['port']} [{$result['service']}]...",
                "[PROGRESS]: ENCRYPTION LAYER CRACKED — BIAS REDUCED BY {$result['reduction']}%",
                "[CURRENT BIAS]: {$result['new_bias']}%" . ($result['new_bias'] <= 25 ? ' — EXPLOITABLE' : ' — CONTINUE DECODING OR FIND ANOTHER ENTRY POINT'),
            ];

            PacketHijackCommandResult::dispatch(
                matchId:      $match->id,
                playerId:     $me->id,
                command:      $raw,
                outputLines:  $lines,
                updatedPorts: $result['ports'],
            );
        } else {
            PacketHijackCommandResult::dispatch(
                matchId:     $match->id,
                playerId:    $me->id,
                command:     $raw,
                outputLines: ["[ERROR]: {$result['error']}"],
            );
        }

        return response()->json(['ok' => true]);
    }

    private function handleBreach(PacketHijackMatch $match, string $role, Player $me, string $raw, array $args): JsonResponse
    {
        $inputIp  = $args[0] ?? '';
        $ports    = $match->portsFor($role);
        $targetIp = $match->targetIpFor($role);

        $result = $this->phService->commandBreach($ports, $targetIp, $inputIp);

        if (!$result['success']) {
            PacketHijackCommandResult::dispatch(
                matchId:     $match->id,
                playerId:    $me->id,
                command:     $raw,
                outputLines: ["[ERROR]: {$result['error']}"],
            );
            return response()->json(['ok' => true]);
        }

        if ($match->status === 'complete') {
            return response()->json(['ok' => true]);
        }

        return $this->resolveMatch($match, $me, $role, $raw);
    }

    // =========================================================================
    // Private — rig command handler (Phase 7)
    // =========================================================================

    /**
     * Execute an equipped rig command during an active PH match.
     *
     * Validates:
     *   - The player has the command equipped (is_active) and it is a 'hack' or 'map' command.
     *   - Ghost Protocol and Signal Noise are 'map'-context commands that carry defined
     *     Packet Hijack effects, so both contexts are accepted here.
     *   - The command has not already been used this match (one-use-per-match guard).
     *
     * Then delegates to PacketHijackService::applyRigCommand(), saves match state,
     * and dispatches the appropriate broadcast events.
     */
    private function handleRigCommand(
        PacketHijackMatch $match,
        string            $role,
        Player            $me,
        string            $slug
    ): JsonResponse {
        // ── Load player's active hack commands ────────────────────────────────
        $activeCommands = PlayerCommand::where('player_id', $me->id)
            ->where('is_active', true)
            ->with('command')
            ->get();

        $playerCmd = $activeCommands->first(function ($pc) use ($slug) {
            $cmdSlug = strtolower(str_replace(' ', '_', $pc->command->name ?? ''));
            return $cmdSlug === $slug && in_array($pc->command->context ?? '', ['hack', 'map'], true);
        });

        if ($playerCmd === null) {
            PacketHijackCommandResult::dispatch(
                matchId:     $match->id,
                playerId:    $me->id,
                command:     "[RIG CMD]: {$slug}",
                outputLines: ["[ERROR]: COMMAND NOT EQUIPPED OR NOT A HACK COMMAND: {$slug}"],
            );
            return response()->json(['ok' => true]);
        }

        // ── One-use-per-match guard ───────────────────────────────────────────
        $usedKey      = "{$role}_used_commands";
        $usedCommands = $match->$usedKey ?? [];

        if (in_array($slug, $usedCommands, true)) {
            PacketHijackCommandResult::dispatch(
                matchId:     $match->id,
                playerId:    $me->id,
                command:     "[RIG CMD]: {$slug}",
                outputLines: ["[ERROR]: COMMAND ALREADY DEPLOYED THIS MATCH — ONE USE PER SESSION"],
            );
            return response()->json(['ok' => true]);
        }

        $level = (int) ($playerCmd->level ?? 1);

        // ── Apply effect via service ──────────────────────────────────────────
        $result = $this->phService->applyRigCommand($slug, $match, $role, $level);

        if (!$result['success']) {
            PacketHijackCommandResult::dispatch(
                matchId:     $match->id,
                playerId:    $me->id,
                command:     "[RIG CMD]: {$slug}",
                outputLines: ["[ERROR]: {$result['error']}"],
            );
            return response()->json(['ok' => true]);
        }

        // Mark command as used and persist all match state changes
        $usedCommands[]  = $slug;
        $match->$usedKey = $usedCommands;
        $match->save();

        $opponentId   = $match->opponentIdOf($me->id);
        $opponentRole = $role === 'challenger' ? 'defender' : 'challenger';
        $cmdLabel     = '[RIG CMD]: ' . strtoupper(str_replace('_', ' ', $slug));

        // ── Broadcast result to command user ──────────────────────────────────
        PacketHijackCommandResult::dispatch(
            matchId:     $match->id,
            playerId:    $me->id,
            command:     $cmdLabel,
            outputLines: $result['output_lines'],
        );

        // ── Broadcast disruption notice to opponent (if applicable) ───────────
        if (!empty($result['opponent_lines']) && $opponentId !== null) {
            // Determine if we should also send updated port state to the opponent
            $opponentPortsUpdated = $result['opponent_ports_updated'] ?? false;
            $opponentPorts        = $opponentPortsUpdated ? $match->portsFor($opponentRole) : null;

            PacketHijackCommandResult::dispatch(
                matchId:      $match->id,
                playerId:     $opponentId,
                command:      '[INCOMING RIG CMD]',
                outputLines:  $result['opponent_lines'],
                updatedPorts: $opponentPorts,
                lockUntil:    $result['opponent_lock_until'] ?? null,
            );
        }

        // ── Mirror reflection broadcasts ──────────────────────────────────────
        if ($result['mirror_fired'] ?? false) {
            // Notify the attacker that their command rebounded
            if (!empty($result['mirror_rebound'])) {
                PacketHijackCommandResult::dispatch(
                    matchId:     $match->id,
                    playerId:    $me->id,
                    command:     '[MIRROR REFLECTION]',
                    outputLines: $result['mirror_rebound'],
                );
            }

            // Notify the mirror holder that their shield activated
            if (!empty($result['mirror_lines']) && $opponentId !== null) {
                PacketHijackCommandResult::dispatch(
                    matchId:     $match->id,
                    playerId:    $opponentId,
                    command:     '[MIRROR PROTOCOL]',
                    outputLines: $result['mirror_lines'],
                );
            }
        }

        return response()->json(['ok' => true]);
    }

    // =========================================================================
    // Match resolution — mirrors CombatController::resolve() economy logic
    // =========================================================================

    private function resolveMatch(PacketHijackMatch $match, Player $winner, string $winnerRole, string $raw): JsonResponse
    {
        $loserId = $match->opponentIdOf($winner->id);
        $loser   = Player::find($loserId);

        if ($loser === null) {
            return response()->json(['message' => 'Opponent player not found.'], 500);
        }

        // ── 1. Resolve loot BEFORE damage (mirrors CombatController order) ────
        $loserRig    = $this->rigService->getRigForPlayer($loser);
        $winnerRig   = $this->rigService->getRigForPlayer($winner);
        $damageEvent = null;

        $loserFirewall = $loserRig
            ? $this->rigService->effectiveStats($loserRig, $loser)['firewall']['effective']
            : 0;
        $winnerCpu = $winnerRig
            ? $this->rigService->effectiveStats($winnerRig, $winner)['cpu']['effective']
            : 1;

        $pvpDamage     = max(15, 20 + ($winnerCpu * 5) - ($loserFirewall * 5));
        $currentSs     = (int) ($loserRig?->current_ss ?? 0);
        $isElimination = $loserRig !== null && ($currentSs - $pvpDamage) <= 0;

        $loot = $this->bountyService->resolvePvpLoot($winner, $loser, $isElimination);

        // ── 2. Apply PvP damage to loser ──────────────────────────────────────
        if ($loserRig !== null) {
            $damageResult = $this->rigService->applyDamage(
                rig:    $loserRig,
                amount: $pvpDamage,
                source: 'pvp',
                player: $loser,
            );
            $loserRig    = $damageResult['rig'];
            $damageEvent = $damageResult['event'];
        }

        // ── 3. Post-combat silent moves + loser state reset ───────────────────
        $winner->post_combat_silent_moves = 2;
        $winner->save();

        if ($damageEvent !== 'critical_failure') {
            $this->bountyService->resetAfterPvpLoss($loser);
        }
        $loser->post_combat_silent_moves = 2;
        $loser->save();

        // ── 4. Winner bounty escalation ───────────────────────────────────────
        $this->bountyService->recordPvpWin($winner);

        // ── 5. Mark match complete ────────────────────────────────────────────
        $match->winner_id    = $winner->id;
        $match->status       = 'complete';
        $match->completed_at = now();
        $match->save();

        // Close the originating challenge so neither player is permanently flagged in-combat
        \App\Models\CombatChallenge::where('challenger_id', $match->challenger_id)
            ->where('target_id', $match->defender_id)
            ->where('status', 'accepted')
            ->update(['status' => 'resolved']);

        // ── 6. Broadcast outcome to both players ──────────────────────────────
        PacketHijackCommandResult::dispatch(
            matchId:     $match->id,
            playerId:    $winner->id,
            command:     $raw,
            outputLines: [
                '[BREACH]: TRANSMITTING PAYLOAD...',
                '[==================================================>] 100%',
                '[BREACH COMPLETE]: NODE FULLY PURGED. CRED BUFFER ACQUIRED.',
                '[CONNECTION TERMINATED]: YOU WIN THE MATCH.',
            ],
        );

        PacketHijackMatchComplete::dispatch(
            matchId:     $match->id,
            playerId:    $winner->id,
            isWinner:    true,
            winnerId:    $winner->id,
            loserId:     $loser->id,
            credsStolen: $loot['stolen'],
        );

        PacketHijackMatchComplete::dispatch(
            matchId:     $match->id,
            playerId:    $loser->id,
            isWinner:    false,
            winnerId:    $winner->id,
            loserId:     $loser->id,
            credsStolen: $loot['stolen'],
        );

        return response()->json(['ok' => true]);
    }
}
