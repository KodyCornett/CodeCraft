<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Sentinel Service - Monitors player's security status.
 * Tracks exposure level, incoming connections, and active threats.
 */
class SentinelService
{
    /**
     * Exposure thresholds and their effects.
     */
    public const EXPOSURE_LEVELS = [
        'minimal' => ['min' => 0, 'max' => 20, 'label' => 'Minimal', 'color' => 'green'],
        'low' => ['min' => 21, 'max' => 40, 'label' => 'Low', 'color' => 'cyan'],
        'moderate' => ['min' => 41, 'max' => 60, 'label' => 'Moderate', 'color' => 'yellow'],
        'high' => ['min' => 61, 'max' => 80, 'label' => 'High', 'color' => 'orange'],
        'critical' => ['min' => 81, 'max' => 100, 'label' => 'Critical', 'color' => 'red'],
    ];

    /**
     * Get current security status.
     */
    public function getStatus(): array
    {
        // Mock data - in real game, comes from database/game state
        $exposure = $this->getExposure();
        $exposureLevel = $this->getExposureLevel($exposure);

        return [
            'exposure' => $exposure,
            'exposureLevel' => $exposureLevel,
            'maxExposure' => 100,
            'status' => $this->getSystemStatus(),
            'firewallStrength' => $this->getFirewallStrength(),
            'lastScan' => '2 minutes ago',
            'uptime' => '3h 24m',
        ];
    }

    /**
     * Get current exposure percentage.
     */
    public function getExposure(): int
    {
        // Mock - would be calculated from recent actions
        return 34;
    }

    /**
     * Get exposure level details.
     */
    public function getExposureLevel(int $exposure): array
    {
        foreach (self::EXPOSURE_LEVELS as $key => $level) {
            if ($exposure >= $level['min'] && $exposure <= $level['max']) {
                return array_merge(['key' => $key], $level);
            }
        }

        return self::EXPOSURE_LEVELS['critical'];
    }

    /**
     * Get system status string.
     */
    private function getSystemStatus(): string
    {
        // Would be based on active threats
        $threats = $this->getActiveThreats();
        $activeThreats = count(array_filter($threats, fn($t) => $t['status'] === 'active'));

        if ($activeThreats > 0) {
            return 'UNDER ATTACK';
        }

        $incoming = $this->getIncomingConnections();
        $probes = count(array_filter($incoming, fn($c) => $c['type'] === 'probe' || $c['type'] === 'scan'));

        if ($probes > 2) {
            return 'ELEVATED THREAT';
        }

        if ($probes > 0) {
            return 'MONITORING';
        }

        return 'SECURE';
    }

    /**
     * Get firewall strength percentage.
     */
    private function getFirewallStrength(): int
    {
        // Mock - would be based on installed defenses
        return 75;
    }

    /**
     * Get incoming connection attempts.
     */
    public function getIncomingConnections(): array
    {
        // Mock data - would come from game state
        return [
            [
                'id' => 'conn_001',
                'sourceIp' => '192.168.50.10',
                'sourceNode' => 'nova-corp-sec',
                'type' => 'probe',
                'port' => 22,
                'timestamp' => now()->subSeconds(45)->toIso8601String(),
                'timeAgo' => '45s ago',
                'status' => 'detected',
                'threatLevel' => 'low',
            ],
            [
                'id' => 'conn_002',
                'sourceIp' => '10.0.0.15',
                'sourceNode' => 'unknown',
                'type' => 'scan',
                'port' => null,
                'timestamp' => now()->subMinutes(1)->subSeconds(23)->toIso8601String(),
                'timeAgo' => '1m ago',
                'status' => 'detected',
                'threatLevel' => 'low',
            ],
            [
                'id' => 'conn_003',
                'sourceIp' => '172.16.0.8',
                'sourceNode' => 'sec-response-unit',
                'type' => 'intrusion',
                'port' => 443,
                'timestamp' => now()->subSeconds(12)->toIso8601String(),
                'timeAgo' => '12s ago',
                'status' => 'active',
                'threatLevel' => 'critical',
                'progress' => 35,
                'timeRemaining' => 45,
            ],
        ];
    }

    /**
     * Get active threats requiring immediate attention.
     */
    public function getActiveThreats(): array
    {
        $connections = $this->getIncomingConnections();

        return array_values(array_filter($connections, function ($conn) {
            return $conn['status'] === 'active' || $conn['threatLevel'] === 'critical';
        }));
    }

