<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Contracts\GameEngine\GameEngineInterface;
use Carbon\Carbon;

/**
 * Sentinel Service - Monitors player's security status.
 * Tracks exposure level, incoming connections, and active threats.
 */
class SentinelService
{
    public function __construct(
        private readonly GameEngineInterface $engine
    ) {}
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
        $sessionId = session()->getId();
        $sentinelData = $this->engine->getSentinelStatus($sessionId);

        \Log::debug('Sentinel: Raw engine response', [
            'sessionId' => $sessionId,
            'dataKeys' => !empty($sentinelData) ? array_keys($sentinelData) : [],
            'exposure' => $sentinelData['exposure'] ?? 'missing',
            'threatCount' => $sentinelData['threatCount'] ?? 'missing',
            'incomingThreatsCount' => isset($sentinelData['incomingThreats']) ? count($sentinelData['incomingThreats']) : 'missing',
        ]);

        if (empty($sentinelData) || !isset($sentinelData['exposure'])) {
            // Fallback if engine is unavailable or invalid response
            \Log::warning('Sentinel: Invalid or empty response from engine', ['data' => $sentinelData]);
            return [
                'exposure' => 0,
                'exposureLevel' => ['key' => 'minimal', 'label' => 'Minimal', 'color' => 'green'],
                'maxExposure' => 100,
                'status' => 'OFFLINE',
                'firewallStrength' => 100,
                'lastScan' => 'never',
                'uptime' => '0h 0m',
                'shield' => ['active' => false, 'secondsRemaining' => 0],
                'counterHackPending' => false,
            ];
        }

        $exposure = (int) round($sentinelData['exposure'] ?? 0);
        $exposureLevel = $this->getExposureLevel($exposure);
        $shieldActive = $sentinelData['shieldActive'] ?? false;
        $shieldExpiresAt = $sentinelData['shieldExpiresAt'] ?? null;

        $shield = ['active' => $shieldActive, 'secondsRemaining' => 0];
        if ($shieldActive && $shieldExpiresAt) {
            $shield['secondsRemaining'] = max(0, (int) (($shieldExpiresAt - (time() * 1000)) / 1000));
        }

        return [
            'exposure' => $exposure,
            'exposureLevel' => $exposureLevel,
            'maxExposure' => 100,
            'status' => $this->getSystemStatusFromExposure($exposure),
            'firewallStrength' => max(0, 100 - $exposure),
            'lastScan' => 'unknown',
            'uptime' => '3h 24m',
            'shield' => $shield,
            'counterHackPending' => false,
        ];
    }

    /**
     * Get current exposure percentage (from Kotlin engine).
     */
    public function getExposure(): int
    {
        $sessionId = session()->getId();
        $sentinelData = $this->engine->getSentinelStatus($sessionId);
        return (int) round($sentinelData['exposure'] ?? 0);
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
     * Get system status string based on exposure level.
     */
    private function getSystemStatusFromExposure(int $exposure): string
    {
        if ($exposure > 80) {
            return 'UNDER ATTACK';
        }

        if ($exposure > 60) {
            return 'ELEVATED THREAT';
        }

        if ($exposure > 40) {
            return 'MONITORING';
        }

        return 'SECURE';
    }

    /**
     * Get incoming threats from Kotlin engine.
     */
    public function getIncomingConnections(): array
    {
        $sessionId = session()->getId();
        $sentinelData = $this->engine->getSentinelStatus($sessionId);

        // Return incoming threats, NOT connection traces
        $threats = $sentinelData['incomingThreats'] ?? [];

        return array_map(function ($threat) {
            $threat['timeAgo'] = isset($threat['timestamp'])
                ? $this->computeTimeAgo($threat['timestamp'])
                : 'unknown';
            return $threat;
        }, $threats);
    }

    /**
     * Get active threats requiring immediate attention.
     */
    public function getActiveThreats(): array
    {
        $threats = $this->getIncomingConnections();

        return array_values(array_filter($threats, function ($threat) {
            // Filter threats that are active or critical severity
            $isActive = ($threat['active'] ?? 'false') === 'true';
            $isCritical = ($threat['severity'] ?? '') === 'critical';
            return $isActive || $isCritical;
        }));
    }

    /**
     * Get security event log from Kotlin engine.
     */
    public function getEventLog(): array
    {
        // TODO: Implement /api/sentinel/events/{sessionId} call
        // For now, return empty array
        return [];
    }

    /**
     * Attempt to block an incoming connection.
     */
    public function blockConnection(string $connectionId): array
    {
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
     * Initiate counter-hack via Kotlin engine.
     */
    public function counterHack(string $connectionId): array
    {
        // TODO: Implement counter-hack via Kotlin engine
        // For now, return placeholder
        return [
            'success' => false,
            'message' => 'Counter-hack not yet implemented for Kotlin engine',
        ];
    }

    /**
     * Format status for terminal display.
     */
    public function formatForTerminal(): string
    {
        $status = $this->getStatus();
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
                $icon = match ($conn['threatLevel'] ?? 'low') {
                    'critical' => '!',
                    'high' => '*',
                    'medium' => '~',
                    default => '-',
                };
                $type = strtoupper(str_pad($conn['type'], 10));
                $ip = str_pad($conn['sourceIp'], 15);
                $time = str_pad($conn['timeAgo'], 8);
                $output .= sprintf("║  [%s] %s %s %s ║\n", $icon, $ip, $type, $time);
            }
        }

        $output .= "╚══════════════════════════════════════════════════════╝";

        return $output;
    }

    /**
     * Compute a human-readable "time ago" string from an ISO timestamp.
     */
    private function computeTimeAgo(string $timestamp): string
    {
        $seconds = now()->diffInSeconds(Carbon::parse($timestamp));

        if ($seconds < 60) {
            return $seconds . 's ago';
        }
        if ($seconds < 3600) {
            return floor($seconds / 60) . 'm ago';
        }

        return floor($seconds / 3600) . 'h ago';
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
