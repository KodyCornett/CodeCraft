<?php

declare(strict_types=1);

namespace App\Contracts\GameEngine;

/**
 * Interface for communicating with the game engine (Kotlin).
 *
 * Laravel asks "what happens if the player does X?"
 * The engine answers "here is the result."
 */
interface GameEngineInterface
{
    /**
     * Execute a terminal command and return the result.
     *
     * @param string $sessionId Unique session/player identifier
     * @param string $command The raw command string from the player
     * @param array $context Current game state context
     * @return CommandResult The result of command execution
     */
    public function executeCommand(string $sessionId, string $command, array $context = []): CommandResult;

    /**
     * Get the current trace level for a session.
     *
     * @param string $sessionId
     * @return float Trace level between 0.0 (safe) and 1.0 (caught)
     */
    public function getTraceLevel(string $sessionId): float;

    /**
     * Check if a command is valid/available for the current session.
     *
     * @param string $sessionId
     * @param string $command
     * @return bool
     */
    public function isCommandAvailable(string $sessionId, string $command): bool;

    /**
     * Get list of currently available commands for a session.
     *
     * @param string $sessionId
     * @return array<string> List of available command names
     */
    public function getAvailableCommands(string $sessionId): array;
}
