<?php

namespace App\Services;

use App\Models\PacketHijackMatch;
use App\Models\Player;
use App\Models\PlayerRig;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * PacketHijackService — all game logic for the Packet Hijack PvP mini-game.
 *
 * Separation of concerns:
 *   - IP pool generation, recon commands, port topology, cascade math,
 *     malware payload validation, and rig-command disruption all live here.
 *   - No HTTP objects, no request data, no broadcasting — those belong in the
 *     controller and event classes respectively.
 *   - All stat reads go through RigService::effectiveStats().
 */
class PacketHijackService
{
    // ── Port catalogue ────────────────────────────────────────────────────────

    /** Ports available for Phase 2 topology. Service name keyed by port number. */
    private const PORT_CATALOGUE = [
        21   => 'FTP',
        22   => 'SSH',
        80   => 'HTTP',
        443  => 'HTTPS',
        3306 => 'MySQL',
        8080 => 'Alt-HTTP',
    ];

    /** The exfil port that unlocks after all catalogue ports are shattered. */
    private const EXFIL_PORT = 8080;

    /** Bias threshold: a port must be AT OR BELOW this value to be exploitable. */
    private const EXPLOIT_THRESHOLD = 25;

    /**
     * Overclock exploit threshold — replaces EXPLOIT_THRESHOLD for the next
     * exploit command when the player has overclock_active.
     */
    private const OVERCLOCK_THRESHOLD = 45;

    /** Lock duration in seconds when a player hits a honeypot. */
    private const HONEYPOT_LOCK_SECONDS = 3;

    /** Number of suspect IPs on the Phase 1 board. */
    private const SUSPECT_COUNT = 14;

    /** Recognised Phase 1 commands. */
    private const PHASE1_COMMANDS = ['netstat', 'ping', 'traceroute', 'arp', 'whois', 'sniff', 'flush', 'inject'];

    /** Recognised Phase 2 commands. */
    private const PHASE2_COMMANDS = ['probe', 'exploit', 'decode', 'breach'];

    /** Port flavor text for probe command — indexed by port number. */
    private const PORT_FLAVOR = [
        21   => ['LEGACY FTP PROTOCOL DETECTED', 'PLAIN TEXT AUTHENTICATION — NO ENCRYPTION'],
        22   => ['SECURE SHELL SERVICE DETECTED', 'ENCRYPTED CHANNEL — KEY EXCHANGE HANDSHAKE ACTIVE'],
        80   => ['UNENCRYPTED HTTP SERVICE DETECTED', 'NULL AUTH HEADER PRESENT — OPEN CHANNEL'],
        443  => ['TLS ENCRYPTED HTTPS SERVICE', 'CERTIFICATE PINNING ACTIVE — HARDENED GATE'],
        3306 => ['MYSQL DATABASE PORT EXPOSED', 'MISCONFIGURATION DETECTED — PORT SHOULD NOT BE PUBLIC'],
        8080 => ['EXFILTRATION CHANNEL — ALT-HTTP', 'PAYLOAD DELIVERY ROUTE — AWAITING UNLOCK'],
    ];

    /**
     * Rig commands that buff the user (self-targeting).
     * Mirror protocol reflects these to the mirror holder (opponent).
     */
    private const SELF_BUFF_COMMANDS = ['trace_route', 'overclock', 'mirror_protocol', 'data_spike'];

    public function __construct(private readonly RigService $rigService) {}

    // =========================================================================
    // Match Initialisation
    // =========================================================================

    /**
     * Generate a random RFC-1918 IP address for a player's rig.
     * Each player gets a unique IP assigned at match start; this is the value
     * the opponent must locate during Phase 1.
     */
    public function generateRigIp(): string
    {
        $ranges = [
            fn() => '192.168.' . random_int(0, 255) . '.' . random_int(1, 254),
            fn() => '10.'      . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254),
            fn() => '172.'     . random_int(16, 31)  . '.' . random_int(0, 255) . '.' . random_int(1, 254),
        ];

