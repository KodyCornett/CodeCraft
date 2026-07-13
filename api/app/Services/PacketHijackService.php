<?php

namespace App\Services;

use App\Constants\PacketHijackConstants;
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

    /**
     * Full port catalogue. Service name keyed by port number.
     * 8080 is always the exfil terminal — never selected as a chain entry point.
     */
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

    /** The exfil port — always the final link in the exploit chain. */
    private const EXFIL_PORT = 8080;

    /** Lock duration in seconds when a player hits a honeypot. */
    private const HONEYPOT_LOCK_SECONDS = 3;

    /** Number of suspect IPs on the Phase 1 board. */
    private const SUSPECT_COUNT = 14;

    /** Recognised Phase 1 commands. */
    private const PHASE1_COMMANDS = ['netstat', 'ping', 'traceroute', 'arp', 'whois', 'sniff', 'flush', 'inject'];

    /** Recognised Phase 2 commands — decode and validate removed. */
    private const PHASE2_COMMANDS = ['scan', 'probe', 'trace', 'exploit', 'breach'];

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


    /**
     * Relational anomaly templates for chain ports.
     * Keyed by [from_service][to_service] — describes the dependency relationship.
     * {next} is replaced with the next service name at generation time.
     */
    private const CHAIN_ANOMALIES = [
        'FTP'      => [
            'SSH'      => 'PLAIN-TEXT CREDENTIAL BLEED — SHARED AUTH TOKEN PATTERN MATCHES ENCRYPTED CHANNEL UPSTREAM',
            'HTTP'     => 'PASSIVE MODE RELAY DETECTED — TRANSFER STREAM CORRELATED WITH HTTP SESSION HANDLER',
            'MySQL'    => 'ANONYMOUS LOGIN PATH EXPOSES DATABASE STAGING AREA — DOWNSTREAM DB RELAY SUSPECTED',
            'Redis'    => 'FILE TRANSFER SESSION TAG CORRELATES WITH IN-MEMORY CACHE ENTRY — UPSTREAM RELAY DETECTED',
            'Postgres' => 'CREDENTIAL STAGING FILE DETECTED — DOWNSTREAM DATABASE AUTH DEPENDENCY SUSPECTED',
            'SMTP'     => 'BOUNCE RELAY PATTERN — MAIL HANDLER SHARES SESSION NAMESPACE WITH FTP DAEMON',
        ],
        'SSH'      => [
            'MySQL'    => 'KEY EXCHANGE DOWNGRADE PATTERN — SESSION CREDENTIAL BLEED TO DATABASE AUTH LAYER SUSPECTED',
            'Redis'    => 'TUNNELLED SESSION DETECTED — FORWARDED PORT CORRELATES WITH IN-MEMORY BROKER',
            'HTTP'     => 'AUTHORIZED KEYS FILE EXPOSES WEB SESSION NAMESPACE — CROSS-SERVICE DEPENDENCY',
            'Postgres' => 'PUBKEY AUTH PLUGIN SHARES CREDENTIAL STORE WITH DOWNSTREAM DATABASE — BLEED VECTOR ACTIVE',
            'FTP'      => 'HOST KEY MISMATCH — DOWNGRADE PATH CORRELATES WITH LEGACY FILE TRANSFER SERVICE',
            'SMTP'     => 'AGENT FORWARDING ACTIVE — MAIL RELAY SESSION SHARES SSH NAMESPACE',
        ],
        'HTTP'     => [
            'MySQL'    => 'SESSION COOKIE NAMESPACE LEAKS INTO DATABASE QUERY HANDLER — UPSTREAM DEPENDENCY DETECTED',
            'Redis'    => 'FASTCGI SESSION BROKER SHARES KEYSPACE WITH IN-MEMORY CACHE — RELAY VECTOR ACTIVE',
            'SSH'      => 'REVERSE PROXY CONFIG EXPOSES BACKEND SHELL — ENCRYPTED CHANNEL DEPENDENCY DETECTED',
            'Postgres' => 'ORM SESSION POOL LEAKS CREDENTIAL HASH — DOWNSTREAM DB AUTH BLEED SUSPECTED',
            'SMTP'     => 'FORM HANDLER SHARES MAIL RELAY NAMESPACE — SESSION TOKEN CORRELATION DETECTED',
            'DNS'      => 'VIRTUAL HOST RESOLUTION MISMATCH — DNS REBINDING PATTERN DETECTED UPSTREAM',
        ],
        'HTTPS'    => [
            'MySQL'    => 'TLS SESSION TICKET REUSE — DATABASE AUTH HANDLER SHARES RESUMPTION KEY',
            'Redis'    => 'TLS CERT PIN MISMATCH — CACHE BROKER SESSION ID BLEEDS THROUGH ENCRYPTED LAYER',
            'HTTP'     => 'CERTIFICATE DOWNGRADE NEGOTIATED — PLAIN HTTP FALLBACK CHANNEL ACTIVE DOWNSTREAM',
            'DNS'      => 'CERT TRANSPARENCY LOG ANOMALY — DNS RESOLVER DEPENDENCY IN VALIDATION CHAIN',
            'SSH'      => 'CLIENT CERT ISSUER MATCHES SSH HOST KEY AUTHORITY — SHARED PKI DEPENDENCY',
        ],
        'MySQL'    => [
            'Redis'    => 'QUERY CACHE ENTRIES LEAK INTO VOLATILE KEYSPACE — IN-MEMORY RELAY DEPENDENCY ACTIVE',
            'HTTP'     => 'STORED PROCEDURE EXPOSES SESSION NAMESPACE SHARED WITH WEB HANDLER',
            'FTP'      => 'DATABASE EXPORT STAGING PATH OVERLAPS WITH FILE TRANSFER DAEMON ROOT',
            'Postgres' => 'CROSS-DATABASE CREDENTIAL SHARE — REPLICATION LINK BRIDGES AUTH STORES',
            'SMTP'     => 'TRIGGER EVENT FIRES MAIL RELAY — SESSION TOKEN CARRIED IN NOTIFICATION PAYLOAD',
        ],
        'Redis'    => [
            'MySQL'    => 'VOLATILE KEYSPACE PATTERN — ACTIVE AUTH HANDSHAKE SESSION RELAYED TO DATABASE LAYER',
            'HTTP'     => 'SESSION BROKER CACHE FEEDS WEB HANDLER — TOKEN REUSE ACROSS SERVICE BOUNDARY',
            'SSH'      => 'PUBSUB CHANNEL LEAKS TUNNEL SESSION KEY — ENCRYPTED SHELL DEPENDENCY UPSTREAM',
            'MongoDB'  => 'KEYSPACE NOTIFICATION PATTERN MATCHES DOCUMENT STORE EVENT STREAM — RELAY ACTIVE',
            'Postgres' => 'CACHE INVALIDATION TIED TO DATABASE WRITE CYCLE — CREDENTIAL WINDOW EXPOSED',
        ],
        'SMTP'     => [
            'DNS'      => 'MX RECORD RESOLUTION ANOMALY — MAIL EXCHANGER CHAINS THROUGH RESOLVER — UPSTREAM DEPENDENCY',
            'HTTP'     => 'RELAY AGENT EXPOSES FORM-HANDLER SESSION — WEB LAYER DEPENDENCY DETECTED',
            'Redis'    => 'DELIVERY QUEUE SERIALISED INTO CACHE LAYER — SESSION TOKEN PRESENT IN PAYLOAD',
            'MySQL'    => 'BOUNCE TABLE SHARES DATABASE AUTH NAMESPACE — DOWNSTREAM DB RELAY SUSPECTED',
        ],
        'DNS'      => [
            'HTTP'     => 'REBINDING ATTACK PATTERN — LOCAL RESOLVER MAPS EXTERNAL HOST TO WEB HANDLER INTERNAL IP',
            'HTTPS'    => 'DNSSEC VALIDATION FAILURE — CERT CHAIN DEPENDENCY ON RESOLVER INTEGRITY BROKEN',
            'SMTP'     => 'PTR RECORD MISMATCH — MAIL RELAY DEPENDS ON RESOLVER STATE FOR AUTH HANDSHAKE',
            'Redis'    => 'DYNAMIC RECORD INJECTION DETECTED — CACHE POISONING VECTOR TO IN-MEMORY BROKER',
        ],
        'Postgres' => [
            'Redis'    => 'LISTEN/NOTIFY CHANNEL PUSHES SESSION EVENTS TO CACHE BROKER — RELAY DEPENDENCY ACTIVE',
            'HTTP'     => 'STORED PROCEDURE EXPOSES WEB SESSION CREDENTIAL — CROSS-LAYER BLEED DETECTED',
            'MySQL'    => 'FOREIGN DATA WRAPPER BRIDGES AUTH STORES — CREDENTIAL REUSE ACROSS DATABASES',
            'SMTP'     => 'DATABASE TRIGGER FIRES MAIL RELAY — SESSION TOKEN EXPOSED IN NOTIFICATION BODY',
        ],
        'MongoDB'  => [
            'Redis'    => 'DOCUMENT CHANGE STREAM FEEDS CACHE BROKER — EVENT PAYLOAD CONTAINS SESSION TOKEN',
            'HTTP'     => 'AGGREGATION PIPELINE LEAKS SESSION NAMESPACE INTO WEB REQUEST HANDLER',
            'MySQL'    => 'CROSS-STORE SYNC JOB SHARES CREDENTIAL FILE — AUTH BLEED BETWEEN DATABASES',
            'Postgres' => 'OPLOG REPLAY EXPOSES WRITE OPERATIONS — DOWNSTREAM DB AUTH DEPENDENCY DETECTED',
        ],
        'RDP'      => [
            'SSH'      => 'DESKTOP SESSION CREDENTIAL FORWARDING — OVERLAPPING AUTH STORE WITH SHELL SERVICE',
            'MySQL'    => 'REMOTE APPLICATION SHARES DATABASE CREDENTIAL FILE — DOWNSTREAM DB RELAY DETECTED',
            'HTTP'     => 'SESSION TOKEN REUSED ACROSS REMOTE DESKTOP AND WEB HANDLER — CROSS-SERVICE BLEED',
        ],
    ];

    /**
     * Red herring anomaly pools keyed by OS tier.
     * OS 1–3 → tier 'low' (obviously vague)
     * OS 4–6 → tier 'mid' (sounds relational but generic)
     * OS 7+  → tier 'high' (closely mimics chain language)
     */
    private const REDHERRING_ANOMALIES = [
        'low' => [
            'MISCONFIGURATION DETECTED — NON-STANDARD PORT CONFIGURATION',
            'SERVICE RUNNING OUTSIDE EXPECTED PARAMETERS',
            'UNUSUAL PROCESS BINDING — NOT EXPLOITABLE',
            'LEGACY PROTOCOL IN USE — NO KNOWN ACTIVE VECTOR',
            'CONFIGURATION DRIFT DETECTED — ISOLATED SERVICE',
        ],
        'mid' => [
            'UPSTREAM DEPENDENCY SUSPECTED — NO ACTIVE SIGNATURE MATCH',
            'INTER-SERVICE COMMUNICATION PATTERN — CORRELATION INCONCLUSIVE',
            'SESSION NAMESPACE OVERLAP — INDEPENDENT SERVICES',
            'AUTH HANDSHAKE ANOMALY — SOURCE UNRESOLVABLE',
            'RELAY PATTERN DETECTED — NO DOWNSTREAM DEPENDENCY CONFIRMED',
            'TOKEN REUSE SUSPECTED — CROSS-SERVICE ORIGIN UNCLEAR',
        ],
        'high' => [
            'CREDENTIAL BLEED PATTERN — SESSION TOKEN ORIGIN AMBIGUOUS — FURTHER CORRELATION REQUIRED',
            'VOLATILE KEYSPACE ACTIVITY — UPSTREAM RELAY POSSIBLE — NO CONFIRMED DEPENDENCY',
            'AUTH PLUGIN MISMATCH — DOWNSTREAM SERVICE UNIDENTIFIED — TRACE TO CONFIRM',
            'SESSION FORWARDING ACTIVE — TARGET SERVICE INDETERMINATE — CROSS-REFERENCE REQUIRED',
            'CERTIFICATE CHAIN ANOMALY — DEPENDENCY VECTOR PRESENT — UNRESOLVED SOURCE',
            'TUNNELLED SESSION DETECTED — DESTINATION ENDPOINT UNCONFIRMED — FURTHER RECON NEEDED',
        ],
    ];

    /**
     * Flare data pools — realistic noise lines per service type for probe banners.
     * Mixed in around the anomaly to make the system feel alive.
     */
    private const PORT_FLARE = [
        21    => [
            'Transfer-Mode: BINARY',
            'Auth-Mode: PLAIN',
            'Passive-Mode: ENABLED',
            'Max-Connections: 50',
            'Idle-Timeout: 300s',
            'Session-Count: {n}',
            'Last-Transfer: {ts}s ago',
            'Bytes-In: {kb}K / Bytes-Out: {kb2}K',
            'Failed-Logins: {n}',
        ],
        22    => [
            'Key-Exchange: diffie-hellman-group14-sha256',
            'Auth-Methods: publickey,password',
            'Cipher: aes128-ctr',
            'Active-Sessions: {n}',
            'Failed-Auth: {n}',
            'Banner-Exchange: ENABLED',
            'Keep-Alive: 60s',
            'Host-Key-Type: RSA-4096',
            'Compression: none',
        ],
        25    => [
            'Queue-Size: {n} messages',
            'Relay-Status: OPEN',
            'TLS-Required: NO',
            'Auth-Methods: PLAIN LOGIN',
            'Bounce-Rate: {n}%',
            'Active-Connections: {n}',
            'Last-Delivery: {ts}s ago',
            'Max-Message-Size: 10MB',
            'Accepted-Domains: {n}',
        ],
        53    => [
            'Recursion: ENABLED',
            'DNSSEC: DISABLED',
            'Query-Rate: {n}/s',
            'Cache-Size: {kb}K entries',
            'Upstream-Resolvers: 2',
            'Response-Time: {n}ms avg',
            'Zone-Transfers: ALLOWED',
            'AXFR-Restricted: NO',
            'Negative-Cache-TTL: 30s',
        ],
        80    => [
            'Server: {ver}',
            'X-Powered-By: FastCGI',
            'Keep-Alive: timeout=5, max=100',
            'Content-Type: text/html',
            'Active-Connections: {n}',
            'Request-Rate: {n}/min',
            'Cache-Control: no-store',
            'Worker-Processes: {n}',
            'Upstream-Timeout: 30s',
        ],
        443   => [
            'Cipher-Suite: TLS_AES_256_GCM_SHA384',
            'OCSP-Status: GOOD',
            'Active-Sessions: {n}',
            'Session-Resumption: ENABLED',
            'HSTS: max-age=31536000',
            'Certificate-Expiry: {n} days',
            'Cert-Transparency: ENABLED',
            'Perfect-Forward-Secrecy: YES',
            'Renegotiation: DISABLED',
        ],
        3306  => [
            'Auth-Plugin: caching_sha2_password',
            'Charset: utf8mb4',
            'Status: AUTOCOMMIT',
            'Active-Connections: {n}',
            'Query-Cache: DISABLED',
            'Max-Allowed-Packet: 64MB',
            'Slow-Query-Log: ENABLED',
            'Uptime: {ts}s',
            'Open-Tables: {n}',
        ],
        3389  => [
            'RDP-Protocol: v10',
            'NLA-Required: NO',
            'Active-Sessions: {n}',
            'Clipboard-Redirect: ENABLED',
            'Drive-Redirect: ENABLED',
            'Encryption: 128-bit RC4',
            'Color-Depth: 32bpp',
            'Idle-Timeout: 900s',
            'Session-Broker: STANDALONE',
        ],
        5432  => [
            'Auth-Method: md5',
            'Max-Connections: 100',
            'Active-Connections: {n}',
            'Shared-Buffers: 128MB',
            'WAL-Level: REPLICA',
            'Logging: ENABLED',
            'SSL: OFF',
            'Extensions: uuid-ossp, pg_stat_statements',
            'Uptime: {ts}s',
        ],
        6379  => [
            'Connected-Clients: {n}',
            'Used-Memory: {kb}K',
            'Keyspace-Hits: {n} / Misses: {n2}',
            'Last-Save: {ts}s ago',
            'Blocked-Clients: 0',
            'Active-Channels: {n}',
            'Persistence: RDB + AOF',
            'Eviction-Policy: allkeys-lru',
            'Uptime: {ts}s',
        ],
        27017 => [
            'Auth: DISABLED',
            'Active-Connections: {n}',
            'Collections: {n}',
            'Storage-Engine: WiredTiger',
            'Oplog-Size: 512MB',
            'Replication: STANDALONE',
            'Journaling: ENABLED',
            'Cache-Size: {kb}MB',
            'Network-Compression: DISABLED',
        ],
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
    private const SELF_BUFF_COMMANDS = ['trace_route', 'overclock', 'mirror_protocol'];

    public function __construct(private readonly RigService $rigService) {}

    /**
     * Reveal credential fragments progressively based on chain progress.
     * Called after each successful exploit in the chain.
     *
     * chainProgress = 1 → first non-exfil port shattered → reveal hostname tier2
     * chainProgress = 2 → second non-exfil port shattered → reveal os tier2
     * chainProgress = chainLength (8080 shattered) → reveal remaining tiers
     *
     * Returns updated credential state.
     */
    public function revealCredentialFragment(array $credState, int $chainProgress, int $chainLength): array
    {
        $t1h  = $credState['_tier1']          ?? 'SYS';
        $t1o  = $credState['_os_tier1']       ?? 'OS';
        $t2h  = $credState['_hostname_tier2'] ?? '????';
        $t3h  = $credState['_hostname_tier3'] ?? '????';
        $t2o  = $credState['_os_tier2']       ?? '???';
        $t3o  = $credState['_os_tier3']       ?? '???';

        // Determine what to reveal based on progress
        // We spread 4 fragments (hostname t2, hostname t3, os t2, os t3) across chain steps
        $hostnameT2Revealed = $chainProgress >= 1;
        $osTier2Revealed    = $chainProgress >= 2;
        $hostnameT3Revealed = $chainProgress >= max(2, $chainLength - 1);
        $osTier3Revealed    = $chainProgress >= $chainLength;

        $hostnameDisplay = $t1h
            . '-' . ($hostnameT2Revealed ? $t2h : '????')
            . '-' . ($hostnameT3Revealed ? $t3h : '????');

        $osDisplay = $t1o
            . '-' . ($osTier2Revealed ? $t2o : '???')
            . '-' . ($osTier3Revealed ? $t3o : '???');

        return array_merge($credState, [
            'hostname'           => $hostnameDisplay,
            'os'                 => $osDisplay,
            'hostname_t2_shown'  => $hostnameT2Revealed,
            'hostname_t3_shown'  => $hostnameT3Revealed,
            'os_t2_shown'        => $osTier2Revealed,
            'os_t3_shown'        => $osTier3Revealed,
        ]);
    }

    // =========================================================================
    // Phase 2 Redesign — Commands
    // =========================================================================

    /**
     * scan <ip> — opens Phase 2.
     * Returns port numbers and service names only — no anomaly, no probed state.
     * Same role as netstat in Phase 1: populate the board, nothing more.
     */
    public function commandScanPorts(array $portPool): array
    {
        return array_map(fn($p) => [
            'port'      => $p['port'],
            'service'   => $p['service'],
            'probed'    => $p['probed'],
            'shattered' => $p['shattered'],
            'is_exfil'  => $p['port'] === self::EXFIL_PORT,
        ], $portPool);
    }

    /**
     * probe <port> — fingerprint a specific port.
     *
     * Returns the flare banner + anomaly line. Marks port as probed.
     * Banner line count scales with target OS: higher OS = more noise lines.
     * Anomaly is always the final line, prefixed 'ANOMALY:'.
     * Dead end ports with null anomaly get a generic non-relational closing line.
     *
     * Returns ['found' => true, 'port' => int, 'service' => string, 'lines' => array]
     *      or ['found' => false, 'error' => string]
     */
    public function commandProbePort(array &$portPool, int $portNumber, int $targetOs): array
    {
        foreach ($portPool as $i => $p) {
            if ((int) $p['port'] !== $portNumber) continue;

            $portPool[$i]['probed'] = true;

            $svcData = PacketHijackConstants::PORT_SERVICES[$portNumber] ?? ['service' => 'UNKNOWN', 'versions' => ['1.0']];
            $ver     = $svcData['versions'][array_rand($svcData['versions'])];

            // Header line
            $lines = ["{$svcData['service']} — {$ver}"];

            // Flare body — OS stat controls how many noise lines precede the anomaly
            $flareCount = match (true) {
                $targetOs >= 7 => 7,
                $targetOs >= 4 => 5,
                default        => 3,
            };
            $flare = array_slice($p['flare_lines'], 0, $flareCount);
            foreach ($flare as $line) {
                $lines[] = $line;
            }

            // Anomaly closer
            if ($p['anomaly'] !== null) {
                $lines[] = '';
                $lines[] = 'ANOMALY: ' . $p['anomaly'];
            } else {
                $lines[] = '';
                $lines[] = 'STATUS: NO ANOMALOUS ACTIVITY DETECTED — SERVICE NOMINAL';
            }

            return [
                'found'   => true,
                'port'    => $portNumber,
                'service' => $p['service'],
                'lines'   => $lines,
            ];
        }

        return ['found' => false, 'error' => "PORT {$portNumber} NOT IN TARGET TOPOLOGY"];
    }

    /**
     * trace <port1> <port2> — test a hypothesized chain relationship.
     *
     * Both ports must be probed first.
     * If the two ports are adjacent in the exploit chain (port1 → port2), confirms
     * the link and reveals directionality.
     * Otherwise returns no-correlation.
     * Always consumes one trace attempt regardless of result.
     *
     * Returns [
     *   'confirmed'   => bool,
     *   'lines'       => array,   — terminal output
     *   'attempts_left' => int,
     * ]
     */
    public function commandTrace(
        array $portPool,
        array $chain,
        int   $port1Number,
        int   $port2Number,
        int   $traceAttemptsRemaining
    ): array {
        // Both ports must be probed
        $p1 = $this->findPort($portPool, $port1Number);
        $p2 = $this->findPort($portPool, $port2Number);

        if ($p1 === null) {
            return ['error' => "PORT {$port1Number} NOT IN TARGET TOPOLOGY — PROBE FIRST"];
        }
        if ($p2 === null) {
            return ['error' => "PORT {$port2Number} NOT IN TARGET TOPOLOGY — PROBE FIRST"];
        }
        if (!$p1['probed']) {
            return ['error' => "PORT {$port1Number} NOT PROBED — RUN probe {$port1Number} FIRST"];
        }
        if (!$p2['probed']) {
            return ['error' => "PORT {$port2Number} NOT PROBED — RUN probe {$port2Number} FIRST"];
        }
        if ($port1Number === $port2Number) {
            return ['error' => "CANNOT TRACE A PORT AGAINST ITSELF"];
        }

        // Check adjacency in chain: correct direction (port1 → port2)
        $confirmed = false;
        for ($i = 0; $i < count($chain) - 1; $i++) {
            if ((int) $chain[$i] === $port1Number && (int) $chain[$i + 1] === $port2Number) {
                $confirmed = true;
                break;
            }
        }

        if ($confirmed) {
            $s1 = $p1['service'];
            $s2 = $p2['service'];
            return [
                'confirmed'     => true,
                'attempts_left' => $traceAttemptsRemaining,
                'lines'         => [
                    "[TRACE]: CROSS-REFERENCING {$s1}:{$port1Number} → {$s2}:{$port2Number}...",
                    "[CONFIRMED]: DEPENDENCY CHAIN VERIFIED",
                    "[VECTOR]: EXPLOIT {$port1Number} FIRST — CASCADE PROPAGATES TO {$port2Number}",
                    "[TRACE ATTEMPTS REMAINING]: {$traceAttemptsRemaining}",
                ],
            ];
        }

        // Check reverse direction (port2 → port1 exists in chain) — partial signal, no attempt consumed
        $reversed = false;
        for ($i = 0; $i < count($chain) - 1; $i++) {
            if ((int) $chain[$i] === $port2Number && (int) $chain[$i + 1] === $port1Number) {
                $reversed = true;
                break;
            }
        }

        if ($reversed) {
            $s1 = $p1['service'];
            $s2 = $p2['service'];
            return [
                'confirmed'     => false,
                'partial'       => true,
                'attempts_left' => $traceAttemptsRemaining,
                'lines'         => [
                    "[TRACE]: CROSS-REFERENCING {$s1}:{$port1Number} → {$s2}:{$port2Number}...",
                    "[PARTIAL]: SIGNAL DETECTED — REFINE PORT DIRECTION",
                    "[TRACE ATTEMPTS REMAINING]: {$traceAttemptsRemaining}",
                ],
            ];
        }

        // No link — consume one attempt
        $attemptsLeft = max(0, $traceAttemptsRemaining - 1);
        $s1 = $p1['service'];
        $s2 = $p2['service'];
        return [
            'confirmed'     => false,
            'partial'       => false,
            'attempts_left' => $attemptsLeft,
            'lines'         => [
                "[TRACE]: CROSS-REFERENCING {$s1}:{$port1Number} → {$s2}:{$port2Number}...",
                "[RESULT]: NO CORRELATED ANOMALY — SERVICES OPERATE INDEPENDENTLY",
                "[TRACE ATTEMPTS REMAINING]: {$attemptsLeft}",
            ],
        ];
    }

    /**
     * exploit <port> — attempt to shatter a port in the chain.
     *
     * Rules:
     *   - Port must be probed first.
     *   - For chain ports: the port must be the current head of the chain
     *     (i.e. all preceding chain ports already shattered).
     *   - Exfil port 8080: only when all other chain ports are shattered.
     *   - Non-chain ports (dead ends / red herrings): always fails with a clue.
     *   - On success: marks port shattered, advances chain progress,
     *     returns credential fragment reveal data.
     *
     * Returns [
     *   'success'          => bool,
     *   'port'             => int,
     *   'lines'            => array,
     *   'new_progress'     => int,      — updated chain_progress (on success)
     *   'credential_state' => array,    — updated credential state (on success)
     *   'chain_complete'   => bool,     — true if 8080 just shattered
     * ]
     */
    public function commandExploitPort(
        array &$portPool,
        array  $chain,
        int    $portNumber,
        int    $chainProgress,
        array  $credentialState,
        array  $baitPorts = [],
        bool   $overclockActive = false
    ): array {
        // Bait check
        foreach ($baitPorts as $bait) {
            if ((int) $bait['port'] === $portNumber) {
                return [
                    'success'      => false,
                    'baited'       => true,
                    'lock_seconds' => (float) $bait['lock_seconds'],
                    'lines'        => [
                        "[EXPLOIT]: TARGETING PORT {$portNumber}...",
                        "[ALERT]: HONEYPOT TRIGGERED — INPUT LOCKED FOR {$bait['lock_seconds']}s",
                    ],
                ];
            }
        }

        $portEntry = $this->findPort($portPool, $portNumber);

        if ($portEntry === null) {
            return ['success' => false, 'error' => "PORT {$portNumber} NOT IN TARGET TOPOLOGY"];
        }

        if (!$portEntry['probed']) {
            return [
                'success' => false,
                'lines'   => [
                    "[EXPLOIT]: TARGETING PORT {$portNumber}...",
                    "[FAILED]: PORT NOT PROBED — RUN probe {$portNumber} FIRST",
                ],
            ];
        }

        if ($portEntry['shattered']) {
            return [
                'success' => false,
                'lines'   => ["[EXPLOIT]: PORT {$portNumber} ALREADY SHATTERED"],
            ];
        }

        $service    = $portEntry['service'];
        $category   = $portEntry['category'];
        $chainLength = count($chain);

        // ── Non-chain port ────────────────────────────────────────────────────
        if ($category !== 'chain') {
            $hint = $category === 'red_herring'
                ? 'NO UPSTREAM SIGNAL DETECTED — THIS SERVICE MAY REQUIRE A PRIOR DEPENDENCY'
                : 'SERVICE INTEGRITY HIGH — NO KNOWN ATTACK VECTOR';

            return [
                'success' => false,
                'lines'   => [
                    "[EXPLOIT]: TARGETING {$service}:{$portNumber}...",
                    "[GATE HOLDING]: AUTH LAYER UNRESPONSIVE",
                    "[HINT]: {$hint}",
                ],
            ];
        }

        // ── Exfil port check ──────────────────────────────────────────────────
        if ($portNumber === self::EXFIL_PORT) {
            $nonExfilChain = array_filter($chain, fn($p) => $p !== self::EXFIL_PORT);
            $allShattered  = true;
            foreach ($nonExfilChain as $cp) {
                $cpEntry = $this->findPort($portPool, $cp);
                if ($cpEntry === null || !$cpEntry['shattered']) {
                    $allShattered = false;
                    break;
                }
            }

            if (!$allShattered) {
                return [
                    'success' => false,
                    'lines'   => [
                        "[EXPLOIT]: TARGETING ALT-HTTP:8080...",
                        "[EXFIL LOCKED]: CHAIN INCOMPLETE — CLEAR ALL CASCADE DEPENDENCIES FIRST",
                    ],
                ];
            }
        }

        // ── Chain order check ─────────────────────────────────────────────────
        $expectedPort = $chain[$chainProgress] ?? null;

        if ((int) ($expectedPort ?? 0) !== $portNumber) {
            // Overclock active — bypass chain order for this hit
            if ($overclockActive) {
                // Verify the port IS somewhere in the remaining chain (not already past)
                $remainingChain = array_slice($chain, $chainProgress);
                if (!in_array($portNumber, $remainingChain, false)) {
                    return [
                        'success' => false,
                        'lines'   => [
                            "[EXPLOIT]: TARGETING {$service}:{$portNumber}...",
                            "[OVERCLOCK]: CHAIN SKIP ACTIVE — PORT NOT IN REMAINING CASCADE",
                        ],
                    ];
                }
                // Allow — fall through to success block (overclock consumed in controller)
            } else {
                return [
                    'success' => false,
                    'lines'   => [
                        "[EXPLOIT]: TARGETING {$service}:{$portNumber}...",
                        "[GATE HOLDING]: AUTH LAYER UNRESPONSIVE — NO UPSTREAM SIGNAL DETECTED",
                        "[HINT]: THIS SERVICE MAY REQUIRE A PRIOR DEPENDENCY TO BE CLEARED",
                    ],
                ];
            }
        }

        // ── SUCCESS ───────────────────────────────────────────────────────────
        // Mark shattered in pool
        foreach ($portPool as $i => $p) {
            if ((int) $p['port'] === $portNumber) {
                $portPool[$i]['shattered'] = true;
                break;
            }
        }

        $newProgress     = $chainProgress + 1;
        $newCredState    = $this->revealCredentialFragment($credentialState, $newProgress, $chainLength);
        $chainComplete   = $portNumber === self::EXFIL_PORT;

        $lines = [
            "[EXPLOIT]: TARGETING {$service}:{$portNumber}...",
            "[============================] GATE COLLAPSED",
            "",
            "[CREDENTIAL FRAGMENT EXTRACTED]:",
            "  HOSTNAME : {$newCredState['hostname']}",
            "  OS       : {$newCredState['os']}",
        ];

        if ($chainComplete) {
            $lines[] = '';
            $lines[] = '[EXFIL CHANNEL OPEN] — RUN breach <ip> TO INITIATE CONNECTION';
        } else {
            $nextPort    = $chain[$newProgress] ?? null;
            $nextService = $nextPort ? (self::PORT_CATALOGUE[$nextPort] ?? 'UNKNOWN') : 'UNKNOWN';
            $lines[] = '';
            $lines[] = "[CASCADE]: NEXT DEPENDENCY — {$nextService}:{$nextPort}";
        }

        return [
            'success'          => true,
            'port'             => $portNumber,
            'new_progress'     => $newProgress,
            'credential_state' => $newCredState,
            'chain_complete'   => $chainComplete,
            'lines'            => $lines,
        ];
    }

    /**
     * breach <ip> — final Phase 2 command.
     *
     * Validates the IP matches the Phase 1 target and the full chain is shattered.
     * On success, opens the auth prompt.
     */
    public function commandBreachChain(array $portPool, array $chain, string $targetIp, string $inputIp): array
    {
        if ($inputIp !== $targetIp) {
            return [
                'success' => false,
                'lines'   => ['[BREACH]: IP MISMATCH — TARGET SIGNATURE REJECTED'],
            ];
        }

        // Verify chain complete
        foreach ($chain as $port) {
            $entry = $this->findPort($portPool, $port);
            if ($entry === null || !$entry['shattered']) {
                return [
                    'success' => false,
                    'lines'   => ['[BREACH]: CHAIN INCOMPLETE — CLEAR ALL DEPENDENCIES FIRST'],
                ];
            }
        }

        return [
            'success'         => true,
            'awaiting_auth'   => true,
            'lines'           => [
                "[BREACH]: IP SIGNATURE MATCHED — INITIATING CONNECTION...",
                "[SYSTEM LOGIN REQUIRED]: ENTER CREDENTIALS TO COMPLETE BREACH",
            ],
        ];
    }

    /**
     * Helper — find a port entry in the pool by port number.
     */
    private function findPort(array $portPool, int $portNumber): ?array
    {
        foreach ($portPool as $p) {
            if ((int) $p['port'] === $portNumber) return $p;
        }
        return null;
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
            'ping'       => [1, 1],   // ping <ip>
            'traceroute' => [1, 1],   // traceroute <ip>
            'arp'        => [0, 1],   // arp --scan
            'whois'      => [1, 1],   // whois <ip>
            'sniff'      => [0, 1],   // sniff --traffic
            'flush'      => [1, 1],   // flush <ip>
            'inject'     => [1, 1],   // inject <ip>
            // Phase 2 — redesigned
            'scan'       => [1, 1],   // scan <ip>
            'probe'      => [1, 1],   // probe <port>
            'trace'      => [2, 2],   // trace <port1> <port2>
            'exploit'    => [1, 1],   // exploit <port>
            'breach'     => [1, 1],   // breach <ip>
            // Phase 3
            'ls'         => [0, 1],   // ls
            'cd'         => [1, 1],   // cd <dir> or cd ..
            'extract'    => [0, 1],   // extract
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
            'trace_route'     => $this->rigCommandTraceRoute($match, $userRole, $level === 1 ? 1 : 2),
            'overclock'       => $this->rigCommandOverclock($match, $userRole),
            'mirror_protocol' => $this->rigCommandMirrorProtocol($match, $userRole),
            'data_spike'      => $this->rigCommandDataSpike($match, $opponentRole, $level === 1 ? 1 : 2),
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
            'trace_route'    => $this->rigCommandTraceRoute($match, $mirrorTarget, $level === 1 ? 1 : 2),
            'overclock'      => $this->rigCommandOverclock($match, $mirrorTarget),
            'data_spike'     => $this->rigCommandDataSpike($match, $mirrorTarget, $level === 1 ? 1 : 2),
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
     * Trace Route — dual-phase command.
     *
     * Phase 1: Reveals the first octet of the target IP, narrowing the search
     *          to one RFC-1918 range without giving away the subnet.
     *
     * Phase 2: Auto-confirms 1 (L1) or 2 (L2) random chain adjacencies — outputs
     *          confirmed port pairs to terminal without consuming trace attempts.
     *
     * The $level is not passed directly into this method; the caller in
     * applyRigCommand passes $userRole. Level is accessed via the match context
     * by the caller — here we read the level from the method signature via
     * an injected parameter approach. To keep the private handler simple, we
     * accept an optional $adjacencies count (default 1).
     */
    private function rigCommandTraceRoute(PacketHijackMatch $match, string $userRole, int $adjacencies = 1): array
    {
        $phase = $match->phaseOf($userRole);

        // ── Phase 2: auto-confirm chain adjacencies ───────────────────────────
        if ($phase === 2) {
            $chain    = $match->exploitChainFor($userRole);
            $portsKey = "{$userRole}_ports";
            $ports    = $match->$portsKey ?? [];

            if (empty($chain) || count($chain) < 2) {
                return [
                    'success'        => true,
                    'effect'         => 'trace_route',
                    'output_lines'   => [
                        '[TRACE ROUTE]: MAPPING EXPLOIT PATH...',
                        '[RESULT]: CHAIN NOT INITIALISED — RUN scan FIRST',
                    ],
                    'opponent_lines' => [],
                ];
            }

            // Build all valid adjacency pairs from the chain
            $allPairs = [];
            for ($i = 0; $i < count($chain) - 1; $i++) {
                $allPairs[] = [$chain[$i], $chain[$i + 1]];
            }

            shuffle($allPairs);
            $toReveal = array_slice($allPairs, 0, min($adjacencies, count($allPairs)));

            $outputLines   = ['[TRACE ROUTE]: MAPPING EXPLOIT PATH...'];
            $confirmedPairs = [];

            foreach ($toReveal as [$p1, $p2]) {
                $svc1 = self::PORT_CATALOGUE[$p1] ?? "PORT {$p1}";
                $svc2 = self::PORT_CATALOGUE[$p2] ?? "PORT {$p2}";
                $outputLines[]   = "[CONFIRMED LINK]: {$p1} [{$svc1}] → {$p2} [{$svc2}]";
                $confirmedPairs[] = [$p1, $p2];
            }

            return [
                'success'          => true,
                'effect'           => 'trace_route',
                'output_lines'     => $outputLines,
                'opponent_lines'   => ['[ALERT]: OPPONENT DEPLOYED TRACE ROUTE — CHAIN PATH ANALYSIS ACTIVE'],
                'confirmed_pairs'  => $confirmedPairs,
            ];
        }

        // ── Phase 1: first-octet IP reveal ────────────────────────────────────
        $targetIpKey = "{$userRole}_target_ip";
        $targetIp    = $match->$targetIpKey;

        if (empty($targetIp)) {
            return [
                'success'        => true,
                'effect'         => 'trace_route',
                'output_lines'   => [
                    '[TRACE ROUTE]: ROUTING PROBE DISPATCHED...',
                    '[RESULT]: NO TARGET LOCKED — COMPLETE RECON FIRST',
                ],
                'opponent_lines' => [],
            ];
        }

        $firstOctet = explode('.', $targetIp)[0];

        return [
            'success'        => true,
            'effect'         => 'trace_route',
            'output_lines'   => [
                '[TRACE ROUTE]: ROUTING PROBE DISPATCHED...',
                "[RESULT]: ORIGIN NETWORK BLOCK RESOLVED — {$firstOctet}.x.x.x",
            ],
            'opponent_lines' => [
                '[ALERT]: OPPONENT DEPLOYED TRACE ROUTE — NETWORK ORIGIN TRIANGULATED',
            ],
        ];
    }

    /**
     * Overclock — grants a chain-skip for the next exploit command.
     * When active, exploit can target ANY chain port regardless of order
     * (bypasses chain-head enforcement for one hit). Consumed on use.
     */
    private function rigCommandOverclock(PacketHijackMatch $match, string $userRole): array
    {
        $key         = "{$userRole}_overclock_active";
        $match->$key = true;

        return [
            'success'        => true,
            'effect'         => 'overclock',
            'output_lines'   => [
                '[OVERCLOCK]: EXPLOIT MODULE OVERCLOCKED — NEXT EXPLOIT BYPASSES CHAIN ORDER',
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
     * Data Spike — offensive, phase-aware attack on the opponent.
     *
     * Phase 1 (opponent in recon): Injects 1–2 fresh decoy IPs into the
     *   opponent's active suspect list, forcing them to investigate more entries.
     *
     * Phase 2 (opponent in exploit chain): Injects 1–2 red-herring ports into
     *   the opponent's port pool, expanding their topology with dead ends.
     *
     * L1: 1 injection / L2: 2 injections.
     */
    private function rigCommandDataSpike(PacketHijackMatch $match, string $targetRole, int $count = 1): array
    {
        $opponentPhase = $match->phaseOf($targetRole);

        // ── Phase 2: inject red-herring ports ────────────────────────────────
        if ($opponentPhase === 2) {
            $portsKey     = "{$targetRole}_ports";
            $ports        = $match->$portsKey ?? [];
            $presentPorts = array_map('intval', array_column($ports, 'port'));

            $available = array_values(array_filter(
                array_keys(self::PORT_CATALOGUE),
                fn($p) => $p !== self::EXFIL_PORT && !in_array($p, $presentPorts, true)
            ));

            if (empty($available)) {
                return [
                    'success'        => true,
                    'effect'         => 'data_spike',
                    'output_lines'   => ['[DATA SPIKE]: TOPOLOGY SATURATED — NO PORTS AVAILABLE TO INJECT'],
                    'opponent_lines' => [],
                ];
            }

            shuffle($available);
            $toAdd     = array_slice($available, 0, min($count, count($available)));
            $exfilIdx  = null;

            foreach ($ports as $i => $entry) {
                if ((int) $entry['port'] === self::EXFIL_PORT) { $exfilIdx = $i; break; }
            }

            $addedLabels = [];
            foreach ($toAdd as $port) {
                $entry = [
                    'port'         => $port,
                    'service'      => self::PORT_CATALOGUE[$port],
                    'bias'         => random_int(30, 65),
                    'category'     => 'dead_end',
                    'shattered'    => false,
                    'shattered_at' => null,
                    'probed'       => false,
                    'unlocked'     => false,
                    'anomaly'      => null,
                    'flare_lines'  => [],
                ];

                if ($exfilIdx !== null) {
                    array_splice($ports, $exfilIdx, 0, [$entry]);
                    $exfilIdx++;
                } else {
                    $ports[] = $entry;
                }

                $addedLabels[] = "{$port} [{$entry['service']}]";
            }

            $match->$portsKey = $ports;

            $countLabel = count($addedLabels) === 1 ? '1 PORT' : count($addedLabels) . ' PORTS';
            $portList   = implode(', ', $addedLabels);

            return [
                'success'                => true,
                'effect'                 => 'data_spike',
                'output_lines'           => ["[DATA SPIKE]: {$countLabel} INJECTED INTO OPPONENT CASCADE — {$portList}"],
                'opponent_lines'         => ["[ALERT]: DATA SPIKE — {$countLabel} ADDED TO YOUR PORT TOPOLOGY: {$portList}"],
                'opponent_ports_updated' => true,
            ];
        }

        // ── Phase 1: inject decoy suspects ────────────────────────────────────
        $suspectsKey = "{$targetRole}_suspects";
        $suspects    = $match->$suspectsKey ?? [];
        $existingIps = array_column($suspects, 'ip');

        $whoisClasses = [
            'STATIC NODE', 'RELAY HUB', 'PROXY ENDPOINT', 'ANONYMOUS RELAY',
            'MESH NODE', 'DARK RELAY', 'TRANSIT HOP', 'EDGE NODE',
        ];
        $generators = [
            fn() => '192.168.' . random_int(0, 255) . '.' . random_int(1, 254),
            fn() => '10.'      . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254),
            fn() => '172.'     . random_int(16, 31)  . '.' . random_int(0, 255) . '.' . random_int(1, 254),
        ];

        $added = 0; $attempts = 0;
        while ($added < $count && $attempts < 50) {
            $attempts++;
            $ip = $generators[array_rand($generators)]();
            if (in_array($ip, $existingIps, true)) continue;

            $ipParts    = explode('.', $ip);
            $range      = $ipParts[0] === '192' ? '192.x' : ($ipParts[0] === '10' ? '10.x' : '172.x');
            $suspects[] = [
                'ip'                => $ip,
                'latency_ms'        => random_int(5, 60),
                'latency_status'    => 'LIVE',
                'hops'              => random_int(1, 5),
                'network_range'     => $range,
                'last_seen_seconds' => random_int(5, 60),
                'whois_class'       => $whoisClasses[array_rand($whoisClasses)],
                'whois_redacted'    => (bool) random_int(0, 1),
                'is_target'         => false,
                'flushed'           => false,
            ];
            $existingIps[] = $ip;
            $added++;
        }

        $match->$suspectsKey = $suspects;

        $countLabel = $added === 1 ? '1 DECOY' : "{$added} DECOYS";

        return [
            'success'                    => true,
            'effect'                     => 'data_spike',
            'output_lines'               => ["[DATA SPIKE]: {$countLabel} INJECTED INTO OPPONENT CASE FILE"],
            'opponent_lines'             => ["[ALERT]: DATA SPIKE — {$countLabel} ADDED TO YOUR SUSPECT LIST"],
            'opponent_suspects_updated'  => true,
        ];
    }

    // ── Attack commands ───────────────────────────────────────────────────────

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
     * Null Byte — inject fresh decoy IPs directly into the opponent's active
     * suspect list. They appear as uninvestigated entries the opponent must
     * work through before they can safely identify the real target.
     * L1: 1 decoy / L2: 2 decoys.
     */
    private function rigCommandNullByte(
        PacketHijackMatch $match,
        string            $targetRole,
        int               $decoys
    ): array {
        $suspectsKey = "{$targetRole}_suspects";
        $suspects    = $match->$suspectsKey ?? [];

        $existingIps = array_column($suspects, 'ip');

        $whoisClasses = [
            'STATIC NODE', 'RELAY HUB', 'PROXY ENDPOINT', 'ANONYMOUS RELAY',
            'MESH NODE', 'DARK RELAY', 'TRANSIT HOP', 'EDGE NODE',
        ];

        $generators = [
            fn() => '192.168.' . random_int(0, 255) . '.' . random_int(1, 254),
            fn() => '10.'      . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254),
            fn() => '172.'     . random_int(16, 31)  . '.' . random_int(0, 255) . '.' . random_int(1, 254),
        ];

        $added = 0;
        $attempts = 0;

        while ($added < $decoys && $attempts < 50) {
            $attempts++;
            $ip = $generators[array_rand($generators)]();
            if (in_array($ip, $existingIps, true)) continue;

            $ipParts = explode('.', $ip);
            $range   = $ipParts[0] === '192' ? '192.x' : ($ipParts[0] === '10' ? '10.x' : '172.x');
            $roll    = random_int(0, 1);

            $suspects[] = [
                'ip'                => $ip,
                'latency_ms'        => $roll === 0 ? random_int(2, 12) : random_int(20, 80),
                'latency_status'    => $roll === 0 ? 'LIVE' : 'LIVE',
                'hops'              => random_int(1, 6),
                'network_range'     => $range,
                'last_seen_seconds' => random_int(2, 30),
                'whois_class'       => $whoisClasses[array_rand($whoisClasses)],
                'whois_redacted'    => (bool) random_int(0, 1),
                'is_target'         => false,
                'flushed'           => false,
            ];

            $existingIps[] = $ip;
            $added++;
        }

        $match->$suspectsKey = $suspects;

        $decoyLabel = $decoys === 1 ? '1 DECOY' : "{$decoys} DECOYS";

        return [
            'success'          => true,
            'effect'           => 'null_byte',
            'output_lines'     => [
                "[NULL BYTE]: {$decoyLabel} INJECTED INTO OPPONENT TRACE BUFFER",
            ],
            'opponent_lines'   => [
                '[ALERT]: NULL BYTE DETECTED — NEW SUSPECT ENTRIES APPEARED IN YOUR CASE FILE',
            ],
            'opponent_suspects_updated' => true,
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
     * Sector Purge — resets up to 2 of the opponent's probed ports back to
     * unprobed, forcing them to re-probe before they can exploit.
     * L1: resets 1 port / L2: resets 2 ports.
     */
    private function rigCommandSectorPurge(
        PacketHijackMatch $match,
        string            $targetRole,
        int               $level
    ): array {
        $portsKey  = "{$targetRole}_ports";
        $ports     = $match->$portsKey ?? [];
        $resetCount = $level >= 2 ? 2 : 1;

        // Find probed, non-shattered, non-exfil ports
        $candidates = [];
        foreach ($ports as $i => $entry) {
            if ($entry['probed'] && !$entry['shattered'] && (int) $entry['port'] !== self::EXFIL_PORT) {
                $candidates[] = $i;
            }
        }

        if (empty($candidates)) {
            return [
                'success'        => true,
                'effect'         => 'sector_purge',
                'output_lines'   => [
                    '[SECTOR PURGE]: NO PROBED PORTS TO CORRUPT',
                ],
                'opponent_lines' => [],
            ];
        }

        shuffle($candidates);
        $toReset  = array_slice($candidates, 0, min($resetCount, count($candidates)));
        $resetted = [];

        foreach ($toReset as $i) {
            $ports[$i]['probed'] = false;
            $resetted[]          = $ports[$i]['port'];
        }

        $match->$portsKey = $ports;

        $portList   = implode(', ', $resetted);
        $countLabel = count($resetted) === 1 ? '1 PORT' : count($resetted) . ' PORTS';

        return [
            'success'                => true,
            'effect'                 => 'sector_purge',
            'output_lines'           => [
                "[SECTOR PURGE]: {$countLabel} WIPED FROM OPPONENT PROBE LOG — {$portList}",
            ],
            'opponent_lines'         => [
                "[ALERT]: SECTOR PURGE — {$countLabel} PROBE DATA DESTROYED: {$portList} — RE-PROBE REQUIRED",
            ],
            'opponent_ports_updated' => true,
        ];
    }

    /**
     * Sector Corrupt — wipes revealed intel from up to 3 suspect IPs in the
     * opponent's Phase 1 case file. Affected suspects revert to uninvestigated
     * state — ping, arp, traceroute, and whois data are all cleared.
     * L1: wipes 2 suspects / L2: wipes 3 suspects.
     */
    private function rigCommandSectorCorrupt(
        PacketHijackMatch $match,
        string            $targetRole,
        int               $numSuspects
    ): array {
        $suspectsKey = "{$targetRole}_suspects";
        $suspects    = $match->$suspectsKey ?? [];

        // Only target suspects that have at least one piece of revealed intel
        // and haven't been flushed (still active in the case file).
        $candidates = [];
        foreach ($suspects as $i => $s) {
            if ($s['flushed']) continue;
            $hasIntel = ($s['_ping_revealed'] ?? false)
                || ($s['_arp_revealed'] ?? false)
                || isset($s['hops'])
                || isset($s['whois_class']);
            if ($hasIntel) $candidates[] = $i;
        }

        if (empty($candidates)) {
            return [
                'success'        => true,
                'effect'         => 'sector_corrupt',
                'output_lines'   => [
                    '[SECTOR CORRUPT]: NO INVESTIGATED SUSPECTS FOUND — NOTHING TO WIPE',
                ],
                'opponent_lines' => [],
            ];
        }

        shuffle($candidates);
        $toWipe = array_slice($candidates, 0, min($numSuspects, count($candidates)));

        foreach ($toWipe as $i) {
            // Clear all revealed intel — reset to bare IP entry
            $suspects[$i]['_ping_revealed']  = false;
            $suspects[$i]['_arp_revealed']   = false;
            // Keep underlying data (so commands can re-reveal it) but hide revealed flags
            // The UI only shows data when the reveal flag is set, so this is sufficient
        }

        $match->$suspectsKey = $suspects;

        $countLabel = count($toWipe) === 1 ? '1 SUSPECT' : count($toWipe) . ' SUSPECTS';

        return [
            'success'                    => true,
            'effect'                     => 'sector_corrupt',
            'output_lines'               => [
                "[SECTOR CORRUPT]: INTEL WIPED ON {$countLabel} IN OPPONENT CASE FILE",
            ],
            'opponent_lines'             => [
                "[ALERT]: SECTOR CORRUPT — INTEL CORRUPTED ON {$countLabel} — RE-INVESTIGATE REQUIRED",
            ],
            'opponent_suspects_updated'  => true,
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
