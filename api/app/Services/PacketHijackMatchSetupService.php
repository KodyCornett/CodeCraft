<?php

namespace App\Services;

use App\Constants\PacketHijackConstants;
use App\Models\CombatChallenge;
use App\Models\PacketHijackMatch;
use App\Models\Player;
use App\Models\PlayerRig;
use Illuminate\Support\Str;

/**
 * PacketHijackMatchSetupService — match creation and RigService-dependent setup generators.
 *
 * Owns:
 *   - createFromChallenge() — full match setup: IPs, suspect boards, topology,
 *                              fingerprints, filesystems, and PacketHijackMatch::create()
 *   - generatePortTopology() — bias-scaled port selection (per-player)
 *   - generateFingerprint()  — credential tier generation + banner injection (per-player)
 *   - generateFilesystem()   — Phase 3 directory tree + wallet placement (per-player)
 *
 * No HTTP objects. All model persistence goes through createFromChallenge().
 */
class PacketHijackMatchSetupService
{
    private const EXFIL_PORT = 8080;

    private const EXPLOIT_THRESHOLD = 25;

    private const PORT_CATALOGUE = [
        21    => 'FTP',
        22    => 'SSH',
        25    => 'SMTP',
        53    => 'DNS',
        80    => 'HTTP',
        443   => 'HTTPS',
        3306  => 'MySQL',
        3389  => 'RDP',
        5432  => 'Postgres',
        6379  => 'Redis',
        27017 => 'MongoDB',
        8080  => 'Alt-HTTP',
    ];

    private const STAT_PREFIXES = [
        'cpu'      => ['hostname' => 'CORE',    'os' => 'PROC'],
        'firewall' => ['hostname' => 'WALL',    'os' => 'SHIELD'],
        'os'       => ['hostname' => 'PHANTOM', 'os' => 'GHOST'],
        'storage'  => ['hostname' => 'CACHE',   'os' => 'VAULT'],
        'ram'      => ['hostname' => 'STACK',   'os' => 'HEAP'],
    ];

    private const HOSTNAME_WORDS = [
        'CIPHER', 'WRAITH', 'NEXUS', 'DAEMON', 'VECTOR', 'STATIC',
        'PULSE',  'RELAY',  'SIGNAL','PRISM',  'FLUX',   'VORTEX',
        'APEX',   'ZERO',   'BYTE',  'NODE',   'GRID',   'MESH',
        'SPIKE',  'TRACE',  'DRIFT', 'SURGE',  'ECHO',   'PHASE',
        'TORQUE', 'RAZOR',  'BLADE', 'COIL',   'WIRE',   'LINK',
    ];

    private const OS_VERSIONS = [
        '4.2', '11.7', '3.9', '7.1', '2.4',  '9.3',
        '6.0', '14.2', '5.8', '8.4', '1.9', '12.1',
    ];

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

    public function __construct(
        private readonly RigService                   $rigService,
        private readonly PacketHijackLifecycleService $lifecycleService,
    ) {}

    // =========================================================================
    // Match Creation
    // =========================================================================

