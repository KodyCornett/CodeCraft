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
    private const PHASE2_COMMANDS = ['scan', 'probe', 'validate', 'exploit', 'decode', 'breach'];

    /** Recognised Phase 3 commands. */
    private const PHASE3_COMMANDS = ['ls', 'cd', 'extract'];

    /** Dominant stat → hostname prefix + OS prefix */
    private const STAT_PREFIXES = [
        'cpu'      => ['hostname' => 'CORE',    'os' => 'PROC'],
        'firewall' => ['hostname' => 'WALL',    'os' => 'SHIELD'],
        'os'       => ['hostname' => 'PHANTOM', 'os' => 'GHOST'],
        'storage'  => ['hostname' => 'CACHE',   'os' => 'VAULT'],
        'ram'      => ['hostname' => 'STACK',   'os' => 'HEAP'],
    ];

    /** Tier 2 hostname word pool */
    private const HOSTNAME_WORDS = [
        'CIPHER', 'WRAITH', 'NEXUS', 'DAEMON', 'VECTOR', 'STATIC',
        'PULSE', 'RELAY', 'SIGNAL', 'PRISM', 'FLUX', 'VORTEX',
        'APEX', 'ZERO', 'BYTE', 'NODE', 'GRID', 'MESH',
        'SPIKE', 'TRACE', 'DRIFT', 'SURGE', 'ECHO', 'PHASE',
        'TORQUE', 'RAZOR', 'BLADE', 'COIL', 'WIRE', 'LINK',
    ];

    /** Tier 2 OS version pool */
    private const OS_VERSIONS = [
        '4.2', '11.7', '3.9', '7.1', '2.4', '9.3',
        '6.0', '14.2', '5.8', '8.4', '1.9', '12.1',
    ];

    /** Port service + version strings for banner generation */
    private const PORT_SERVICES = [
        21   => ['service' => 'FTP',   'versions' => ['vsftpd 3.0.3', 'ProFTPD 1.3.5', 'Pure-FTPd 1.0.47']],
        22   => ['service' => 'SSH',   'versions' => ['OpenSSH 7.2p1', 'OpenSSH 6.9', 'Dropbear 2019.78']],
        80   => ['service' => 'HTTP',  'versions' => ['Apache 2.4.49', 'nginx 1.18.0', 'Apache 2.2.34']],
        443  => ['service' => 'HTTPS', 'versions' => ['TLS 1.3 / nginx', 'TLS 1.2 / Apache', 'TLS 1.3 / Caddy']],
        3306 => ['service' => 'MySQL', 'versions' => ['MySQL 5.6.0', 'MySQL 5.7.32', 'MariaDB 10.3.27']],
    ];

    /** Filesystem wallet locations — path segments */
    private const WALLET_LOCATIONS = [
        ['home', 'user', 'wallet'],
        ['home', 'runner', 'wallet'],
        ['var', 'cache', 'data', 'wallet'],
        ['tmp', '.hidden', 'wallet'],
        ['net', 'relay', 'wallet'],
        ['home', 'user', 'documents', 'finance', 'wallet'],
        ['var', 'lib', 'wallet'],
        ['home', 'user', 'data', 'wallet'],
        ['sys', 'net', 'cache', 'wallet'],
        ['tmp', 'session', 'wallet'],
    ];

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

    /**
     * Generate the Phase 2 system fingerprint for one player's side.
     *
     * Derives Tier 1 credential prefixes from the TARGET player's dominant
     * effective stat (read-only — no stat values are modified).
     * Splits OS and hostname each into 3 tiers, hides fragments in port banners.
     *
     * Schema:
     *   hostname: { full, tier1, tier2, tier3, display (fills as found) }
     *   os:       { full, tier1, tier2, tier3, display }
     *   ports: [{
     *     port, service, version, exposure, probed, shattered,
     *     banner_lines: [],     — raw output shown to attacker on probe
     *     fragments: [{value, type (hostname|os), tier (2|3)}]  — hidden in banner
     *   }]
     *   exploit_port: int       — the designated entry port (CRITICAL/HIGH)
     *   validated_hostname: ''  — assembled so far
     *   validated_os: ''
     */
    public function generateFingerprint(PlayerRig $rig, Player $player, array $portTopology): array
    {
        $stats = $this->rigService->effectiveStats($rig, $player);

        // ── Dominant stat → Tier 1 prefixes ──────────────────────────────────
        $statValues = [
            'cpu'      => $stats['cpu']['effective'],
            'firewall' => $stats['firewall']['effective'],
            'os'       => $stats['os']['effective'],
            'storage'  => $stats['storage']['effective'],
            'ram'      => $stats['ram']['effective'],
        ];
        arsort($statValues);
        $dominant = array_key_first($statValues);
        $prefixes = self::STAT_PREFIXES[$dominant];

        // ── Build credential tiers ────────────────────────────────────────────
        $hostnameTier1 = $prefixes['hostname'];
        $hostnameTier2 = self::HOSTNAME_WORDS[array_rand(self::HOSTNAME_WORDS)];
        $hostnameTier3 = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        $osTier1 = $prefixes['os'];
        $osTier2 = self::OS_VERSIONS[array_rand(self::OS_VERSIONS)];
        $osTier3 = strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));

        $fullHostname = "{$hostnameTier1}-{$hostnameTier2}-{$hostnameTier3}";
        $fullOs       = "{$osTier1}-{$osTier2}-{$osTier3}";

        // ── Assign fragments to ports ─────────────────────────────────────────
        // Four fragments: hostname T2, hostname T3, os T2, os T3
        // Each goes into a different port banner. Randomised per match.
        $fragments = [
            ['value' => $hostnameTier2, 'type' => 'hostname', 'tier' => 2],
            ['value' => $hostnameTier3, 'type' => 'hostname', 'tier' => 3],
            ['value' => $osTier2,       'type' => 'os',       'tier' => 2],
            ['value' => $osTier3,       'type' => 'os',       'tier' => 3],
        ];
        shuffle($fragments);

        // ── Build port entries with banners ───────────────────────────────────
        $firewall    = $stats['firewall']['effective'];
        $portEntries = [];
        $frag_idx    = 0;

        // Use non-exfil ports from the topology for fingerprint
        $topologyPorts = array_filter($portTopology, fn($p) => $p['port'] !== self::EXFIL_PORT);
        $topologyPorts = array_values($topologyPorts);

        // Pick exploit port — prefer CRITICAL/HIGH exposure
        $exploitPort = null;

        foreach ($topologyPorts as $i => $tp) {
            $portNum  = $tp['port'];
            $svcData  = self::PORT_SERVICES[$portNum] ?? ['service' => 'UNKNOWN', 'versions' => ['1.0']];
            $version  = $svcData['versions'][array_rand($svcData['versions'])];

            // Exposure rating based on port bias + firewall
            $bias     = (int) $tp['bias'];
            $exposure = $this->biasToExposure($bias, $firewall);

            // Track best exploit port
            if ($exploitPort === null && in_array($exposure, ['CRITICAL', 'HIGH'], true)) {
                $exploitPort = $portNum;
            }

            // Assign one fragment to this port
            $portFragment = $frag_idx < count($fragments) ? $fragments[$frag_idx++] : null;

            // Generate banner lines with fragment hidden among noise
            $bannerLines = $this->generateBanner($portNum, $version, $portFragment, $hostnameTier1, $osTier1);

            $portEntries[] = [
                'port'        => $portNum,
                'service'     => $svcData['service'],
                'version'     => $version,
                'exposure'    => $exposure,
                'probed'      => false,
                'shattered'   => false,
                'banner_lines'=> $bannerLines,
                'fragment'    => $portFragment,  // server-side only, never sent to client
            ];
        }

        // Fallback if no CRITICAL/HIGH found
        if ($exploitPort === null && count($portEntries) > 0) {
            $exploitPort = $portEntries[0]['port'];
        }

        return [
            'hostname' => [
                'full'    => $fullHostname,
                'tier1'   => $hostnameTier1,
                'tier2'   => $hostnameTier2,
                'tier3'   => $hostnameTier3,
                'display' => $hostnameTier1 . '-????-????',
            ],
            'os' => [
                'full'    => $fullOs,
                'tier1'   => $osTier1,
                'tier2'   => $osTier2,
                'tier3'   => $osTier3,
                'display' => $osTier1 . '-????-???',
            ],
            'ports'              => $portEntries,
            'exploit_port'       => $exploitPort,
            'validated_hostname' => '',
            'validated_os'       => '',
        ];
    }

    /**
     * Convert a port's bias value to a human-readable EXPOSURE rating.
     * Firewall stat raises effective resistance.
     */
    private function biasToExposure(int $bias, int $firewall): string
    {
        $effective = $bias + ($firewall * 3);
        if ($effective <= 20)  return 'CRITICAL';
        if ($effective <= 40)  return 'HIGH';
        if ($effective <= 65)  return 'MODERATE';
        if ($effective <= 85)  return 'LOW';
        return 'MINIMAL';
    }

    /**
     * Generate a realistic-looking service banner for a port.
     * Hides the real fragment among noise strings of similar format.
     */
    private function generateBanner(int $port, string $version, ?array $fragment, string $hostPrefix, string $osPrefix): array
    {
        $noise = [
            'SYN-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)),
            'PKT-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)),
            strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)),
            strtoupper(self::HOSTNAME_WORDS[array_rand(self::HOSTNAME_WORDS)]),
        ];

        $lines = match ($port) {
            21 => [
                "220 FTP Service Ready — {$version}",
                "System-ID: " . $noise[0],
                "Auth-Mode: PLAIN",
                "Build-Tag: " . $noise[2],
                "Session-Node: " . ($fragment ? $fragment['value'] : $noise[1]),
                "Transfer-Mode: BINARY",
            ],
            22 => [
                "SSH-2.0-{$version}",
                "Key-Exchange: diffie-hellman-group14-sha256",
                "Node-ID: " . ($fragment ? $fragment['value'] : $noise[0]),
                "Auth-Methods: publickey,password",
                "Cipher: aes128-ctr",
                "Host-Tag: " . $noise[3],
            ],
            80 => [
                "HTTP/1.1 200 OK",
                "Server: {$version}",
                "X-Node-Tag: " . $noise[1],
                "X-Build: " . ($fragment ? $fragment['value'] : $noise[2]),
                "X-Powered-By: FastCGI",
                "Content-Type: text/html",
            ],
            443 => [
                "TLS HANDSHAKE — {$version}",
                "Cipher-Suite: TLS_AES_256_GCM_SHA384",
                "Cert-CN: " . $noise[0],
                "Session-ID: " . ($fragment ? $fragment['value'] : $noise[3]),
                "OCSP-Status: GOOD",
                "Pin-Hash: " . $noise[2],
            ],
            3306 => [
                "MySQL Protocol — {$version}",
                "Auth-Plugin: caching_sha2_password",
                "Server-Tag: " . $noise[2],
                "Build-ID: " . ($fragment ? $fragment['value'] : $noise[0]),
                "Charset: utf8mb4",
                "Status: AUTOCOMMIT",
            ],
            default => [
                "SERVICE RESPONSE — Port {$port}",
                "Version: {$version}",
                "Node: " . ($fragment ? $fragment['value'] : $noise[0]),
            ],
        };

        return $lines;
    }

    /**
     * Generate a Phase 3 filesystem for one player's side.
     * Wallet is placed at a randomised location in the tree.
     *
     * Returns a nested directory tree and the wallet path string.
     */
    public function generateFilesystem(): array
    {
        // Pick a random wallet location
        $walletPath = self::WALLET_LOCATIONS[array_rand(self::WALLET_LOCATIONS)];

        // Build base tree
        $tree = [
            'home' => [
                'user'   => ['logs' => [], 'config' => []],
                'runner' => ['cache' => []],
            ],
            'var'  => [
                'cache' => ['data' => []],
                'lib'   => [],
                'log'   => [],
            ],
            'sys'  => [
                'net'  => ['cache' => []],
                'proc' => [],
            ],
            'tmp'  => [
                '.hidden' => [],
                'session' => [],
            ],
            'net'  => [
                'relay' => [],
                'mesh'  => [],
            ],
        ];

        // Inject wallet at the target path
        $tree = $this->injectWallet($tree, $walletPath);

        return [
            'tree'         => $tree,
            'wallet_path'  => '/' . implode('/', $walletPath),
            'current_path' => '/',
        ];
    }

    /**
     * Recursively inject 'wallet' at the given path segments into the tree.
     */
    private function injectWallet(array $tree, array $pathSegments): array
    {
        if (count($pathSegments) === 1) {
            // Last segment is the wallet itself
            $tree['wallet'] = null;
            return $tree;
        }

        $dir = $pathSegments[0];
        $rest = array_slice($pathSegments, 1);

        if (!isset($tree[$dir])) {
            $tree[$dir] = [];
        }

        $tree[$dir] = $this->injectWallet($tree[$dir], $rest);
        return $tree;
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
        $allCommands = array_merge(self::PHASE1_COMMANDS, self::PHASE2_COMMANDS, self::PHASE3_COMMANDS);

        if (!in_array($command, $allCommands, true)) {
            return ['valid' => false, 'error' => "COMMAND NOT FOUND: {$tokens[0]}"];
        }

        $argCount = count($tokens) - 1;

        $argRequirements = [
            // Phase 1
            'netstat'    => [0, 1],   // netstat --active
            'ping'       => [1, 1],   // ping <ip or partial>
            'traceroute' => [1, 1],   // traceroute <ip>
            'arp'        => [0, 1],   // arp --scan
            'whois'      => [1, 1],   // whois <ip>
            'sniff'      => [0, 1],   // sniff --traffic
            'flush'      => [1, 1],   // flush <ip>
            'inject'     => [1, 1],   // inject <ip>
            // Phase 2
            'scan'       => [1, 1],   // scan <ip>
            'probe'      => [1, 1],   // probe <port>
            'validate'   => [1, 1],   // validate <string>
            'exploit'    => [1, 1],   // exploit <port>
            'decode'     => [1, 1],   // decode <port>
            'breach'     => [2, 2],   // breach <ip> <port>
            // Phase 3
            'ls'         => [0, 1],   // ls (optional arg ignored)
            'cd'         => [1, 1],   // cd <dir> or cd ..
            'extract'    => [0, 1],   // extract (optional target arg ignored)
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
    // Phase 2 — Fingerprint Commands
    // =========================================================================

    /**
     * scan <ip> — opens Phase 2 investigation.
     * Returns port numbers only — no service, version, exposure or fragments.
     * Same role as netstat in Phase 1: populates the board, nothing more.
     */
    public function commandScan(array $fingerprint): array
    {
        return array_map(fn($p) => [
            'port'      => $p['port'],
            'service'   => '???',
            'version'   => '???',
            'exposure'  => '???',
            'probed'    => false,
            'shattered' => $p['shattered'],
        ], $fingerprint['ports']);
    }

    /**
     * probe <port> — fingerprint a specific port.
     * Returns the banner lines (with fragment hidden in noise) and exposure rating.
     * Marks the port as probed in the fingerprint.
     * Never reveals which line contains the fragment — player has to read and validate.
     *
     * Returns ['found' => true, 'port' => array, 'banner' => array]
     *      or ['found' => false, 'error' => string]
     */
    public function commandProbe(array &$fingerprint, int $portNumber): array
    {
        foreach ($fingerprint['ports'] as $i => $p) {
            if ((int) $p['port'] !== $portNumber) continue;

            $fingerprint['ports'][$i]['probed'] = true;

            return [
                'found'    => true,
                'port'     => $portNumber,
                'service'  => $p['service'],
                'version'  => $p['version'],
                'exposure' => $p['exposure'],
                'banner'   => $p['banner_lines'],
            ];
        }

        return ['found' => false, 'error' => "PORT {$portNumber} NOT IN TARGET TOPOLOGY"];
    }

    /**
     * validate <string> — check if a string matches any credential fragment.
     * No penalty for wrong input. Updates the fingerprint display if valid.
     *
     * Returns ['valid' => true, 'type' => string, 'tier' => int, 'updated_display' => array]
     *      or ['valid' => false]
     */
    public function commandValidate(array &$fingerprint, string $input): array
    {
        $upper = strtoupper(trim($input));

        foreach ($fingerprint['ports'] as $p) {
            $frag = $p['fragment'] ?? null;
            if ($frag === null) continue;

            if (strtoupper($frag['value']) !== $upper) continue;

            // Valid fragment found — update the display string
            $type = $frag['type']; // 'hostname' or 'os'
            $tier = $frag['tier']; // 2 or 3

            // Build updated display
            $this->updateFingerprintDisplay($fingerprint, $type, $tier, $frag['value']);

            return [
                'valid'            => true,
                'type'             => $type,
                'tier'             => $tier,
                'value'            => $frag['value'],
                'hostname_display' => $fingerprint['hostname']['display'],
                'os_display'       => $fingerprint['os']['display'],
            ];
        }

        return ['valid' => false];
    }

    /**
     * Update the display string for a credential after a fragment is validated.
     */
    private function updateFingerprintDisplay(array &$fingerprint, string $type, int $tier, string $value): void
    {
        $cred = &$fingerprint[$type];

        if ($tier === 2) {
            // Replace second segment
            $cred['display'] = $cred['tier1'] . '-' . $value . '-' . (isset($cred['tier3_found']) ? $cred['tier3'] : '????');
        } elseif ($tier === 3) {
            $cred['display'] = $cred['tier1'] . '-' . (isset($cred['tier2_found']) ? $cred['tier2'] : '????') . '-' . $value;
            $cred['tier3_found'] = true;
        }

        if ($tier === 2) $cred['tier2_found'] = true;

        // Check if complete
        if (isset($cred['tier2_found']) && isset($cred['tier3_found'])) {
            $cred['display'] = $cred['full'];
        }
    }

    /**
     * Check whether both hostname and OS credentials are fully validated.
     */
    public function fingerprintComplete(array $fingerprint): bool
    {
        $h = $fingerprint['hostname'];
        $o = $fingerprint['os'];
        return ($h['display'] === $h['full']) && ($o['display'] === $o['full']);
    }

    /**
     * exploit <port> — attempt to shatter a port based on exposure vs attacker CPU.
     * CRITICAL/HIGH → direct exploit succeeds.
     * MODERATE → requires at least one decode first.
     * LOW/MINIMAL → requires two decodes / overclock.
     */
    public function commandExploitFingerprint(
        array     &$fingerprint,
        int       $portNumber,
        PlayerRig $rig,
        Player    $player,
        bool      $overclocked = false,
        array     $baitPorts   = []
    ): array {
        // Bait check
        foreach ($baitPorts as $bait) {
            if ((int) $bait['port'] === $portNumber) {
                return ['success' => false, 'baited' => true, 'lock_seconds' => (float) $bait['lock_seconds']];
            }
        }

        foreach ($fingerprint['ports'] as $i => $p) {
            if ((int) $p['port'] !== $portNumber) continue;

            if ($p['shattered']) {
                return ['success' => false, 'error' => "PORT {$portNumber} ALREADY SHATTERED"];
            }

            if (!$p['probed']) {
                return ['success' => false, 'error' => "PORT {$portNumber} NOT PROBED — RUN probe {$portNumber} FIRST"];
            }

            $exposure  = $p['exposure'];
            $threshold = $overclocked ? 'MODERATE' : 'HIGH';

            $exploitable = match ($exposure) {
                'CRITICAL' => true,
                'HIGH'     => true,
                'MODERATE' => $overclocked || ($p['decode_count'] ?? 0) >= 1,
                'LOW'      => $overclocked || ($p['decode_count'] ?? 0) >= 2,
                'MINIMAL'  => $overclocked,
                default    => false,
            };

            if (!$exploitable) {
                $hint = in_array($exposure, ['MODERATE', 'LOW']) ? ' — USE decode FIRST' : ' — PORT TOO HARDENED';
                return ['success' => false, 'error' => "EXPLOIT FAILED: {$exposure} EXPOSURE{$hint}"];
            }

            $fingerprint['ports'][$i]['shattered'] = true;

            return ['success' => true, 'port' => $portNumber, 'fingerprint' => $fingerprint];
        }

        return ['success' => false, 'error' => "PORT {$portNumber} NOT IN TARGET TOPOLOGY"];
    }

    /**
     * decode <port> — reduce resistance on a port, enabling exploit on MODERATE/LOW ports.
     * Increments decode_count on the port entry.
     */
    public function commandDecodeFingerprint(array &$fingerprint, int $portNumber): array
    {
        foreach ($fingerprint['ports'] as $i => $p) {
            if ((int) $p['port'] !== $portNumber) continue;
            if ($p['shattered']) {
                return ['success' => false, 'error' => "PORT {$portNumber} ALREADY SHATTERED"];
            }

            $fingerprint['ports'][$i]['decode_count'] = ($p['decode_count'] ?? 0) + 1;
            $count = $fingerprint['ports'][$i]['decode_count'];

            return [
                'success'      => true,
                'port'         => $portNumber,
                'decode_count' => $count,
                'exposure'     => $p['exposure'],
                'fingerprint'  => $fingerprint,
            ];
        }

        return ['success' => false, 'error' => "PORT {$portNumber} NOT IN TARGET TOPOLOGY"];
    }

    /**
     * breach <ip> <port> — fire exploit payload at the designated port.
     * Validates:
     *   - IP matches the target IP from Phase 1
     *   - Port is shattered in the fingerprint
     *   - Fingerprint is complete (both credentials fully validated)
     *
     * On success: triggers connection sequence → auth prompt.
     * On failure: corrupts 1-2 fragments and ejects player.
     */
    public function commandBreachFingerprint(
        array  $fingerprint,
        string $targetIp,
        string $inputIp,
        int    $inputPort
    ): array {
        if ($inputIp !== $targetIp) {
            return ['success' => false, 'reason' => 'ip_mismatch', 'error' => 'IP MISMATCH — TARGET SIGNATURE REJECTED'];
        }

        // Find the port
        $portEntry = null;
        foreach ($fingerprint['ports'] as $p) {
            if ((int) $p['port'] === $inputPort) { $portEntry = $p; break; }
        }

        if ($portEntry === null) {
            return ['success' => false, 'reason' => 'port_not_found', 'error' => "PORT {$inputPort} NOT IN TARGET TOPOLOGY"];
        }

        if (!$portEntry['shattered']) {
            return ['success' => false, 'reason' => 'port_not_shattered', 'error' => "PORT {$inputPort} NOT SHATTERED — EXPLOIT FIRST"];
        }

        if (!$this->fingerprintComplete($fingerprint)) {
            return ['success' => false, 'reason' => 'incomplete', 'error' => 'SYSTEM FINGERPRINT INCOMPLETE — CONTINUE PROBING'];
        }

        return ['success' => true, 'awaiting_auth' => true];
    }

    /**
     * Authenticate with discovered credentials.
     * Player types the full assembled hostname as username, OS as password.
     *
     * On success: advances to Phase 3.
     * On failure: corrupts fragments based on how wrong the attempt was.
     */
    public function commandAuthenticate(
        array  &$fingerprint,
        string $usernameInput,
        string $passwordInput
    ): array {
        $correctUser = $fingerprint['hostname']['full'];
        $correctPass = $fingerprint['os']['full'];

        if (strtoupper($usernameInput) === strtoupper($correctUser) &&
            strtoupper($passwordInput) === strtoupper($correctPass)) {
            return ['success' => true];
        }

        // Determine how wrong — corrupt fragments accordingly
        $corrupted = $this->corruptFragments($fingerprint, $usernameInput, $passwordInput);

        return [
            'success'   => false,
            'corrupted' => $corrupted,
            'error'     => 'AUTHENTICATION FAILED — CREDENTIALS REJECTED',
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * Corrupt 1-3 fragments on auth failure based on how wrong the attempt was.
     * Returns list of corrupted fragment descriptions.
     */
    private function corruptFragments(array &$fingerprint, string $user, string $pass): array
    {
        $corrupted = [];
        $corruptCount = 1;

        // More wrong = more corruption
        $userMatch = similar_text(strtoupper($user), strtoupper($fingerprint['hostname']['full']));
        $passMatch = similar_text(strtoupper($pass), strtoupper($fingerprint['os']['full']));

        if ($userMatch < 4 && $passMatch < 4) $corruptCount = 3;
        elseif ($userMatch < 6 || $passMatch < 6) $corruptCount = 2;

        // Reset random validated fragments
        $allFragments = [
            ['cred' => 'hostname', 'tier' => 2, 'label' => 'HOSTNAME MID-SEGMENT'],
            ['cred' => 'hostname', 'tier' => 3, 'label' => 'HOSTNAME SUFFIX'],
            ['cred' => 'os',       'tier' => 2, 'label' => 'OS VERSION BUILD'],
            ['cred' => 'os',       'tier' => 3, 'label' => 'OS BUILD HASH'],
        ];

        shuffle($allFragments);
        $toCorrupt = array_slice($allFragments, 0, $corruptCount);

        foreach ($toCorrupt as $frag) {
            $cred = $frag['cred'];
            $tier = $frag['tier'];

            if ($tier === 2) {
                $fingerprint[$cred]['tier2_found'] = false;
                $fingerprint[$cred]['display']     = $fingerprint[$cred]['tier1'] . '-????-' .
                    ($fingerprint[$cred]['tier3_found'] ?? false ? $fingerprint[$cred]['tier3'] : '????');
            } else {
                $fingerprint[$cred]['tier3_found'] = false;
                $fingerprint[$cred]['display']     = $fingerprint[$cred]['tier1'] . '-' .
                    ($fingerprint[$cred]['tier2_found'] ?? false ? $fingerprint[$cred]['tier2'] : '????') . '-????';
            }

            $corrupted[] = $frag['label'];
        }

        return $corrupted;
    }

    // =========================================================================
    // Phase 3 — Filesystem Commands
    // =========================================================================

    /**
     * ls — list contents of the current directory.
     * Returns directory entries at the current path.
     */
    public function commandLs(array $filesystem): array
    {
        $node = $this->navigateToPath($filesystem['tree'], $filesystem['current_path']);
        if ($node === null) {
            return ['success' => false, 'error' => 'DIRECTORY READ ERROR'];
        }

        $entries = [];
        foreach ($node as $name => $contents) {
            $entries[] = [
                'name'     => $name,
                'is_dir'   => is_array($contents),
                'is_wallet'=> $name === 'wallet' && $contents === null,
            ];
        }

        return ['success' => true, 'path' => $filesystem['current_path'], 'entries' => $entries];
    }

    /**
     * cd <dir> — change directory. Supports 'cd ..' to go up one level.
     * Updates current_path in the filesystem object.
     */
    public function commandCd(array &$filesystem, string $dir): array
    {
        $currentPath = $filesystem['current_path'];

        if ($dir === '..') {
            if ($currentPath === '/') {
                return ['success' => false, 'error' => 'ALREADY AT ROOT'];
            }
            $parts       = array_filter(explode('/', $currentPath));
            array_pop($parts);
            $newPath     = '/' . implode('/', $parts);
            $filesystem['current_path'] = $newPath ?: '/';
            return ['success' => true, 'path' => $filesystem['current_path']];
        }

        // Navigate forward
        $targetPath = rtrim($currentPath, '/') . '/' . ltrim($dir, '/');
        $node       = $this->navigateToPath($filesystem['tree'], $targetPath);

        // Special case: wallet is a file (null), not a directory
        $currentNode = $this->navigateToPath($filesystem['tree'], $currentPath);
        if ($currentNode !== null && array_key_exists($dir, $currentNode) && $currentNode[$dir] === null) {
            return ['success' => false, 'error' => "{$dir} IS A FILE — RUN: extract"];
        }

        if ($node === null) {
            return ['success' => false, 'error' => "DIRECTORY NOT FOUND: {$dir}"];
        }

        if (!is_array($node)) {
            return ['success' => false, 'error' => "{$dir} IS NOT A DIRECTORY"];
        }

        $filesystem['current_path'] = $targetPath;
        return ['success' => true, 'path' => $filesystem['current_path']];
    }

    /**
     * extract — steal the wallet from current directory.
     * Only succeeds if wallet exists in current directory.
     */
    public function commandExtract(array $filesystem): array
    {
        $node = $this->navigateToPath($filesystem['tree'], $filesystem['current_path']);
        if ($node === null || !array_key_exists('wallet', $node)) {
            return ['success' => false, 'error' => 'NO WALLET FOUND IN CURRENT DIRECTORY — KEEP LOOKING'];
        }

        return ['success' => true];
    }

    /**
     * Navigate a filesystem tree to a given path string.
     * Returns the node at that path, or null if not found.
     */
    private function navigateToPath(array $tree, string $path): ?array
    {
        if ($path === '/') return $tree;

        $parts = array_filter(explode('/', $path));
        $node  = $tree;

        foreach ($parts as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) return null;
            $node = $node[$part];
        }

        return is_array($node) ? $node : null;
    }

    /**
     * probe port <number> — OLD method kept for rig command compatibility.
     * New fingerprint-based probe is commandProbe() above.
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
                $displayEntry = $fakeBias !== null ? array_merge($entry, ['bias' => $fakeBias]) : $entry;
                $flavor = self::PORT_FLAVOR[$portNumber] ?? ["SERVICE DETECTED ON PORT {$portNumber}", 'UNKNOWN PROTOCOL'];
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
