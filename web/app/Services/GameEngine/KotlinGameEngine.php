<?php

declare(strict_types=1);

namespace App\Services\GameEngine;

use App\Contracts\GameEngine\CommandResult;
use App\Contracts\GameEngine\GameEngineInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real game engine implementation that connects to the Kotlin server.
 */
class KotlinGameEngine implements GameEngineInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('game.engine_url', 'http://localhost:8085');
    }

    public function executeCommand(string $sessionId, string $command, array $context = []): CommandResult
    {
        try {
            $variables = $context['variables'] ?? [];

            $payload = [
                'sessionId' => $sessionId,
                'command' => $command,
                'context' => [
                    'currentPath' => $context['currentPath'] ?? '/home/user',
                    'connectedTo' => $context['connectedTo'] ?? null,
                    // Force empty array to be JSON object {} instead of array []
                    'variables' => empty($variables) ? (object) [] : $variables,
                ],
            ];

            Log::debug('Sending command to engine', $payload);

            $response = Http::asJson()
                ->timeout(10)
                ->post("{$this->baseUrl}/api/command", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $output = $data['output'] ?? '';

                // Merge gameEvents into stateChanges for downstream processing
                $stateChanges = $data['stateChanges'] ?? [];
                if (!empty($data['gameEvents'])) {
                    $stateChanges['gameEvents'] = $data['gameEvents'];
                }

                return new CommandResult(
                    success: $data['success'] ?? false,
                    output: $output,
                    lines: $output ? explode("\n", $output) : [],
                    traceIncrease: (float) ($data['traceIncrease'] ?? 0.0),
                    delayMs: $data['delayMs'] ?? 100,
                    stateChanges: $stateChanges,
                    errorType: $data['error'] ?? null,
                );
            }

            Log::error('Game engine request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return CommandResult::error("Engine returned an error (HTTP {$response->status()}).");

        } catch (\Exception $e) {
            Log::error('Game engine connection failed', [
                'error' => $e->getMessage(),
            ]);

            return CommandResult::error('Connection to engine lost. Is the game server running?');
        }
    }

    public function getTraceLevel(string $sessionId): float
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/session/{$sessionId}");

            if ($response->successful()) {
                $data = $response->json();
                return (float) ($data['exposure'] ?? 0.0);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get trace level from engine', [
                'error' => $e->getMessage(),
            ]);
        }

        return 0.0;
    }

    public function isCommandAvailable(string $sessionId, string $command): bool
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/commands/{$sessionId}");

            if ($response->successful()) {
                $data = $response->json();
                $commands = collect($data['commands'] ?? []);
                return $commands->contains('name', $command);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to check command availability', [
                'error' => $e->getMessage(),
            ]);
        }

        // Default to true if we can't check
        return true;
    }

    public function getAvailableCommands(string $sessionId): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/commands/{$sessionId}");

            if ($response->successful()) {
                $data = $response->json();
                return array_column($data['commands'] ?? [], 'name');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get available commands', [
                'error' => $e->getMessage(),
            ]);
        }

        return ['help', 'ls', 'cd', 'cat', 'clear'];
    }

    /**
     * Check if the engine is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(2)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a new session on the engine
     */
    public function createSession(): ?string
    {
        try {
            $response = Http::asJson()
                ->timeout(5)
                ->post("{$this->baseUrl}/api/session", [
                    'action' => 'create',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['sessionId'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to create session on engine', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get active mission status
     */
    public function getActiveMission(string $sessionId): ?array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/mission/{$sessionId}");

            if ($response->successful()) {
                $data = $response->json();
                return $data['active'] ?? false ? $data : null;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get active mission', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Complete active mission
     */
    public function completeMission(string $sessionId): array
    {
        try {
            $response = Http::asJson()
                ->timeout(10)
                ->post("{$this->baseUrl}/api/mission/{$sessionId}/complete");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('Failed to complete mission', [
                'error' => $e->getMessage(),
            ]);
        }

        return ['success' => false, 'output' => 'Failed to complete mission'];
    }

    /**
     * Abandon active mission
     */
    public function abandonMission(string $sessionId): array
    {
        try {
            $response = Http::asJson()
                ->timeout(5)
                ->post("{$this->baseUrl}/api/mission/{$sessionId}/abandon");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('Failed to abandon mission', [
                'error' => $e->getMessage(),
            ]);
        }

        return ['success' => false, 'output' => 'Failed to abandon mission'];
    }

    /**
     * Accept a mission from the board
     */
    public function acceptMission(string $sessionId, string $missionId): array
    {
        try {
            $response = Http::asJson()->timeout(5)
                ->post("{$this->baseUrl}/api/mission/{$sessionId}/{$missionId}/accept");

            if ($response->successful()) {
                return array_merge(['success' => true], $response->json());
            }
        } catch (\Exception $e) {
            Log::warning('Failed to accept mission', ['error' => $e->getMessage()]);
        }

        return ['success' => false, 'output' => 'Failed to accept mission'];
    }

    /**
     * Get available missions
     */
    public function getAvailableMissions(string $sessionId): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/missions/available/{$sessionId}");

            if ($response->successful()) {
                $data = $response->json();
                return $data['missions'] ?? [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get available missions', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Get network state (nodes and connections) for the session
     */
    public function getNetworkState(string $sessionId): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/network-state/{$sessionId}");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get network state from engine', [
                'error' => $e->getMessage(),
            ]);
        }

        return ['nodes' => [], 'connections' => [], 'currentNode' => 'local'];
    }

    /**
     * Get job offers
     */
    public function getJobOffers(string $sessionId): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/jobs/{$sessionId}");

            if ($response->successful()) {
                $data = $response->json();
                return $data['offers'] ?? [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get job offers', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Get Sentinel status (exposure, shield, firewall)
     */
    public function getSentinelStatus(string $sessionId): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/sentinel/status/{$sessionId}");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get sentinel status', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Get Firewall status
     */
    public function getFirewallStatus(string $sessionId): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/firewall/status/{$sessionId}");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get firewall status', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Get downloads list
     */
    public function getDownloads(string $sessionId): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/api/downloads/{$sessionId}");

            if ($response->successful()) {
                $data = $response->json();
                return $data['downloads'] ?? [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get downloads', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }
}
