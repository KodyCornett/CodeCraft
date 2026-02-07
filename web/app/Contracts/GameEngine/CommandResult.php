<?php

declare(strict_types=1);

namespace App\Contracts\GameEngine;

/**
 * Represents the result of a command execution from the game engine.
 */
readonly class CommandResult
{
    public function __construct(
        public bool $success,
        public string $output,
        public array $lines = [],
        public float $traceIncrease = 0.0,
        public int $delayMs = 0,
        public array $stateChanges = [],
        public ?string $errorType = null,
    ) {}

    /**
     * Create a successful command result.
     */
    public static function success(
        string $output,
        array $lines = [],
        float $traceIncrease = 0.0,
        int $delayMs = 100,
        array $stateChanges = [],
    ): self {
        return new self(
            success: true,
            output: $output,
            lines: $lines ?: explode("\n", $output),
            traceIncrease: $traceIncrease,
            delayMs: $delayMs,
            stateChanges: $stateChanges,
        );
    }

    /**
     * Create a failed command result.
     */
    public static function error(
        string $output,
        string $errorType = 'COMMAND_ERROR',
        float $traceIncrease = 0.0,
    ): self {
        return new self(
            success: false,
            output: $output,
            lines: explode("\n", $output),
            traceIncrease: $traceIncrease,
            delayMs: 50,
            errorType: $errorType,
        );
    }

    /**
     * Create an "unknown command" error.
     */
    public static function unknownCommand(string $command): self
    {
        return self::error(
            output: "bash: {$command}: command not found",
            errorType: 'UNKNOWN_COMMAND',
        );
    }
}
