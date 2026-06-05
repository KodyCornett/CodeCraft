<?php

namespace App\Services;

use App\Constants\PacketHijackConstants;

/**
 * PacketHijackLifecycleService — match initialisation helpers.
 *
 * Owns all pure data-generation methods that seed a new match:
 *   - IP generation (generateRigIp)
 *   - Phase 1 suspect board (generateNodeConnections)
 *   - Phase 2 exploit chain (generateExploitChain)
 *   - Phase 2 port pool (generatePortPool)
 *   - Trace attempt budget (initialTraceAttempts)
 *   - Starting credential state (initialCredentialState)
 *
 * RigService-dependent setup (generatePortTopology, generateFingerprint,
 * generateFilesystem) lives in PacketHijackMatchSetupService.
 *
 * No HTTP objects. No model persistence. Pure data generation.
 */
class PacketHijackLifecycleService
{
    /** Number of suspects on the Phase 1 board (1 real + decoys). */
    private const SUSPECT_COUNT = 14;

    /** The exfil port — always the final link in the exploit chain. */
    private const EXFIL_PORT = 8080;

    /** Full port catalogue keyed by port number. */
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

    // =========================================================================
    // IP Generation
    // =========================================================================

    /**
     * Generate a random RFC-1918 IP address for a player's rig.
     * Each player gets a unique IP at match start; this is the value
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

    // =========================================================================
    // Phase 1 Setup
    // =========================================================================

    /**
     * Generate the Phase 1 suspect board for one player.
     *
     * Creates SUSPECT_COUNT IP objects: one real target, the rest decoys.
     * All attributes are seeded server-side — is_target is NEVER sent to the client.
     *
     * Decoy difficulty scales with the target's OS stat:
     *   OS 1–2 → 0 fast decoys   OS 3–4 → 1–2   OS 5–6 → 3–4   OS 7+ → 5+
     */
    public function generateNodeConnections(string $realIp, int $targetOs): array
    {
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

        $parts       = explode('.', $realIp);
        $targetRange = $parts[0] === '192' ? '192.x' : ($parts[0] === '10' ? '10.x' : '172.x');
        $suspects    = [];

        for ($i = 0; $i < self::SUSPECT_COUNT - 1; $i++) {
            do {
                $ip = $generators[array_rand($generators)]();
            } while (in_array($ip, $usedIps, true));
            $usedIps[] = $ip;

            $ipParts = explode('.', $ip);
            $range   = $ipParts[0] === '192' ? '192.x' : ($ipParts[0] === '10' ? '10.x' : '172.x');

            [$latencyMs, $latencyStatus, $hops, $lastSeen] = $i < $fastDecoys
                ? [random_int(2, 12), 'LIVE', random_int(1, 4), random_int(1, 15)]
                : $this->slowDecoyStats();

            $suspects[] = [
                'ip'                => $ip,
                'latency_ms'        => $latencyMs,
                'latency_status'    => $latencyStatus,
                'hops'              => $hops,
                'network_range'     => $range,
                'last_seen_seconds' => $lastSeen,
                'whois_class'       => $whoisClasses[array_rand($whoisClasses)],
                'whois_redacted'    => (bool) random_int(0, 1),
                'is_target'         => false,
                'flushed'           => false,
            ];
        }

        $chassisClasses = ['MOBILE RIG', 'BREAKER UNIT', 'VAULT CHASSIS', 'GHOST FRAME'];

        $suspects[] = [
            'ip'                => $realIp,
            'latency_ms'        => random_int(2, 8),
            'latency_status'    => 'LIVE',
            'hops'              => random_int(1, 3),
            'network_range'     => $targetRange,
            'last_seen_seconds' => random_int(0, 2),
            'whois_class'       => $chassisClasses[array_rand($chassisClasses)],
            'whois_redacted'    => $targetOs >= 6,
            'is_target'         => true,
            'flushed'           => false,
        ];

        shuffle($suspects);

        return $suspects;
    }

    // =========================================================================
    // Phase 2 Setup
    // =========================================================================

    /**
     * Generate the ordered exploit chain for one player's Phase 2.
     * Always 3 non-exfil ports + 8080. Returns e.g. [6379, 3306, 8080].
     */
    public function generateExploitChain(int $targetFirewall): array
    {
        $eligible = array_keys(array_filter(
            self::PORT_CATALOGUE,
            fn($service, $port) => $port !== self::EXFIL_PORT,
            ARRAY_FILTER_USE_BOTH
        ));

        shuffle($eligible);
        $chain   = array_slice($eligible, 0, 3);
        $chain[] = self::EXFIL_PORT;

        return $chain;
    }