    /**
     * Get security event log.
     */
    public function getEventLog(): array
    {
        return [
            [
                'id' => 'evt_001',
                'type' => 'probe_detected',
                'message' => 'Port probe detected from 192.168.50.10',
                'timestamp' => now()->subSeconds(45)->toIso8601String(),
                'timeAgo' => '45s ago',
                'severity' => 'warning',
            ],
            [
                'id' => 'evt_002',
                'type' => 'scan_detected',
                'message' => 'Network scan detected from 10.0.0.15',
                'timestamp' => now()->subMinutes(1)->subSeconds(23)->toIso8601String(),
                'timeAgo' => '1m ago',
                'severity' => 'warning',
            ],
            [
                'id' => 'evt_003',
                'type' => 'intrusion_attempt',
                'message' => 'INTRUSION ATTEMPT from 172.16.0.8 (sec-response-unit)',
                'timestamp' => now()->subSeconds(12)->toIso8601String(),
                'timeAgo' => '12s ago',
                'severity' => 'critical',
            ],
            [
                'id' => 'evt_004',
                'type' => 'firewall_block',
                'message' => 'Firewall blocked connection from 45.33.32.156',
                'timestamp' => now()->subMinutes(5)->toIso8601String(),
                'timeAgo' => '5m ago',
                'severity' => 'info',
            ],
            [
                'id' => 'evt_005',
                'type' => 'exposure_increase',
                'message' => 'Exposure increased: Failed exploit attempt detected',
                'timestamp' => now()->subMinutes(8)->toIso8601String(),
                'timeAgo' => '8m ago',
                'severity' => 'warning',
            ],
        ];
    }

    /**
     * Attempt to block an incoming connection.
     */
    public function blockConnection(string $connectionId): array
    {
        // Mock - would update game state
        return [
            'success' => true,
            'message' => 'Connection blocked successfully.',
            'exposureChange' => 0,
        ];
    }

    /**
     * Attempt to trace an incoming connection.
     */
    public function traceConnection(string $connectionId): array
    {
        // Mock - would reveal node info
        return [
            'success' => true,
            'nodeId' => 'node_sec_response_01',
            'nodeIp' => '172.16.0.8',
            'nodeName' => 'SecResponse Unit #7',
            'nodeType' => 'security',
            'organization' => 'NovaCorp Security Division',
            'message' => 'Trace complete. Node added to Node Manager.',
        ];
    }

    /**
     * Attempt to counter-hack an attacker.
     */
    public function counterHack(string $connectionId): array
    {
        // Mock - risky action, could backfire
        $success = rand(0, 100) > 40; // 60% success rate

        if ($success) {
            return [
                'success' => true,
                'message' => 'Counter-hack successful! Attacker systems disabled.',
                'exposureChange' => -10,
                'reward' => [
                    'credits' => rand(100, 500),
                    'data' => 'Recovered security protocols',
                ],
            ];
        }

        return [
            'success' => false,
            'message' => 'Counter-hack failed! Your position has been exposed.',
            'exposureChange' => 15,
            'consequence' => 'Attacker has strengthened their intrusion.',
        ];
    }

    /**
     * Format status for terminal display.
     */
    public function formatForTerminal(): string
    {
        $status = $this->getStatus();
        $threats = $this->getActiveThreats();
        $connections = $this->getIncomingConnections();

        $exposureBar = $this->renderProgressBar($status['exposure'], 100, 20);
        $firewallBar = $this->renderProgressBar($status['firewallStrength'], 100, 20);

        $output = "╔══════════════════════════════════════════════════════╗\n";
        $output .= "║  SENTINEL v2.1 - Security Monitor                    ║\n";
        $output .= "╠══════════════════════════════════════════════════════╣\n";
        $output .= sprintf("║  STATUS: %-44s ║\n", $status['status']);
        $output .= sprintf("║  EXPOSURE:  %s %3d%%  ║\n", $exposureBar, $status['exposure']);
        $output .= sprintf("║  FIREWALL:  %s %3d%%  ║\n", $firewallBar, $status['firewallStrength']);
        $output .= "╠══════════════════════════════════════════════════════╣\n";
        $output .= "║  INCOMING CONNECTIONS                                ║\n";
        $output .= "╠══════════════════════════════════════════════════════╣\n";

        if (empty($connections)) {
            $output .= "║  No incoming connections detected                    ║\n";
        } else {
            foreach (array_slice($connections, 0, 5) as $conn) {
                $icon = match ($conn['threatLevel']) {
                    'critical' => '🔴',
                    'high' => '🟠',
                    'medium' => '🟡',
                    default => '⚪',
                };
                $type = strtoupper(str_pad($conn['type'], 10));
                $ip = str_pad($conn['sourceIp'], 15);
                $time = str_pad($conn['timeAgo'], 8);
                $output .= sprintf("║  %s %s %s %s ║\n", $icon, $ip, $type, $time);
            }
        }

        $output .= "╚══════════════════════════════════════════════════════╝\n";

        if (count($threats) > 0) {
            $output .= "\n⚠️  ACTIVE THREATS DETECTED! Open Sentinel for details.";
        }

        return $output;
    }

    /**
     * Render a text-based progress bar.
     */
    private function renderProgressBar(int $value, int $max, int $width): string
    {
        $filled = (int) round(($value / $max) * $width);
        $empty = $width - $filled;

        return '[' . str_repeat('█', $filled) . str_repeat('░', $empty) . ']';
    }
}
