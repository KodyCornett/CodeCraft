<?php

namespace App\Services;

/**
 * PacketHijackPhase2Service — Phase 2 exploit-chain commands.
 *
 * Owns: scan, probe, trace, exploit, breach, and the credential-reveal helper.
 * No HTTP objects, no broadcasting, no model persistence — pure data transforms.
 * State is passed in by the controller and written back via the returned payload.
 */
class PacketHijackPhase2Service
{
    private const EXFIL_PORT = 8080;

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

    private const PORT_SERVICES = [
        21    => ['service' => 'FTP',      'versions' => ['vsftpd 3.0.3', 'ProFTPD 1.3.5', 'Pure-FTPd 1.0.47']],
        22    => ['service' => 'SSH',      'versions' => ['OpenSSH 7.2p1', 'OpenSSH 6.9', 'Dropbear 2019.78']],
        25    => ['service' => 'SMTP',     'versions' => ['Postfix 3.4.13', 'Sendmail 8.15.2', 'Exim 4.92']],
        53    => ['service' => 'DNS',      'versions' => ['BIND 9.16.1', 'Unbound 1.9.4', 'dnsmasq 2.80']],
        80    => ['service' => 'HTTP',     'versions' => ['Apache 2.4.49', 'nginx 1.18.0', 'Apache 2.2.34']],
        443   => ['service' => 'HTTPS',    'versions' => ['TLS 1.3 / nginx', 'TLS 1.2 / Apache', 'TLS 1.3 / Caddy']],
        3306  => ['service' => 'MySQL',    'versions' => ['MySQL 5.6.0', 'MySQL 5.7.32', 'MariaDB 10.3.27']],
        3389  => ['service' => 'RDP',      'versions' => ['RDP 10.0', 'RDP 8.1', 'FreeRDP 2.0']],
        5432  => ['service' => 'Postgres', 'versions' => ['PostgreSQL 13.3', 'PostgreSQL 12.7', 'PostgreSQL 14.1']],
        6379  => ['service' => 'Redis',    'versions' => ['Redis 6.2.6', 'Redis 5.0.14', 'Redis 7.0.0']],
        27017 => ['service' => 'MongoDB',  'versions' => ['MongoDB 4.4.6', 'MongoDB 5.0.3', 'MongoDB 6.0.1']],
    ];

    // =========================================================================
    // Public commands
    // =========================================================================