    /**
     * Generate the full port pool for one player's Phase 2 board.
     *
     * Selects 7–9 non-exfil ports (count driven by target FW), ensures all chain
     * ports are included, fills remaining slots with noise, then appends 8080.
     * Category per port: 'chain' | 'dead_end' | 'red_herring'.
     * anomaly and flare_lines are server-side only — stripped before sending to client.
     */
    public function generatePortPool(array $chain, int $targetFirewall, int $targetOs): array
    {
        $totalNonExfil = match (true) {
            $targetFirewall >= 7 => 9,
            $targetFirewall >= 4 => 8,
            default              => 7,
        };

        $chainNonExfil = array_values(array_filter($chain, fn($p) => $p !== self::EXFIL_PORT));

        $eligible = array_keys(array_filter(
            self::PORT_CATALOGUE,
            fn($service, $port) => $port !== self::EXFIL_PORT && !in_array($port, $chainNonExfil, true),
            ARRAY_FILTER_USE_BOTH
        ));
        shuffle($eligible);

        $fillerCount     = $totalNonExfil - count($chainNonExfil);
        $fillers         = array_slice($eligible, 0, $fillerCount);
        $redHerringCount = (int) ceil($fillerCount / 2);
        $redHerringPorts = array_slice($fillers, 0, $redHerringCount);
        $deadEndPorts    = array_slice($fillers, $redHerringCount);
        $osTier          = $targetOs >= 7 ? 'high' : ($targetOs >= 4 ? 'mid' : 'low');
        $ports           = [];

        foreach ($chainNonExfil as $i => $port) {
            $nextPort    = $chain[$i + 1] ?? self::EXFIL_PORT;
            $nextService = self::PORT_CATALOGUE[$nextPort] ?? 'Alt-HTTP';
            $ports[]     = [
                'port'        => $port,
                'service'     => self::PORT_CATALOGUE[$port],
                'category'    => 'chain',
                'probed'      => false,
                'shattered'   => false,
                'anomaly'     => $this->pickChainAnomaly(self::PORT_CATALOGUE[$port], $nextService),
                'flare_lines' => $this->generateFlareLines($port),
            ];
        }

        foreach ($redHerringPorts as $port) {
            $pool    = PacketHijackConstants::REDHERRING_ANOMALIES[$osTier];
            $ports[] = [
                'port'        => $port,
                'service'     => self::PORT_CATALOGUE[$port],
                'category'    => 'red_herring',
                'probed'      => false,
                'shattered'   => false,
                'anomaly'     => $pool[array_rand($pool)],
                'flare_lines' => $this->generateFlareLines($port),
            ];
        }

        foreach ($deadEndPorts as $port) {
            $ports[] = [
                'port'        => $port,
                'service'     => self::PORT_CATALOGUE[$port],
                'category'    => 'dead_end',
                'probed'      => false,
                'shattered'   => false,
                'anomaly'     => null,
                'flare_lines' => $this->generateFlareLines($port),
            ];
        }

        $ports[] = [
            'port'        => self::EXFIL_PORT,
            'service'     => self::PORT_CATALOGUE[self::EXFIL_PORT],
            'category'    => 'chain',
            'probed'      => false,
            'shattered'   => false,
            'anomaly'     => 'EXFILTRATION CHANNEL — LOCKED UNTIL CASCADE COMPLETE',
            'flare_lines' => ['PAYLOAD DELIVERY ROUTE — AWAITING CHAIN UNLOCK'],
        ];

        shuffle($ports);
        usort($ports, fn($a, $b) => ($a['port'] === self::EXFIL_PORT ? 1 : 0) - ($b['port'] === self::EXFIL_PORT ? 1 : 0));

        return $ports;
    }

    public function initialTraceAttempts(int $attackerCpu): int
    {
        return match (true) {
            $attackerCpu >= 7 => 8,
            $attackerCpu >= 4 => 6,
            default           => 4,
        };
    }

    public function initialCredentialState(array $fingerprint): array
    {
        $h = $fingerprint['hostname'] ?? [];
        $o = $fingerprint['os']       ?? [];

        return [
            'hostname'        => ($h['tier1'] ?? 'SYS') . '-????-????',
            'os'              => ($o['tier1'] ?? 'OS')  . '-????-???',
            '_hostname_full'  => $h['full']  ?? '',
            '_os_full'        => $o['full']  ?? '',
            '_hostname_tier2' => $h['tier2'] ?? '',
            '_hostname_tier3' => $h['tier3'] ?? '',
            '_os_tier2'       => $o['tier2'] ?? '',
            '_os_tier3'       => $o['tier3'] ?? '',
            '_tier1'          => $h['tier1'] ?? 'SYS',
            '_os_tier1'       => $o['tier1'] ?? 'OS',
        ];
    }

    private function slowDecoyStats(): array
    {
        return match (random_int(0, 2)) {
            0       => [null,                 'TIMEOUT',  random_int(6, 12), random_int(60, 600)],
            1       => [random_int(150, 450), 'DEGRADED', random_int(5, 10), random_int(30, 300)],
            default => [random_int(20, 80),   'LIVE',     random_int(3, 8),  random_int(10, 120)],
        };
    }

    private function pickChainAnomaly(string $service, string $nextService): string
    {
        $templates = PacketHijackConstants::CHAIN_ANOMALIES[$service] ?? [];

        if (isset($templates[$nextService])) {
            return $templates[$nextService];
        }

        return "SERVICE INTERDEPENDENCY DETECTED — {$nextService} LAYER SHOWS UPSTREAM CORRELATION — TRACE TO CONFIRM";
    }

    private function generateFlareLines(int $port): array
    {
        $pool = PacketHijackConstants::PORT_FLARE[$port] ?? [
            'Protocol: UNKNOWN',
            'Status: ACTIVE',
            'Connections: {n}',
        ];

        shuffle($pool);
        $selected = array_slice($pool, 0, min(6, count($pool)));
        $svcData  = PacketHijackConstants::PORT_SERVICES[$port] ?? ['versions' => ['1.0']];
        $ver      = $svcData['versions'][array_rand($svcData['versions'])];

        return array_map(function (string $line) use ($ver) {
            $line = str_replace('{n}',   (string) random_int(1, 24),     $line);
            $line = str_replace('{n2}',  (string) random_int(100, 999),  $line);
            $line = str_replace('{kb}',  (string) random_int(128, 9999), $line);
            $line = str_replace('{kb2}', (string) random_int(128, 9999), $line);
            $line = str_replace('{ts}',  (string) random_int(4, 7200),   $line);
            $line = str_replace('{ver}', $ver,                            $line);
            return $line;
        }, $selected);
    }
}