        return $ranges[array_rand($ranges)]();
    }

    /**
     * Generate the Phase 1 suspect board for one player.
     *
     * Creates SUSPECT_COUNT IP objects representing active connections on the
     * shared node. One entry is the real target; the rest are decoys. Every
     * attribute is seeded server-side and stored — the client only sees what
     * has been revealed through commands (is_target is NEVER sent to client).
     *
     * Decoy believability scales with the target's OS stat:
     *   OS 1–2 → 0 fast decoys (target is obvious on ping alone)
     *   OS 3–4 → 1–2 fast decoys
     *   OS 5–6 → 3–4 fast decoys (Ghost chassis — hard to distinguish)
     *   OS 7+  → 5+ fast decoys (near-impossible without arp + whois)
     *
     * Suspect object schema:
     *   ip               string   — RFC-1918 address
     *   latency_ms       int|null — null = timeout
     *   latency_status   string   — LIVE | DEGRADED | TIMEOUT
     *   hops             int      — 1–12
     *   network_range    string   — 192.x | 10.x | 172.x
     *   last_seen_seconds int     — seconds since last ARP activity
     *   whois_class      string   — node type label
     *   whois_redacted   bool     — true if target OS ≥ 6
     *   is_target        bool     — NEVER sent to client
     *   flushed          bool     — set true by flush command
     */
    public function generateNodeConnections(string $realIp, int $targetOs): array
    {
        $suspects   = [];
        $usedIps    = [$realIp];
        $fastDecoys = (int) min(6, max(0, floor(($targetOs - 1) * 1.2)));

        $whoisClasses = [
            'STATIC NODE', 'RELAY HUB', 'PROXY ENDPOINT', 'ANONYMOUS RELAY',
            'MESH NODE', 'DARK RELAY', 'TRANSIT HOP', 'EDGE NODE',
        ];

        $generators = [
            fn() => '192.168.' . random_int(0, 255) . '.' . random_int(1, 254),
            fn() => '10.'      . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254),
            fn() => '172.'     . random_int(16, 31)  . '.' . random_int(0, 255) . '.' . random_int(1, 254),
        ];

        $parts        = explode('.', $realIp);
        $targetRange  = $parts[0] === '192' ? '192.x' : ($parts[0] === '10' ? '10.x' : '172.x');

        // ── Build decoys ──────────────────────────────────────────────────────
        $decoyCount = self::SUSPECT_COUNT - 1;

        for ($i = 0; $i < $decoyCount; $i++) {
            do {
                $ip = $generators[array_rand($generators)]();
            } while (in_array($ip, $usedIps, true));
            $usedIps[] = $ip;

            $ipParts = explode('.', $ip);
            $range   = $ipParts[0] === '192' ? '192.x' : ($ipParts[0] === '10' ? '10.x' : '172.x');

            // Fast decoys — blend with the real target to add uncertainty
            if ($i < $fastDecoys) {
                $latencyMs     = random_int(2, 12);
                $latencyStatus = 'LIVE';
                $hops          = random_int(1, 4);
                $lastSeen      = random_int(1, 15);
            } else {
                // Slow / dead decoys — easy to eliminate
                $roll = random_int(0, 2);
                if ($roll === 0) {
                    $latencyMs     = null;
                    $latencyStatus = 'TIMEOUT';
                    $hops          = random_int(6, 12);
                    $lastSeen      = random_int(60, 600);
                } elseif ($roll === 1) {
                    $latencyMs     = random_int(150, 450);
                    $latencyStatus = 'DEGRADED';
                    $hops          = random_int(5, 10);
                    $lastSeen      = random_int(30, 300);
                } else {
                    $latencyMs     = random_int(20, 80);
                    $latencyStatus = 'LIVE';
                    $hops          = random_int(3, 8);
                    $lastSeen      = random_int(10, 120);
                }
            }

            $suspects[] = [
                'ip'                 => $ip,
                'latency_ms'         => $latencyMs,
                'latency_status'     => $latencyStatus,
                'hops'               => $hops,
                'network_range'      => $range,
                'last_seen_seconds'  => $lastSeen,
                'whois_class'        => $whoisClasses[array_rand($whoisClasses)],
                'whois_redacted'     => (bool) random_int(0, 1),
                'is_target'          => false,
                'flushed'            => false,
            ];
        }

        // ── Build real target entry ───────────────────────────────────────────
        $chassisClasses = ['MOBILE RIG', 'BREAKER UNIT', 'VAULT CHASSIS', 'GHOST FRAME'];

        $suspects[] = [
            'ip'                 => $realIp,
            'latency_ms'         => random_int(2, 8),
            'latency_status'     => 'LIVE',
            'hops'               => random_int(1, 3),
            'network_range'      => $targetRange,
            'last_seen_seconds'  => random_int(0, 2),
            'whois_class'        => $chassisClasses[array_rand($chassisClasses)],
            'whois_redacted'     => $targetOs >= 6,
            'is_target'          => true,
            'flushed'            => false,
        ];

        shuffle($suspects);

        return $suspects;
    }

    /**
     * Generate the Phase 2 port topology for a player's match side.
     *
     * Selects 4 ports from the catalogue (excluding 8080 which is the exfil).
     * One port starts with LOW bias (8–18%), the rest start HIGH (70–95%).
     * The owning player's Firewall stat raises all bias values — higher FW
     * means the attacker faces tougher gates.
     *
     * Schema per entry: { port, service, bias, shattered, shattered_at, unlocked }
     */
    public function generatePortTopology(PlayerRig $rig, Player $player): array
    {
        $stats    = $this->rigService->effectiveStats($rig, $player);
        $firewall = $stats['firewall']['effective'];

        // Pick 4 catalogue ports (never the exfil port)
        $catalogue = array_filter(
            self::PORT_CATALOGUE,
            fn($service, $port) => $port !== self::EXFIL_PORT,
            ARRAY_FILTER_USE_BOTH
        );

        $portNumbers = array_keys($catalogue);
        shuffle($portNumbers);
        $selected = array_slice($portNumbers, 0, 4);

        // Assign bias values
        $lowIndex = array_rand($selected);
        $ports    = [];

        foreach ($selected as $i => $port) {
            if ($i === $lowIndex) {
                // LOW bias port — exploitable from the start
                $bias = random_int(8, 18) + min($firewall * 3, 10);
                $bias = min($bias, self::EXPLOIT_THRESHOLD - 1);
            } else {
                // HIGH bias port — requires cascade to crack
                $bias = random_int(70, 90) + min($firewall * 3, 8);
                $bias = min($bias, 98);
            }

            $ports[] = [
                'port'         => $port,
                'service'      => self::PORT_CATALOGUE[$port],
                'bias'         => $bias,
                'shattered'    => false,
                'shattered_at' => null,
                'unlocked'     => false,
            ];
        }

        // Add the exfil port — always starts locked (unlocked after all others shattered)
        $ports[] = [
            'port'         => self::EXFIL_PORT,
            'service'      => self::PORT_CATALOGUE[self::EXFIL_PORT],
            'bias'         => 100,
            'shattered'    => false,
            'shattered_at' => null,
            'unlocked'     => false,
        ];

        return $ports;
    }

    // =========================================================================
    // Command Parser
    // =========================================================================

    /**
     * Parse and validate a raw terminal input string.
     *
     * Returns ['valid' => true,  'command' => string, 'args' => array]
     *      or ['valid' => false, 'error'   => string]
     *
     * Recognised patterns:
     *   netstat --active
     *   ping <ip>
     *   traceroute <ip>
     *   arp --scan
     *   whois <ip>
     *   sniff --traffic
     *   flush <ip>
     *   inject <ip>
     *   probe port <number>
     *   exploit port <number>
     *   decode port <number>
     *   breach <ip>
     */
    public function parseCommand(string $raw): array
    {
        $tokens = preg_split('/\s+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($tokens)) {
            return ['valid' => false, 'error' => 'EMPTY INPUT'];
        }

        $command     = strtolower($tokens[0]);
        $allCommands = array_merge(self::PHASE1_COMMANDS, self::PHASE2_COMMANDS);

        if (!in_array($command, $allCommands, true)) {
            return ['valid' => false, 'error' => "COMMAND NOT FOUND: {$tokens[0]}"];
        }

        $argCount = count($tokens) - 1;

        $argRequirements = [
            'netstat'    => [0, 1],   // netstat --active
            'ping'       => [1, 1],   // ping <ip>
            'traceroute' => [1, 1],   // traceroute <ip>
            'arp'        => [0, 1],   // arp --scan
            'whois'      => [1, 1],   // whois <ip>
            'sniff'      => [0, 1],   // sniff --traffic
            'flush'      => [1, 1],   // flush <ip>
            'inject'     => [1, 1],   // inject <ip>
            'probe'      => [2, 2],   // probe port <number>
            'exploit'    => [2, 2],   // exploit port <number>
            'decode'     => [2, 2],   // decode port <number>
            'breach'     => [1, 1],   // breach <ip>
        ];

        [$min, $max] = $argRequirements[$command];

        if ($argCount < $min || $argCount > $max) {
            return ['valid' => false, 'error' => "SYNTAX ERROR: unexpected argument count for '{$command}'"];
        }

        return ['valid' => true, 'command' => $command, 'args' => array_slice($tokens, 1)];
    }

    // =========================================================================
    // Phase 1 — Recon Commands
    // =========================================================================

    /**
     * netstat --active — returns the full suspect list as the initial case file.
     * Returns suspect objects stripped of is_target (never sent to client).
     * Only the IP addresses are revealed at this stage — all attribute columns
     * show as unrevealed until the player runs further commands.
     */
    public function commandNetstat(array $suspects): array
    {
        return array_map(fn($s) => ['ip' => $s['ip'], 'flushed' => $s['flushed']], $suspects);
    }

    /**
     * ping <ip> — probe a specific suspect for latency.
     *
     * Returns the latency_ms and latency_status for the given IP.
     * IP must exist in the suspect list.
     */
    public function commandPing(array $suspects, string $ip): array
    {
        foreach ($suspects as $s) {
            if ($s['ip'] === $ip) {
                return [
                    'found'          => true,
                    'latency_ms'     => $s['latency_ms'],
                    'latency_status' => $s['latency_status'],
                ];
            }
        }
        return ['found' => false, 'error' => "HOST {$ip} NOT FOUND IN TRACE BUFFER"];
    }

    /**
     * traceroute <ip> — reveal hop count and network range for a specific suspect.
     */
    public function commandTraceroute(array $suspects, string $ip): array
    {
        foreach ($suspects as $s) {
            if ($s['ip'] === $ip) {
                return [
                    'found'         => true,
                    'hops'          => $s['hops'],
                    'network_range' => $s['network_range'],
                ];
            }
        }
        return ['found' => false, 'error' => "ROUTE TO {$ip} UNREACHABLE"];
    }

    /**
     * arp --scan — reveal last-seen timestamps for ALL suspects at once.
     * Returns an array of [ip, last_seen_seconds] pairs.
     */
    public function commandArpScan(array $suspects): array
    {
        return array_map(fn($s) => [
            'ip'                => $s['ip'],
            'last_seen_seconds' => $s['last_seen_seconds'],
        ], $suspects);
    }

    /**
     * whois <ip> — reveal chassis class hint for a specific suspect.
     * If whois_redacted is true (high OS target), returns a redacted response.
     */
    public function commandWhois(array $suspects, string $ip): array
    {
        foreach ($suspects as $s) {
            if ($s['ip'] === $ip) {
                return [
                    'found'    => true,
                    'redacted' => $s['whois_redacted'],
                    'class'    => $s['whois_redacted'] ? 'DATA REDACTED' : $s['whois_class'],
                ];
            }
        }
        return ['found' => false, 'error' => "WHOIS RECORD NOT FOUND FOR {$ip}"];
    }

    /**
     * sniff --traffic — intercept one octet fragment from the target's live stream.
     * Returns a middle octet (e.g. '.4.') as a tiebreaker clue.
     * The player cross-references this against the case file manually.
     */
    public function commandSniff(string $realIp): string
    {
        $parts = explode('.', $realIp);
        $index = random_int(1, 2);
        return '.' . $parts[$index] . '.';
    }

    /**
     * flush <ip> — mark a suspect as eliminated from the active working set.
     * Sets flushed = true on the matching entry. The entry stays in the case
     * file struck-through — the player made the call, not the game.
     *
     * Returns ['success' => true] or ['success' => false, 'error' => string]
     */
    public function commandFlush(array $suspects, string $ip): array
    {
        foreach ($suspects as $i => $s) {
            if ($s['ip'] === $ip) {
                if ($s['flushed']) {
                    return ['success' => false, 'error' => "{$ip} ALREADY PURGED FROM TRACE BUFFER"];
                }
                $suspects[$i]['flushed'] = true;
                return ['success' => true, 'suspects' => $suspects];
            }
        }
        return ['success' => false, 'error' => "{$ip} NOT FOUND IN TRACE BUFFER"];
    }

    /**
     * inject — attempt to identify the target IP and advance to Phase 2.
     *
     * Returns one of:
     *   ['success' => true]
     *   ['success' => false, 'honeypot' => true,  'lock_until' => Carbon]
     *   ['success' => false, 'error'    => 'not_in_suspects']
     */
    public function commandInject(string $realIp, string $attempt, array $suspects): array
    {
        $ips = array_column($suspects, 'ip');

        if (!in_array($attempt, $ips, true)) {
            return ['success' => false, 'error' => 'not_in_suspects'];
        }

        if ($attempt === $realIp) {
            return ['success' => true];
        }

        return [
            'success'    => false,
            'honeypot'   => true,
            'lock_until' => Carbon::now()->addSeconds(self::HONEYPOT_LOCK_SECONDS),
        ];
    }

    // =========================================================================
    // Phase 2 — Port Commands
    // =========================================================================

    /**
     * probe port <number> — returns rich port status with service flavor text.
     *
     * Replaces the old scan command. Same mechanic but returns service-specific
     * flavor text explaining why the port is or isn't exploitable, making the
     * topology feel like real infrastructure rather than abstract numbers.
     *
     * Checks active sector-corrupt entries and bait traps — fake bias overrides
     * apply here exactly as they did on scan.
     *
     * Returns ['found' => true,  'entry' => array, 'flavor' => array]
     *      or ['found' => false, 'error' => string]
     */
    public function commandProbePort(
        array $ports,
        int   $portNumber,
        array $corruptPorts = [],
        array $baitPorts    = []
    ): array {
        foreach ($ports as $entry) {
            if ((int) $entry['port'] === $portNumber) {
                $fakeBias = $this->resolveFakeBias($portNumber, $corruptPorts, $baitPorts);

                $displayEntry = $fakeBias !== null
                    ? array_merge($entry, ['bias' => $fakeBias])
                    : $entry;

                $flavor = self::PORT_FLAVOR[$portNumber] ?? [
                    "SERVICE DETECTED ON PORT {$portNumber}",
                    'UNKNOWN PROTOCOL',
                ];

                return ['found' => true, 'entry' => $displayEntry, 'flavor' => $flavor];
            }
        }

        return ['found' => false, 'error' => "PORT {$portNumber} NOT IN TARGET TOPOLOGY"];
    }

    /**
     * exploit port <number> — attempt to shatter a port.
     *
     * Rules:
     *   - Port must exist in topology and not already be shattered.
     *   - Check bait first — if the port is baited, return a lock result.
     *   - Port bias must be <= EXPLOIT_THRESHOLD (or OVERCLOCK_THRESHOLD if active).
     *   - On success: port is marked shattered (with shattered_at timestamp);
     *     cascade reduces all remaining live ports' bias by floor(attacker_cpu * 4),
     *     min 10, max 40.
     *   - If all catalogue ports (non-exfil) are now shattered, the exfil
     *     port (8080) is unlocked.
     *
     * Returns:
     *   ['success' => true,  'ports' => array, 'cascade_log' => array, 'exfil_unlocked' => bool]
     *   ['success' => false, 'baited' => true, 'lock_seconds' => float]
     *   ['success' => false, 'error' => string]
     */
    public function commandExploitPort(
        array     $ports,
        int       $portNumber,
        PlayerRig $rig,
        Player    $player,
        bool      $overclocked = false,
        array     $baitPorts   = []
    ): array {
        // ── Bait check — must happen before any other validation ─────────────
        foreach ($baitPorts as $bait) {
            if ((int) $bait['port'] === $portNumber) {
                return [
                    'success'      => false,
                    'baited'       => true,
                    'lock_seconds' => (float) $bait['lock_seconds'],
                ];
            }
        }

        $stats         = $this->rigService->effectiveStats($rig, $player);
        $attackerCpu   = $stats['cpu']['effective'];
        $cascadeAmount = (int) min(40, max(10, floor($attackerCpu * 4)));
        $threshold     = $overclocked ? self::OVERCLOCK_THRESHOLD : self::EXPLOIT_THRESHOLD;

        $targetIndex = null;
        foreach ($ports as $i => $entry) {
            if ((int) $entry['port'] === $portNumber) {
                $targetIndex = $i;
                break;
            }
        }

        if ($targetIndex === null) {
            return ['success' => false, 'error' => "PORT {$portNumber} NOT IN TARGET TOPOLOGY"];
        }

        $target = $ports[$targetIndex];

        if ($target['shattered']) {
            return ['success' => false, 'error' => "PORT {$portNumber} ALREADY SHATTERED"];
        }

        if ($portNumber === self::EXFIL_PORT && !$target['unlocked']) {
            return ['success' => false, 'error' => "EXFIL PORT LOCKED — CLEAR ALL GATES FIRST"];
        }

        if ((int) $target['bias'] > $threshold) {
            $thresholdLabel = $overclocked ? " [OVERCLOCK ACTIVE: {$threshold}%]" : '';
            return [
                'success' => false,
                'error'   => "DECRYPTION BIAS TOO HIGH [{$target['bias']}%] — FIND A WEAKER ENTRY POINT{$thresholdLabel}",
            ];
        }

        // Shatter the target port, recording when it fell
        $ports[$targetIndex]['shattered']    = true;
        $ports[$targetIndex]['shattered_at'] = now()->toIso8601String();

        // Apply cascade to remaining live, non-exfil ports
        $cascadeLog = [];
        foreach ($ports as $i => $entry) {
            if ($entry['shattered'] || (int) $entry['port'] === self::EXFIL_PORT) {
                continue;
            }
            $oldBias             = (int) $entry['bias'];
            $newBias             = max(0, $oldBias - $cascadeAmount);
            $ports[$i]['bias']   = $newBias;
            $cascadeLog[]        = [
                'port'     => $entry['port'],
                'service'  => $entry['service'],
                'old_bias' => $oldBias,
                'new_bias' => $newBias,
            ];
        }

        // Check if all non-exfil ports are now shattered → unlock exfil
        $exfilIndex     = null;
        $allShattered   = true;
        foreach ($ports as $i => $entry) {
            if ((int) $entry['port'] === self::EXFIL_PORT) {
                $exfilIndex = $i;
                continue;
            }
            if (!$entry['shattered']) {
                $allShattered = false;
            }
        }

        $exfilUnlocked = false;
        if ($allShattered && $exfilIndex !== null && !$ports[$exfilIndex]['unlocked']) {
            $ports[$exfilIndex]['unlocked']     = true;
            $ports[$exfilIndex]['bias']         = 0;
            $exfilUnlocked                      = true;
        }

        return [
            'success'        => true,
            'ports'          => $ports,
            'cascade_log'    => $cascadeLog,
            'exfil_unlocked' => $exfilUnlocked,
        ];
    }

    /**
     * decode port <number> — manually chip away at a port's decryption bias.
     *
     * Works on any non-shattered port regardless of current bias level.
     * Each use reduces bias by 10 + floor(attackerCPU × 2), capped at 35.
     * Does NOT shatter the port — the player still needs exploit once bias
     * drops below the threshold.
     *
     * Strategy: high-CPU rigs can decode a specific port down to exploitable
     * range faster than waiting for cascade from another port.
     *
     * Returns ['success' => true, 'ports' => array, 'reduction' => int, 'new_bias' => int]
     *      or ['success' => false, 'error' => string]
     */
    public function commandDecodePort(
        array     $ports,
        int       $portNumber,
        PlayerRig $rig,
        Player    $player
    ): array {
        $stats     = $this->rigService->effectiveStats($rig, $player);
        $cpu       = $stats['cpu']['effective'];
        $reduction = (int) min(35, 10 + floor($cpu * 2));

        foreach ($ports as $i => $entry) {
            if ((int) $entry['port'] !== $portNumber) {
                continue;
            }
            if ($entry['shattered']) {
                return ['success' => false, 'error' => "PORT {$portNumber} ALREADY SHATTERED — NOTHING TO DECODE"];
            }
            if ($portNumber === self::EXFIL_PORT) {
                return ['success' => false, 'error' => "EXFIL PORT CANNOT BE DECODED — SHATTER ALL GATES FIRST"];
            }

            $oldBias           = (int) $entry['bias'];
            $newBias           = max(0, $oldBias - $reduction);
            $ports[$i]['bias'] = $newBias;

            return [
                'success'   => true,
                'ports'     => $ports,
                'reduction' => $reduction,
                'old_bias'  => $oldBias,
                'new_bias'  => $newBias,
                'port'      => $portNumber,
                'service'   => $entry['service'],
            ];
        }

        return ['success' => false, 'error' => "PORT {$portNumber} NOT IN TARGET TOPOLOGY"];
    }

    /**
     * breach <ip> — simplified final payload execution.
     *
     * Replaces the old malware inject command. Player supplies only the target
     * IP they identified in Phase 1 — the exfil port is implicit.
     *
     * Validates:
     *   - Supplied IP matches the real target IP exactly.
     *   - The exfil port (8080) is unlocked in the topology.
     *
     * Returns ['success' => true] or ['success' => false, 'error' => string]
     */
    public function commandBreach(array $ports, string $targetIp, string $inputIp): array
    {
        if ($inputIp !== $targetIp) {
            return ['success' => false, 'error' => 'IP MISMATCH — TARGET SIGNATURE REJECTED'];
        }

        foreach ($ports as $entry) {
            if ((int) $entry['port'] === self::EXFIL_PORT) {
                if (!$entry['unlocked']) {
                    return ['success' => false, 'error' => 'EXFIL PORT STILL LOCKED — SHATTER ALL GATES FIRST'];
                }
                return ['success' => true];
            }
        }

        return ['success' => false, 'error' => 'EXFIL PORT NOT FOUND IN TARGET TOPOLOGY'];
    }

    // =========================================================================
    // Rig Command Disruptions (Phase 7)
    // =========================================================================

    /**
     * Apply a loadout command's Packet Hijack disruption effect.
     *
     * After applying the main effect, checks whether the target player (opponent
     * for attacks, self for buffs) has Mirror Protocol active. If so, the same
     * command is applied in reverse: attack → also hits the attacker; buff →
     * also given to the mirror holder.
     *
     * Returns a result array describing what happened so the controller can
     * update match state and broadcast the appropriate events.
     *
     * Return keys:
     *   success             bool
     *   effect              string  — slug of the command that fired
     *   output_lines        array   — terminal lines for the command user
     *   opponent_lines      array   — terminal lines for the opponent (disruption notice)
     *   opponent_lock_until string|null  — ISO-8601 if opponent's input was locked
     *   mirror_fired        bool
     *   mirror_lines        array   — terminal lines for the mirror holder (confirmation)
     *   mirror_rebound      array   — terminal lines for the attacker (mirror hit them)
     *   error               string|null
     */
    public function applyRigCommand(
        string            $commandSlug,
        PacketHijackMatch $match,
        string            $userRole,
        int               $level = 1
    ): array {
        $opponentRole = $userRole === 'challenger' ? 'defender' : 'challenger';

        // Snapshot mirror state BEFORE applying anything (mirror is consumed after)
        $mirrorKey    = "{$opponentRole}_mirror_active";
        $mirrorActive = (bool) ($match->$mirrorKey ?? false);

        // ── Dispatch to command handler ───────────────────────────────────────
        $result = match ($commandSlug) {
            'ghost_protocol'  => $this->rigCommandGhostProtocol($match, $opponentRole),
            'signal_noise'    => $this->rigCommandSignalNoise($match, $opponentRole),
            'trace_route'     => $this->rigCommandTraceRoute($match, $userRole),
            'overclock'       => $this->rigCommandOverclock($match, $userRole),
            'mirror_protocol' => $this->rigCommandMirrorProtocol($match, $userRole),
            'data_spike'      => $this->rigCommandDataSpike($match, $userRole),
            'phase_shift'     => $this->rigCommandPhaseShift($match, $opponentRole),
            'hardlock'        => $this->rigCommandHardlock($match, $opponentRole, $level === 1 ? 2.5 : 3.5),
            'null_byte'       => $this->rigCommandNullByte($match, $opponentRole, $level === 1 ? 1 : 2),
            'static_burst'    => $this->rigCommandStaticBurst($match, $opponentRole, $level === 1 ? 2.0 : 3.0),
            'phantom_key'     => $this->rigCommandPhantomKey($match, $opponentRole, $level === 1 ? 1 : 2),
            'sector_purge'    => $this->rigCommandSectorPurge($match, $opponentRole, $level),
            'sector_corrupt'  => $this->rigCommandSectorCorrupt($match, $opponentRole, $level === 1 ? 1 : 2),
            'bait'            => $this->rigCommandBait($match, $opponentRole, $level === 1 ? 3 : 5),
            default           => ['success' => false, 'error' => "NO PACKET HIJACK EFFECT FOR: {$commandSlug}"],
        };

        if (!$result['success']) {
            return array_merge(['mirror_fired' => false], $result);
        }

        // Ensure default keys are present
        $result += [
            'opponent_lines'      => [],
            'opponent_lock_until' => null,
            'mirror_fired'        => false,
            'mirror_lines'        => [],
            'mirror_rebound'      => [],
        ];

        // ── Mirror reflection ─────────────────────────────────────────────────
        if ($mirrorActive && $commandSlug !== 'mirror_protocol') {
            $match->$mirrorKey = false; // Consume mirror

            $isSelfBuff   = in_array($commandSlug, self::SELF_BUFF_COMMANDS, true);
            $mirrorTarget = $isSelfBuff ? $opponentRole : $userRole;

            $mirrorResult = $this->applyMirrorEffect($commandSlug, $match, $mirrorTarget, $level);

            $result['mirror_fired']   = true;
            $result['mirror_lines']   = array_merge(
                ['[MIRROR PROTOCOL]: ACTIVATED — COMMAND REFLECTED'],
                $mirrorResult['output_lines'] ?? []
            );
            $result['mirror_rebound'] = array_merge(
                ['[MIRROR PROTOCOL]: OPPONENT HAD MIRROR ACTIVE — COMMAND REFLECTED BACK'],
                $mirrorResult['output_lines'] ?? []
            );
        }

        return $result;
    }

    /**
     * Apply the mirror-reflected version of a command.
     * Self-buffs are given to the mirror holder (opponentRole was passed as mirrorTarget).
     * Attacks are inflicted on the command user (userRole was passed as mirrorTarget).
     * Mirror itself never chains.
     */
    private function applyMirrorEffect(
        string            $slug,
        PacketHijackMatch $match,
        string            $mirrorTarget,
        int               $level
    ): array {
        return match ($slug) {
            'ghost_protocol' => $this->rigCommandGhostProtocol($match, $mirrorTarget),
            'signal_noise'   => $this->rigCommandSignalNoise($match, $mirrorTarget),
            'trace_route'    => $this->rigCommandTraceRoute($match, $mirrorTarget),
            'overclock'      => $this->rigCommandOverclock($match, $mirrorTarget),
            'data_spike'     => $this->rigCommandDataSpike($match, $mirrorTarget),
            'phase_shift'    => $this->rigCommandPhaseShift($match, $mirrorTarget),
            'hardlock'       => $this->rigCommandHardlock($match, $mirrorTarget, $level === 1 ? 2.5 : 3.5),
            'null_byte'      => $this->rigCommandNullByte($match, $mirrorTarget, $level === 1 ? 1 : 2),
            'static_burst'   => $this->rigCommandStaticBurst($match, $mirrorTarget, $level === 1 ? 2.0 : 3.0),
            'phantom_key'    => $this->rigCommandPhantomKey($match, $mirrorTarget, $level === 1 ? 1 : 2),
            'sector_purge'   => $this->rigCommandSectorPurge($match, $mirrorTarget, $level),
            'sector_corrupt' => $this->rigCommandSectorCorrupt($match, $mirrorTarget, $level === 1 ? 1 : 2),
            'bait'           => $this->rigCommandBait($match, $mirrorTarget, $level === 1 ? 3 : 5),
            default          => ['success' => false, 'output_lines' => []],
        };
    }

    // ── Self-buff commands ────────────────────────────────────────────────────

    /**
     * Trace Route — reveals the first octet of the target IP, narrowing
     * Phase 1 search to one RFC-1918 range without giving away the subnet.
     */
    private function rigCommandTraceRoute(PacketHijackMatch $match, string $userRole): array
    {
        $targetIpKey = "{$userRole}_target_ip";
        $targetIp    = $match->$targetIpKey;
        $firstOctet  = explode('.', $targetIp)[0];

        return [
            'success'      => true,
            'effect'       => 'trace_route',
            'output_lines' => [
                '[TRACE ROUTE]: ROUTING PROBE DISPATCHED...',
                "[RESULT]: ORIGIN NETWORK BLOCK RESOLVED — {$firstOctet}.x.x.x",
            ],
            'opponent_lines' => [
                '[ALERT]: OPPONENT DEPLOYED TRACE ROUTE — NETWORK ORIGIN TRIANGULATED',
            ],
        ];
    }

    /**
     * Overclock — raises the exploit threshold from 25% → 45% for the next
     * exploit command only. Consumed on successful exploit.
     */
    private function rigCommandOverclock(PacketHijackMatch $match, string $userRole): array
    {
        $key        = "{$userRole}_overclock_active";
        $match->$key = true;

        return [
            'success'        => true,
            'effect'         => 'overclock',
            'output_lines'   => [
                '[OVERCLOCK]: EXPLOIT MODULE OVERCLOCKED — THRESHOLD RAISED TO 45% FOR NEXT EXPLOIT',
            ],
            'opponent_lines' => [],
        ];
    }

    /**
     * Mirror Protocol — activates a mirror shield that intercepts the next
     * opponent rig command and reflects its effect back.
     * Buffs are also granted to the mirror holder; attacks also strike the attacker.
     */
    private function rigCommandMirrorProtocol(PacketHijackMatch $match, string $userRole): array
    {
        $key        = "{$userRole}_mirror_active";
        $match->$key = true;

        return [
            'success'        => true,
            'effect'         => 'mirror_protocol',
            'output_lines'   => [
                '[MIRROR PROTOCOL]: ACTIVE — NEXT OPPONENT RIG COMMAND WILL BE REFLECTED',
            ],
            'opponent_lines' => [],
        ];
    }

    /**
     * Data Spike — auto-scans the lowest-bias non-shattered port in the
     * player's own topology, returning the same output as a manual scan port
     * command without spending the input window.
     */
    private function rigCommandDataSpike(PacketHijackMatch $match, string $userRole): array
    {
        $portsKey = "{$userRole}_ports";
        $ports    = $match->$portsKey ?? [];

        // Find the lowest-bias non-shattered, non-exfil port
        $candidate = null;
        foreach ($ports as $entry) {
            if ($entry['shattered'] || (int) $entry['port'] === self::EXFIL_PORT) {
                continue;
            }
            if ($candidate === null || (int) $entry['bias'] < (int) $candidate['bias']) {
                $candidate = $entry;
            }
        }

        if ($candidate === null) {
            return [
                'success'        => true,
                'effect'         => 'data_spike',
                'output_lines'   => [
                    '[DATA SPIKE]: SCANNING PORT TOPOLOGY...',
                    '[RESULT]: NO ACTIVE PORTS DETECTED — ALL GATES ALREADY CLEARED',
                ],
                'opponent_lines' => [],
            ];
        }

        $label = (int) $candidate['bias'] <= self::EXPLOIT_THRESHOLD ? 'CRITICAL LOW' : 'HIGH';

        return [
            'success'        => true,
            'effect'         => 'data_spike',
            'output_lines'   => [
                '[DATA SPIKE]: SCANNING PORT TOPOLOGY...',
                "[RESULT]: PORT {$candidate['port']} [{$candidate['service']}] FLAGGED — DECRYPTION BIAS: {$label} [{$candidate['bias']}%]",
            ],
            'opponent_lines' => [
                '[ALERT]: OPPONENT DEPLOYED DATA SPIKE — PORT TOPOLOGY SCANNED',
            ],
        ];
    }

    // ── Attack commands ───────────────────────────────────────────────────────

    /**
     * Ghost Protocol — inject 8 fresh decoy IPs into the opponent's pool,
     * making Phase 1 isolation harder.
     */
    private function rigCommandGhostProtocol(PacketHijackMatch $match, string $targetRole): array
    {
        $poolKey = "{$targetRole}_ip_pool";
        $pool    = $match->$poolKey ?? [];

        $newDecoys = [];
        while (count($newDecoys) < 8) {
            $candidate = $this->generateRigIp();
            if (!in_array($candidate, $pool, true) && !in_array($candidate, $newDecoys, true)) {
                $newDecoys[] = $candidate;
            }
        }

        $pool            = array_merge($pool, $newDecoys);
        shuffle($pool);
        $match->$poolKey = $pool;

        return [
            'success'        => true,
            'effect'         => 'ghost_protocol',
            'output_lines'   => [
                '[GHOST PROTOCOL]: 8 DECOYS INJECTED INTO OPPONENT TRACE BUFFER',
            ],
            'opponent_lines' => [
                '[ALERT]: GHOST PROTOCOL DEPLOYED — RECON GRID POISONED',
            ],
        ];
    }

    /**
     * Signal Noise — lock the opponent's input for 4 seconds.
     * Stacks with an active honeypot lock (takes whichever is longer).
     */
    private function rigCommandSignalNoise(PacketHijackMatch $match, string $targetRole): array
    {
        $lockKey    = "{$targetRole}_locked_until";
        $newLock    = Carbon::now()->addSeconds(4);
        $existing   = $match->$lockKey;

        if ($existing === null || $existing->isPast() || $newLock->gt($existing)) {
            $match->$lockKey = $newLock;
        }

        return [
            'success'             => true,
            'effect'              => 'signal_noise',
            'output_lines'        => [
                '[SIGNAL NOISE]: OPPONENT INPUT JAMMED FOR 4.0s',
            ],
            'opponent_lines'      => [
                '[SIGNAL NOISE]: INPUT JAMMED — 4.0s',
            ],
            'opponent_lock_until' => $match->$lockKey->toIso8601String(),
        ];
    }

    /**
     * Phase Shift — un-shatters the opponent's most recently shattered port
     * (identified by shattered_at timestamp) and re-locks the exfil port if
     * all gates were previously cleared. If no shattered ports exist the
     * command fires silently (no penalty to the user).
     */
    private function rigCommandPhaseShift(PacketHijackMatch $match, string $targetRole): array
    {
        $portsKey = "{$targetRole}_ports";
        $ports    = $match->$portsKey ?? [];

        // Find the most recently shattered non-exfil port by shattered_at
        $targetIndex = null;
        $latestTime  = null;

        foreach ($ports as $i => $entry) {
            if (!$entry['shattered'] || (int) $entry['port'] === self::EXFIL_PORT) {
                continue;
            }
            $shatteredAt = $entry['shattered_at'] ?? null;
            if ($targetIndex === null) {
                $targetIndex = $i;
                $latestTime  = $shatteredAt;
            } elseif ($shatteredAt !== null && ($latestTime === null || $shatteredAt > $latestTime)) {
                $targetIndex = $i;
                $latestTime  = $shatteredAt;
            }
        }

        if ($targetIndex === null) {
            return [
                'success'        => true,
                'effect'         => 'phase_shift',
                'output_lines'   => [
                    '[PHASE SHIFT]: INTRUSION DETECTED — SCANNING CASCADE...',
                    '[RESULT]: NO SHATTERED PORTS FOUND — SHIFT ABORTED',
                ],
                'opponent_lines' => [],
            ];
        }

        $port    = $ports[$targetIndex]['port'];
        $service = $ports[$targetIndex]['service'];

        // Restore the port with a fresh HIGH bias
        $ports[$targetIndex]['shattered']    = false;
        $ports[$targetIndex]['shattered_at'] = null;
        $ports[$targetIndex]['bias']         = random_int(60, 85);

        // Re-lock exfil if it was unlocked (all-clear is no longer valid)
        foreach ($ports as $i => $entry) {
            if ((int) $entry['port'] === self::EXFIL_PORT && $entry['unlocked']) {
                $ports[$i]['unlocked'] = false;
                $ports[$i]['bias']     = 100;
            }
        }

        $match->$portsKey = $ports;

        return [
            'success'        => true,
            'effect'         => 'phase_shift',
            'output_lines'   => [
                '[PHASE SHIFT]: INTRUSION DETECTED — REVERTING CASCADE...',
                "[ALERT]: TARGET PORT {$port} [{$service}] CIRCUIT RESTORED — OPPONENT PROGRESS INTERRUPTED",
            ],
            'opponent_lines' => [
                "[ALERT]: OPPONENT PHASE SHIFT DEPLOYED — PORT {$port} [{$service}] RESTORATION FORCED",
            ],
            'opponent_ports_updated' => true,
        ];
    }

    /**
     * Hardlock — freeze the opponent's terminal input.
     * L1: 2.5s / L2: 3.5s. Stacks with existing locks.
     */
    private function rigCommandHardlock(
        PacketHijackMatch $match,
        string            $targetRole,
        float             $lockSeconds
    ): array {
        $lockKey  = "{$targetRole}_locked_until";
        $newLock  = Carbon::now()->addSeconds($lockSeconds);
        $existing = $match->$lockKey;

        if ($existing === null || $existing->isPast() || $newLock->gt($existing)) {
            $match->$lockKey = $newLock;
        }

        $secLabel = number_format($lockSeconds, 1);

        return [
            'success'             => true,
            'effect'              => 'hardlock',
            'output_lines'        => [
                "[HARDLOCK]: OPPONENT TERMINAL FROZEN FOR {$secLabel}s",
            ],
            'opponent_lines'      => [
                "[HARDLOCK]: INPUT FROZEN — {$secLabel}s",
            ],
            'opponent_lock_until' => $match->$lockKey->toIso8601String(),
        ];
    }

    /**
     * Null Byte — re-inject decoy IPs into the opponent's Phase 1 pool.
     * L1: 1 decoy / L2: 2 decoys.
     */
    private function rigCommandNullByte(
        PacketHijackMatch $match,
        string            $targetRole,
        int               $decoys
    ): array {
        $poolKey = "{$targetRole}_ip_pool";
        $pool    = $match->$poolKey ?? [];

        $newDecoys = [];
        while (count($newDecoys) < $decoys) {
            $candidate = $this->generateRigIp();
            if (!in_array($candidate, $pool, true) && !in_array($candidate, $newDecoys, true)) {
                $newDecoys[] = $candidate;
            }
        }

        $pool            = array_merge($pool, $newDecoys);
        shuffle($pool);
        $match->$poolKey = $pool;

        $decoyLabel = $decoys === 1 ? '1 DECOY' : "{$decoys} DECOYS";

        return [
            'success'        => true,
            'effect'         => 'null_byte',
            'output_lines'   => [
                "[NULL BYTE]: {$decoyLabel} INJECTED INTO OPPONENT TRACE BUFFER",
            ],
            'opponent_lines' => [
                '[ALERT]: NULL BYTE DETECTED — TRACE BUFFER CORRUPTED',
            ],
        ];
    }

    /**
     * Static Burst — flood the opponent's terminal with garbage output and
     * lock their input.
     * L1: 2.0s / L2: 3.0s.
     */
    private function rigCommandStaticBurst(
        PacketHijackMatch $match,
        string            $targetRole,
        float             $lockSeconds
    ): array {
        $lockKey  = "{$targetRole}_locked_until";
        $newLock  = Carbon::now()->addSeconds($lockSeconds);
        $existing = $match->$lockKey;

        if ($existing === null || $existing->isPast() || $newLock->gt($existing)) {
            $match->$lockKey = $newLock;
        }

        $secLabel = number_format($lockSeconds, 1);

        $garbage = [
            '01101111 01110010 01110010 00111111 11110110',
            '##ERR_BUFFER_OVERFLOW## 0xDEADBEEF',
            'FAULT /DEV/NULL >> CORE_DUMP_INITIATED',
            str_repeat('%^#@!~', random_int(4, 7)),
            '>> STATIC_BURST_ACTIVE — TERMINAL_FLOOD <<',
        ];

        return [
            'success'             => true,
            'effect'              => 'static_burst',
            'output_lines'        => [
                "[STATIC BURST]: FLOODING OPPONENT TERMINAL FOR {$secLabel}s",
            ],
            'opponent_lines'      => $garbage,
            'opponent_lock_until' => $match->$lockKey->toIso8601String(),
        ];
    }

    /**
     * Phantom Key — add extra ports to the opponent's Phase 2 cascade.
     * Each added port starts at HIGH bias (65–90%) and must be shattered
     * before the exfil port can unlock.
     * L1: +1 port / L2: +2 ports.
     */
    private function rigCommandPhantomKey(
        PacketHijackMatch $match,
        string            $targetRole,
        int               $extraPorts
    ): array {
        $portsKey     = "{$targetRole}_ports";
        $ports        = $match->$portsKey ?? [];
        $presentPorts = array_map('intval', array_column($ports, 'port'));

        // Available catalogue ports not yet in topology, excluding exfil
        $available = array_values(array_filter(
            array_keys(self::PORT_CATALOGUE),
            fn($p) => $p !== self::EXFIL_PORT && !in_array($p, $presentPorts, true)
        ));

        if (empty($available)) {
            return [
                'success'        => true,
                'effect'         => 'phantom_key',
                'output_lines'   => [
                    '[PHANTOM KEY]: PORT INJECTION ATTEMPTED',
                    '[RESULT]: TOPOLOGY SATURATED — NO ADDITIONAL PORTS AVAILABLE',
                ],
                'opponent_lines' => [],
            ];
        }

        $toAdd = array_slice($available, 0, min($extraPorts, count($available)));
        $added = [];

        // Find the exfil port index so we insert phantom ports before it
        $exfilIdx = null;
        foreach ($ports as $i => $entry) {
            if ((int) $entry['port'] === self::EXFIL_PORT) {
                $exfilIdx = $i;
                break;
            }
        }

        foreach ($toAdd as $port) {
            $newEntry = [
                'port'         => $port,
                'service'      => self::PORT_CATALOGUE[$port],
                'bias'         => random_int(65, 90),
                'shattered'    => false,
                'shattered_at' => null,
                'unlocked'     => false,
            ];

            if ($exfilIdx !== null) {
                array_splice($ports, $exfilIdx, 0, [$newEntry]);
                $exfilIdx++;
            } else {
                $ports[] = $newEntry;
            }

            $added[] = "{$port} [{$newEntry['service']}]";
        }

        // Re-lock exfil if it was already unlocked — the new unshattered port
        // means the all-clear condition is no longer valid.
        foreach ($ports as $i => $entry) {
            if ((int) $entry['port'] === self::EXFIL_PORT && $entry['unlocked']) {
                $ports[$i]['unlocked'] = false;
                $ports[$i]['bias']     = 100;
            }
        }

        $match->$portsKey = $ports;

        $countLabel = count($added) === 1 ? '1 PHANTOM PORT' : count($added) . ' PHANTOM PORTS';
        $addedLabel = implode(', ', $added);

        return [
            'success'             => true,
            'effect'              => 'phantom_key',
            'output_lines'        => [
                "[PHANTOM KEY]: {$countLabel} INJECTED INTO OPPONENT CASCADE",
            ],
            'opponent_lines'      => [
                "[ALERT]: TOPOLOGY BREACH — {$countLabel} ADDED TO YOUR CASCADE: {$addedLabel}",
            ],
            'opponent_ports_updated' => true,
        ];
    }

    /**
     * Sector Purge — re-randomise all non-shattered port bias values in the
     * opponent's topology, making any scan data the attacker collected stale.
     * L1: re-randomise to 50–95% / L2: re-randomise to 70–95% (more punishing).
     */
    private function rigCommandSectorPurge(
        PacketHijackMatch $match,
        string            $targetRole,
        int               $level
    ): array {
        $portsKey = "{$targetRole}_ports";
        $ports    = $match->$portsKey ?? [];
        $minBias  = $level >= 2 ? 70 : 50;
        $purged   = 0;

        foreach ($ports as $i => $entry) {
            if ($entry['shattered'] || (int) $entry['port'] === self::EXFIL_PORT) {
                continue;
            }
            $ports[$i]['bias'] = random_int($minBias, 95);
            $purged++;
        }

        $match->$portsKey = $ports;

        if ($purged === 0) {
            return [
                'success'        => true,
                'effect'         => 'sector_purge',
                'output_lines'   => [
                    '[SECTOR PURGE]: NO ACTIVE PORTS TO PURGE',
                ],
                'opponent_lines' => [],
            ];
        }

        return [
            'success'             => true,
            'effect'              => 'sector_purge',
            'output_lines'        => [
                "[SECTOR PURGE]: {$purged} PORT BIAS VALUES RANDOMISED — OPPONENT SCAN DATA IS NOW STALE",
            ],
            'opponent_lines'      => [
                '[ALERT]: SECTOR PURGE DETECTED — ALL PORT SCAN DATA INVALIDATED',
            ],
            'opponent_ports_updated' => true,
        ];
    }

    /**
     * Sector Corrupt — inject false bias readings into N ports in the opponent's
     * topology for 10 seconds. Scan commands will show the fake value instead of
     * the real bias. The fake is inverted: low-bias ports look high, high-bias
     * ports look low — always misleading.
     * L1: 1 port / L2: 2 ports.
     */
    private function rigCommandSectorCorrupt(
        PacketHijackMatch $match,
        string            $targetRole,
        int               $numPorts
    ): array {
        $portsKey      = "{$targetRole}_ports";
        $corruptKey    = "{$targetRole}_corrupt_ports";
        $ports         = $match->$portsKey ?? [];
        $currentCorrupt = $match->$corruptKey ?? [];

        // Prune any entries that have already expired
        $now            = now();
        $currentCorrupt = array_values(array_filter(
            $currentCorrupt,
            fn($e) => Carbon::parse($e['expires_at'])->isFuture()
        ));

        $alreadyCorrupted = array_map('intval', array_column($currentCorrupt, 'port'));

        // Pick non-shattered, non-exfil ports that aren't already corrupted
        $candidates = array_values(array_filter($ports, function ($e) use ($alreadyCorrupted) {
            return !$e['shattered']
                && (int) $e['port'] !== self::EXFIL_PORT
                && !in_array((int) $e['port'], $alreadyCorrupted, true);
        }));

        shuffle($candidates);
        $toCorrupt = array_slice($candidates, 0, min($numPorts, count($candidates)));

        if (empty($toCorrupt)) {
            return [
                'success'        => true,
                'effect'         => 'sector_corrupt',
                'output_lines'   => [
                    '[SECTOR CORRUPT]: NO ELIGIBLE PORTS — CORRUPTION SKIPPED',
                ],
                'opponent_lines' => [],
            ];
        }

        $expiresAt = $now->copy()->addSeconds(10)->toIso8601String();

        foreach ($toCorrupt as $entry) {
            $realBias = (int) $entry['bias'];
            // Invert perceived state: low appears high, high appears low
            $fakeBias = $realBias <= 30
                ? random_int(65, 90)
                : random_int(5, 20);

            $currentCorrupt[] = [
                'port'       => (int) $entry['port'],
                'fake_bias'  => $fakeBias,
                'expires_at' => $expiresAt,
            ];
        }

        $match->$corruptKey = $currentCorrupt;

        $countLabel = count($toCorrupt) === 1 ? '1 PORT' : count($toCorrupt) . ' PORTS';

        return [
            'success'        => true,
            'effect'         => 'sector_corrupt',
            'output_lines'   => [
                "[SECTOR CORRUPT]: FALSE BIAS DATA INJECTED INTO {$countLabel} — ACTIVE FOR 10s",
            ],
            'opponent_lines' => [
                '[ALERT]: SECTOR CORRUPT ACTIVE — PORT SCAN DATA IS UNRELIABLE FOR 10s',
            ],
        ];
    }

    /**
     * Bait — plant a honeypot on one randomly chosen non-shattered port in the
     * opponent's topology. The port will appear to have a very low bias on scan.
     * If the opponent runs exploit on the baited port, they get locked instead
     * of shattering it. The bait is consumed on trigger.
     * L1: 3s lockout / L2: 5s lockout.
     */
    private function rigCommandBait(
        PacketHijackMatch $match,
        string            $targetRole,
        int               $lockSeconds
    ): array {
        $portsKey    = "{$targetRole}_ports";
        $baitKey     = "{$targetRole}_bait_ports";
        $ports       = $match->$portsKey ?? [];
        $currentBait = $match->$baitKey ?? [];

        $alreadyBaited = array_map('intval', array_column($currentBait, 'port'));

        $candidates = array_values(array_filter($ports, function ($e) use ($alreadyBaited) {
            return !$e['shattered']
                && (int) $e['port'] !== self::EXFIL_PORT
                && !in_array((int) $e['port'], $alreadyBaited, true);
        }));

        if (empty($candidates)) {
            return [
                'success'        => true,
                'effect'         => 'bait',
                'output_lines'   => [
                    '[BAIT]: TRAP DEPLOYMENT FAILED — NO ELIGIBLE PORTS AVAILABLE',
                ],
                'opponent_lines' => [],
            ];
        }

        $target   = $candidates[array_rand($candidates)];
        $fakeBias = random_int(5, 15); // Appears very exploitable

        $currentBait[] = [
            'port'         => (int) $target['port'],
            'fake_bias'    => $fakeBias,
            'lock_seconds' => $lockSeconds,
        ];

        $match->$baitKey = $currentBait;

        return [
            'success'        => true,
            'effect'         => 'bait',
            'output_lines'   => [
                "[BAIT]: HONEYPOT DEPLOYED ON PORT {$target['port']} [{$target['service']}] — {$lockSeconds}s LOCKOUT ON TRIGGER",
            ],
            'opponent_lines' => [], // Opponent is not notified (stealth trap)
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve the fake bias override for a port number by checking active
     * sector-corrupt entries (time-limited) and bait traps (persistent until
     * triggered). Returns null if no override is active.
     */
    private function resolveFakeBias(int $port, array $corruptPorts, array $baitPorts): ?int
    {
        $now = now();

        foreach ($corruptPorts as $entry) {
            if ((int) $entry['port'] === $port && $now->isBefore(Carbon::parse($entry['expires_at']))) {
                return (int) $entry['fake_bias'];
            }
        }

        foreach ($baitPorts as $entry) {
            if ((int) $entry['port'] === $port) {
                return (int) $entry['fake_bias'];
            }
        }

        return null;
    }
}