    /**
     * scan <ip> — opens Phase 2.
     * Returns port numbers and service names only. Same role as netstat in Phase 1.
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
     *
     * Returns ['found' => true,  'port' => int, 'service' => string, 'lines' => array]
     *      or ['found' => false, 'error' => string]
     */
    public function commandProbePort(array &$portPool, int $portNumber, int $targetOs): array
    {
        foreach ($portPool as $i => $p) {
            if ((int) $p['port'] !== $portNumber) continue;

            $portPool[$i]['probed'] = true;

            $svcData = self::PORT_SERVICES[$portNumber] ?? ['service' => 'UNKNOWN', 'versions' => ['1.0']];
            $ver     = $svcData['versions'][array_rand($svcData['versions'])];

            $lines = ["{$svcData['service']} — {$ver}"];

            $flareCount = match (true) {
                $targetOs >= 7 => 7,
                $targetOs >= 4 => 5,
                default        => 3,
            };

            foreach (array_slice($p['flare_lines'], 0, $flareCount) as $line) {
                $lines[] = $line;
            }

            $lines[] = '';
            $lines[] = $p['anomaly'] !== null
                ? 'ANOMALY: ' . $p['anomaly']
                : 'STATUS: NO ANOMALOUS ACTIVITY DETECTED — SERVICE NOMINAL';

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
     * Both ports must be probed first. Confirmed adjacency (port1 → port2) costs
     * no attempt. Reversed direction returns a partial signal at no cost.
     * No correlation consumes one trace attempt.
     *
     * Returns ['confirmed' => bool, 'lines' => array, 'attempts_left' => int]
     */
    public function commandTrace(
        array $portPool,
        array $chain,
        int   $port1Number,
        int   $port2Number,
        int   $traceAttemptsRemaining
    ): array {
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

        $s1 = $p1['service'];
        $s2 = $p2['service'];

        // Correct direction — confirmed, no attempt consumed
        for ($i = 0; $i < count($chain) - 1; $i++) {
            if ((int) $chain[$i] === $port1Number && (int) $chain[$i + 1] === $port2Number) {
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
        }

        // Reversed direction — partial signal, no attempt consumed
        for ($i = 0; $i < count($chain) - 1; $i++) {
            if ((int) $chain[$i] === $port2Number && (int) $chain[$i + 1] === $port1Number) {
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
        }

        // No link — consume one attempt
        $attemptsLeft = max(0, $traceAttemptsRemaining - 1);

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
     * Guards (in order): bait check → port exists → probed → not already shattered
     *   → non-chain dead end → exfil lock → chain order (with overclock bypass).
     * On success: marks port shattered, advances chain progress, reveals credential fragment.
     *
     * Returns [
     *   'success'          => bool,
     *   'port'             => int,
     *   'lines'            => array,
     *   'new_progress'     => int,
     *   'credential_state' => array,
     *   'chain_complete'   => bool,
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
        foreach ($baitPorts as $bait) {
            if ((int) $bait['port'] !== $portNumber) continue;
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

        $service  = $portEntry['service'];
        $category = $portEntry['category'];

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

        if ($portNumber === self::EXFIL_PORT) {
            $nonExfilChain = array_filter($chain, fn($p) => $p !== self::EXFIL_PORT);
            foreach ($nonExfilChain as $cp) {
                $cpEntry = $this->findPort($portPool, $cp);
                if ($cpEntry === null || !$cpEntry['shattered']) {
                    return [
                        'success' => false,
                        'lines'   => [
                            "[EXPLOIT]: TARGETING ALT-HTTP:8080...",
                            "[EXFIL LOCKED]: CHAIN INCOMPLETE — CLEAR ALL CASCADE DEPENDENCIES FIRST",
                        ],
                    ];
                }
            }
        }

        $expectedPort = $chain[$chainProgress] ?? null;

        if ((int) ($expectedPort ?? 0) !== $portNumber) {
            if (!$overclockActive) {
                return [
                    'success' => false,
                    'lines'   => [
                        "[EXPLOIT]: TARGETING {$service}:{$portNumber}...",
                        "[GATE HOLDING]: AUTH LAYER UNRESPONSIVE — NO UPSTREAM SIGNAL DETECTED",
                        "[HINT]: THIS SERVICE MAY REQUIRE A PRIOR DEPENDENCY TO BE CLEARED",
                    ],
                ];
            }

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
        }

        foreach ($portPool as $i => $p) {
            if ((int) $p['port'] === $portNumber) {
                $portPool[$i]['shattered'] = true;
                break;
            }
        }

        $newProgress   = $chainProgress + 1;
        $newCredState  = $this->revealCredentialFragment($credentialState, $newProgress, count($chain));
        $chainComplete = $portNumber === self::EXFIL_PORT;

        $lines = [
            "[EXPLOIT]: TARGETING {$service}:{$portNumber}...",
            "[============================] GATE COLLAPSED",
            "",
            "[CREDENTIAL FRAGMENT EXTRACTED]:",
            "  HOSTNAME : {$newCredState['hostname']}",
            "  OS       : {$newCredState['os']}",
            "",
        ];

        if ($chainComplete) {
            $lines[] = '[EXFIL CHANNEL OPEN] — RUN breach <ip> TO INITIATE CONNECTION';
        } else {
            $nextPort    = $chain[$newProgress] ?? null;
            $nextService = $nextPort ? (self::PORT_CATALOGUE[$nextPort] ?? 'UNKNOWN') : 'UNKNOWN';
            $lines[]     = "[CASCADE]: NEXT DEPENDENCY — {$nextService}:{$nextPort}";
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
     * On success, signals the controller to open the auth prompt.
     */
    public function commandBreachChain(array $portPool, array $chain, string $targetIp, string $inputIp): array
    {
        if ($inputIp !== $targetIp) {
            return [
                'success' => false,
                'lines'   => ['[BREACH]: IP MISMATCH — TARGET SIGNATURE REJECTED'],
            ];
        }

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
            'success'       => true,
            'awaiting_auth' => true,
            'lines'         => [
                "[BREACH]: IP SIGNATURE MATCHED — INITIATING CONNECTION...",
                "[SYSTEM LOGIN REQUIRED]: ENTER CREDENTIALS TO COMPLETE BREACH",
            ],
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Reveal credential hostname/OS fragments progressively as chain ports are shattered.
     * Four fragments spread across chain steps: hostname t2, OS t2, hostname t3, OS t3.
     */
    private function revealCredentialFragment(array $credState, int $chainProgress, int $chainLength): array
    {
        $t1h = $credState['_tier1']          ?? 'SYS';
        $t1o = $credState['_os_tier1']       ?? 'OS';
        $t2h = $credState['_hostname_tier2'] ?? '????';
        $t3h = $credState['_hostname_tier3'] ?? '????';
        $t2o = $credState['_os_tier2']       ?? '???';
        $t3o = $credState['_os_tier3']       ?? '???';

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
            'hostname'          => $hostnameDisplay,
            'os'                => $osDisplay,
            'hostname_t2_shown' => $hostnameT2Revealed,
            'hostname_t3_shown' => $hostnameT3Revealed,
            'os_t2_shown'       => $osTier2Revealed,
            'os_t3_shown'       => $osTier3Revealed,
        ]);
    }

    /**
     * Find a port entry in the pool by port number. Returns null if not found.
     */
    private function findPort(array $portPool, int $portNumber): ?array
    {
        foreach ($portPool as $p) {
            if ((int) $p['port'] === $portNumber) return $p;
        }
        return null;
    }
}