    /**
     * Build and persist a new PacketHijackMatch from a CombatChallenge.
     *
     * Generates unique rig IPs, suspect boards, port topologies, fingerprints,
     * and filesystems for both players, then creates the match record.
     *
     * Called from CombatChallengeController::accept() — controller owns
     * broadcasts and HTTP responses; this method owns all setup data work.
     */
    public function createFromChallenge(CombatChallenge $challenge): PacketHijackMatch
    {
        $challenger = Player::with(['rig.chassis', 'playerPeripherals.peripheral'])
            ->find($challenge->challenger_id);
        $defender = Player::with(['rig.chassis', 'playerPeripherals.peripheral'])
            ->find($challenge->target_id);

        // Generate unique rig IPs — the value each player must locate in Phase 1.
        $challengerRigIp = $this->lifecycleService->generateRigIp();
        $defenderRigIp   = $this->lifecycleService->generateRigIp();
        while ($defenderRigIp === $challengerRigIp) {
            $defenderRigIp = $this->lifecycleService->generateRigIp();
        }

        $challengerRig = $challenger?->rig;
        $defenderRig   = $defender?->rig;

        $challengerStats = $challengerRig ? $this->rigService->effectiveStats($challengerRig, $challenger) : null;
        $defenderStats   = $defenderRig   ? $this->rigService->effectiveStats($defenderRig,   $defender)   : null;
        $challengerOs    = (int) ($challengerStats['os']['effective'] ?? 1);
        $defenderOs      = (int) ($defenderStats['os']['effective']   ?? 1);

        // Suspect boards: challenger hunts defenderRigIp (seeded by defender's OS), vice versa.
        $challengerSuspects = $this->lifecycleService->generateNodeConnections($defenderRigIp,   $defenderOs);
        $defenderSuspects   = $this->lifecycleService->generateNodeConnections($challengerRigIp, $challengerOs);

        $challengerRig = $challengerRig ?? $this->rigService->getRigForPlayer($challenger);
        $defenderRig   = $defenderRig   ?? $this->rigService->getRigForPlayer($defender);

        $challengerPorts = $challengerRig && $challenger
            ? $this->generatePortTopology($challengerRig, $challenger)
            : [];

        $defenderPorts = $defenderRig && $defender
            ? $this->generatePortTopology($defenderRig, $defender)
            : [];

        // Fingerprint: challenger attacks defender's system, defender attacks challenger's.
        $challengerFingerprint = ($defenderRig && $defender)
            ? $this->generateFingerprint($defenderRig, $defender, $defenderPorts)
            : [];

        $defenderFingerprint = ($challengerRig && $challenger)
            ? $this->generateFingerprint($challengerRig, $challenger, $challengerPorts)
            : [];

        $challengerFilesystem = $this->generateFilesystem();
        $defenderFilesystem   = $this->generateFilesystem();

        return PacketHijackMatch::create([
            'id'                     => (string) Str::uuid(),
            'challenger_id'          => $challenge->challenger_id,
            'defender_id'            => $challenge->target_id,
            'status'                 => 'phase1',
            'challenger_target_ip'   => $defenderRigIp,
            'defender_target_ip'     => $challengerRigIp,
            'challenger_suspects'    => $challengerSuspects,
            'defender_suspects'      => $defenderSuspects,
            'challenger_ports'       => $challengerPorts,
            'challenger_fingerprint' => $challengerFingerprint,
            'challenger_filesystem'  => $challengerFilesystem,
            'defender_ports'         => $defenderPorts,
            'defender_fingerprint'   => $defenderFingerprint,
            'defender_filesystem'    => $defenderFilesystem,
            'challenger_phase'       => 1,
            'defender_phase'         => 1,
            'started_at'             => now(),
            'expires_at'             => now()->addMinutes(15),
        ]);
    }

    // =========================================================================
    // Port Topology
    // =========================================================================

    /**
     * Generate the Phase 2 port topology for one player's match side.
     *
     * Selects 4 ports from the catalogue (excluding 8080). One port gets a LOW
     * bias (8–18%) making it exploitable from the start; the rest get HIGH bias
     * (70–95%). The defending player's Firewall stat raises all bias values.
     */
    public function generatePortTopology(PlayerRig $rig, Player $player): array
    {
        $stats    = $this->rigService->effectiveStats($rig, $player);
        $firewall = $stats['firewall']['effective'];

        $catalogue   = array_filter(
            self::PORT_CATALOGUE,
            fn($service, $port) => $port !== self::EXFIL_PORT,
            ARRAY_FILTER_USE_BOTH
        );
        $portNumbers = array_keys($catalogue);
        shuffle($portNumbers);
        $selected = array_slice($portNumbers, 0, 4);
        $lowIndex = array_rand($selected);
        $ports    = [];

        foreach ($selected as $i => $port) {
            $bias = $i === $lowIndex
                ? min(random_int(8, 18)  + min($firewall * 3, 10), self::EXPLOIT_THRESHOLD - 1)
                : min(random_int(70, 90) + min($firewall * 3, 8),  98);

            $ports[] = [
                'port'         => $port,
                'service'      => self::PORT_CATALOGUE[$port],
                'bias'         => $bias,
                'shattered'    => false,
                'shattered_at' => null,
                'unlocked'     => false,
            ];
        }

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
    // Fingerprint
    // =========================================================================

    /**
     * Generate the Phase 2 system fingerprint for one player's side.
     *
     * Derives Tier 1 credential prefixes from the target player's dominant
     * effective stat. Splits hostname and OS into 3 tiers and hides fragments
     * inside port probe banners. The client never sees the raw fragment values.
     */
    public function generateFingerprint(PlayerRig $rig, Player $player, array $portTopology): array
    {
        $stats      = $this->rigService->effectiveStats($rig, $player);
        $firewall   = $stats['firewall']['effective'];

        $statValues = [
            'cpu'      => $stats['cpu']['effective'],
            'firewall' => $firewall,
            'os'       => $stats['os']['effective'],
            'storage'  => $stats['storage']['effective'],
            'ram'      => $stats['ram']['effective'],
        ];
        arsort($statValues);
        $prefixes = self::STAT_PREFIXES[array_key_first($statValues)];

        $hostnameTier1 = $prefixes['hostname'];
        $hostnameTier2 = self::HOSTNAME_WORDS[array_rand(self::HOSTNAME_WORDS)];
        $hostnameTier3 = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $osTier1       = $prefixes['os'];
        $osTier2       = self::OS_VERSIONS[array_rand(self::OS_VERSIONS)];
        $osTier3       = strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));

