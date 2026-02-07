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
            $response = Http::timeout(10)
                ->post("{$this->baseUrl}/api/command", [
                    'sessionId' => $sessionId,
                    'command' => $command,
                    'context' => [
                        'currentPath' => $context['currentPath'] ?? '/home/user',
                        'connectedTo' => $context['connectedTo'] ?? null,
                        'variables' => $context['variables'] ?? [],
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return new CommandResult(
                    success: $data['success'] ?? false,
                    output: $data['output'] ?? '',
                    error: $data['error'] ?? null,
                    delayMs: $data['delayMs'] ?? 100,
                    stateChanges: $data['stateChanges'] ?? [],
                );
            }

            Log::error('Game engine request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return CommandResult::error('Engine communication error');

        } catch (\Exception $e) {
            Log::error('Game engine connection failed', [
                'error' => $e->getMessage(),
            ]);

            // Fall back to mock on connection failure
            return CommandResult::error("Engine offline: {$e->getMessage()}");
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
            $response = Http::timeout(5)
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
}