        $fragments = [
            ['value' => $hostnameTier2, 'type' => 'hostname', 'tier' => 2],
            ['value' => $hostnameTier3, 'type' => 'hostname', 'tier' => 3],
            ['value' => $osTier2,       'type' => 'os',       'tier' => 2],
            ['value' => $osTier3,       'type' => 'os',       'tier' => 3],
        ];
        shuffle($fragments);

        $topologyPorts = array_values(array_filter($portTopology, fn($p) => $p['port'] !== self::EXFIL_PORT));
        $exploitPort   = null;
        $portEntries   = [];
        $fragIdx       = 0;

        foreach ($topologyPorts as $tp) {
            $portNum  = $tp['port'];
            $svcData  = PacketHijackConstants::PORT_SERVICES[$portNum] ?? ['service' => 'UNKNOWN', 'versions' => ['1.0']];
            $version  = $svcData['versions'][array_rand($svcData['versions'])];
            $exposure = $this->biasToExposure((int) $tp['bias'], $firewall);

            if ($exploitPort === null && in_array($exposure, ['CRITICAL', 'HIGH'], true)) {
                $exploitPort = $portNum;
            }

            $portFragment  = $fragIdx < count($fragments) ? $fragments[$fragIdx++] : null;
            $portEntries[] = [
                'port'         => $portNum,
                'service'      => $svcData['service'],
                'version'      => $version,
                'exposure'     => $exposure,
                'probed'       => false,
                'shattered'    => false,
                'banner_lines' => $this->generateBanner($portNum, $version, $portFragment, $hostnameTier1, $osTier1),
                'fragment'     => $portFragment,
            ];
        }

        if ($exploitPort === null && count($portEntries) > 0) {
            $exploitPort = $portEntries[0]['port'];
        }

        return [
            'hostname'           => [
                'full'    => "{$hostnameTier1}-{$hostnameTier2}-{$hostnameTier3}",
                'tier1'   => $hostnameTier1,
                'tier2'   => $hostnameTier2,
                'tier3'   => $hostnameTier3,
                'display' => $hostnameTier1 . '-????-????',
            ],
            'os'                 => [
                'full'    => "{$osTier1}-{$osTier2}-{$osTier3}",
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

    // =========================================================================
    // Filesystem
    // =========================================================================

    /**
     * Generate a fingerprint for the practice target using fixed easy stats.
     * Skips the real rig dependency so TutorialController needs no RigService.
     *
     * Returns the same nested structure as generateFingerprint() so that
     * initialCredentialState() can read hostname.full / os.full correctly.
     */
    public function generatePracticeFingerprint(): array
    {
        // Fixed low-difficulty target: FW 1 (short chain), OS 2 (short banners).
        $prefixes      = self::STAT_PREFIXES['os'];   // arbitrary deterministic prefix
        $tier1h        = $prefixes['hostname'];
        $tier1o        = $prefixes['os'];
        $hostnameTier2 = self::HOSTNAME_WORDS[array_rand(self::HOSTNAME_WORDS)];
        $hostnameTier3 = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $osTier2       = self::OS_VERSIONS[array_rand(self::OS_VERSIONS)];
        $osTier3       = strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));

        return [
            'hostname' => [
                'full'    => "{$tier1h}-{$hostnameTier2}-{$hostnameTier3}",
                'tier1'   => $tier1h,
                'tier2'   => $hostnameTier2,
                'tier3'   => $hostnameTier3,
                'display' => "{$tier1h}-????-????",
            ],
            'os' => [
                'full'    => "{$tier1o}-{$osTier2}-{$osTier3}",
                'tier1'   => $tier1o,
                'tier2'   => $osTier2,
                'tier3'   => $osTier3,
                'display' => "{$tier1o}-???-???",
            ],
        ];
    }

    /**
     * Generate the Phase 3 filesystem tree for one player's side.
     * Wallet is placed at a randomised path within the tree.
     */
    public function generateFilesystem(): array
    {
        $walletPath = self::WALLET_LOCATIONS[array_rand(self::WALLET_LOCATIONS)];

        $tree = [
            'home' => ['user' => ['logs' => [], 'config' => []], 'runner' => ['cache' => []]],
            'var'  => ['cache' => ['data' => []], 'lib' => [], 'log' => []],
            'sys'  => ['net' => ['cache' => []], 'proc' => []],
            'tmp'  => ['.hidden' => [], 'session' => []],
            'net'  => ['relay' => [], 'mesh' => []],
        ];

        return [
            'tree'         => $this->injectWallet($tree, $walletPath),
            'wallet_path'  => '/' . implode('/', $walletPath),
            'current_path' => '/',
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function biasToExposure(int $bias, int $firewall): string
    {
        $effective = $bias + ($firewall * 3);
        if ($effective <= 20) return 'CRITICAL';
        if ($effective <= 40) return 'HIGH';
        if ($effective <= 65) return 'MODERATE';
        if ($effective <= 85) return 'LOW';
        return 'MINIMAL';
    }

    private function generateBanner(int $port, string $version, ?array $fragment, string $hostPrefix, string $osPrefix): array
    {
        $noise = [
            'SYN-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)),
            'PKT-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)),
            strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)),
            strtoupper(self::HOSTNAME_WORDS[array_rand(self::HOSTNAME_WORDS)]),
        ];

        return match ($port) {
            21  => ["220 FTP Service Ready — {$version}", "System-ID: {$noise[0]}", "Auth-Mode: PLAIN", "Build-Tag: {$noise[2]}", "Session-Node: " . ($fragment ? $fragment['value'] : $noise[1]), "Transfer-Mode: BINARY"],
            22  => ["SSH-2.0-{$version}", "Key-Exchange: diffie-hellman-group14-sha256", "Node-ID: " . ($fragment ? $fragment['value'] : $noise[0]), "Auth-Methods: publickey,password", "Cipher: aes128-ctr", "Host-Tag: {$noise[3]}"],
            80  => ["HTTP/1.1 200 OK", "Server: {$version}", "X-Node-Tag: {$noise[1]}", "X-Build: " . ($fragment ? $fragment['value'] : $noise[2]), "X-Powered-By: FastCGI", "Content-Type: text/html"],
            443 => ["TLS HANDSHAKE — {$version}", "Cipher-Suite: TLS_AES_256_GCM_SHA384", "Cert-CN: {$noise[0]}", "Session-ID: " . ($fragment ? $fragment['value'] : $noise[3]), "OCSP-Status: GOOD", "Pin-Hash: {$noise[2]}"],
            3306=> ["MySQL Protocol — {$version}", "Auth-Plugin: caching_sha2_password", "Server-Tag: {$noise[2]}", "Build-ID: " . ($fragment ? $fragment['value'] : $noise[0]), "Charset: utf8mb4", "Status: AUTOCOMMIT"],
            default => ["SERVICE RESPONSE — Port {$port}", "Version: {$version}", "Node: " . ($fragment ? $fragment['value'] : $noise[0])],
        };
    }

    private function injectWallet(array $tree, array $pathSegments): array
    {
        if (count($pathSegments) === 1) {
            $tree['wallet'] = null;
            return $tree;
        }

        $dir = $pathSegments[0];

        if (!isset($tree[$dir])) {
            $tree[$dir] = [];
        }

        $tree[$dir] = $this->injectWallet($tree[$dir], array_slice($pathSegments, 1));
        return $tree;
    }
}
