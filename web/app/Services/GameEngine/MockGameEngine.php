<?php

declare(strict_types=1);

namespace App\Services\GameEngine;

use App\Contracts\GameEngine\CommandResult;
use App\Contracts\GameEngine\GameEngineInterface;
use App\Services\Messaging\MessageService;
use App\Services\Security\SentinelService;

/**
 * Mock implementation of the game engine for UI development.
 *
 * This will be replaced by the real Kotlin engine WebSocket connection
 * when Phase 2 is complete.
 */
class MockGameEngine implements GameEngineInterface
{
    private MessageService $messageService;
    private SentinelService $sentinelService;

    public function __construct()
    {
        $this->messageService = new MessageService();
        $this->sentinelService = new SentinelService();
    }

    /**
     * Simulated trace levels per session (in-memory, resets on request).
     */
    private array $traceLevels = [];

    /**
     * Base commands always available.
     */
    private const BASE_COMMANDS = [
        'help',
        'ls',
        'cd',
        'cat',
        'clear',
        'mail',
        'sentinel',
        'status',
    ];

    /**
     * Commands unlocked through discovery.
     */
    private const DISCOVERABLE_COMMANDS = [
        'connect',
        'scan',
        'disconnect',
        'probe',
        'exploit',
    ];

    public function executeCommand(string $sessionId, string $command, array $context = []): CommandResult
    {
        $parts = explode(' ', trim($command));
        $verb = strtolower($parts[0] ?? '');
        $args = array_slice($parts, 1);

        return match ($verb) {
            'help' => $this->handleHelp($sessionId),
            'ls' => $this->handleLs($args, $context),
            'cd' => $this->handleCd($args, $context),
            'cat' => $this->handleCat($args, $context),
            'clear' => CommandResult::success('', delayMs: 0),
            'connect' => $this->handleConnect($sessionId, $args),
            'scan' => $this->handleScan($sessionId, $args),
            'disconnect' => $this->handleDisconnect($sessionId),
            'mail', 'inbox' => $this->handleMail($args),
            'sentinel', 'status' => $this->handleSentinel(),
            default => CommandResult::unknownCommand($verb),
        };
    }

    public function getTraceLevel(string $sessionId): float
    {
        return $this->traceLevels[$sessionId] ?? 0.0;
    }

    public function isCommandAvailable(string $sessionId, string $command): bool
    {
        return in_array($command, $this->getAvailableCommands($sessionId), true);
    }

    public function getAvailableCommands(string $sessionId): array
    {
        // For now, return all base commands
        // In the real engine, this would check player progression
        return self::BASE_COMMANDS;
    }

    private function handleHelp(string $sessionId): CommandResult
    {
        $output = <<<HELP
AVAILABLE COMMANDS
─────────────────────────────────────────
  help          Show this help message
  ls            List directory contents
  cd <dir>      Change directory
  cat <file>    Display file contents
  clear         Clear the terminal
  mail          Access your messages
  sentinel      Security status monitor
  scan <host>   Scan target for open ports
  connect <ip>  Connect to remote system
  disconnect    Close active connection

Type 'mail help' for mail command options.
Type 'sentinel' or 'status' for security info.
HELP;

        return CommandResult::success($output, delayMs: 150);
    }

    private function handleLs(array $args, array $context): CommandResult
    {
        // Mock file listing based on current path
        $path = $context['currentPath'] ?? '/home/user';

        $mockFiles = match ($path) {
            '/home/user' => [
                'documents/',
                'downloads/',
                '.config/',
                'notes.txt',
                'readme.txt',
            ],
            '/home/user/documents' => [
                'mission_briefing.txt',
                'contacts.txt',
                'network_map.png',
            ],
            default => [
                '..',
            ],
        };

        $output = implode("\n", $mockFiles);

        return CommandResult::success($output, delayMs: 80);
    }

    private function handleCd(array $args, array $context): CommandResult
    {
        $target = $args[0] ?? '~';
        $currentPath = $context['currentPath'] ?? '/home/user';

        if ($target === '~') {
            $newPath = '/home/user';
        } elseif ($target === '..') {
            $newPath = dirname($currentPath);
        } elseif (str_starts_with($target, '/')) {
            $newPath = $target;
        } else {
            $newPath = rtrim($currentPath, '/') . '/' . $target;
        }

        return CommandResult::success(
            output: '',
            delayMs: 50,
            stateChanges: ['currentPath' => $newPath],
        );
    }

    private function handleCat(array $args, array $context): CommandResult
    {
        $file = $args[0] ?? null;

        if (!$file) {
            return CommandResult::error('cat: missing operand');
        }

        // Mock file contents
        $mockContents = [
            'notes.txt' => "Remember: The password hint is in the server logs.\nDon't trust Marcus.",
            'readme.txt' => "Welcome to CodeCraft.\n\nType 'help' to see available commands.",
            'mission_briefing.txt' => "TARGET: Meridian Corp\nOBJECTIVE: Extract personnel database\n\nINTEL: Their legacy-auth system has known vulnerabilities.\nCONTACT: Ghost (encrypted channel)",
            'contacts.txt' => "GHOST - Reliable. Expensive.\nMARCUS - Avoid. Suspected fed.\nZERO - New. Unverified.",
        ];

        $content = $mockContents[$file] ?? null;

        if ($content === null) {
            return CommandResult::error("cat: {$file}: No such file or directory");
        }

        return CommandResult::success($content, delayMs: 120);
    }

    private function handleConnect(string $sessionId, array $args): CommandResult
    {
        $target = $args[0] ?? null;

        if (!$target) {
            return CommandResult::error('connect: missing target address');
        }

        // Simulate connection with trace increase
        $this->increaseTrace($sessionId, 0.05);

        $output = <<<OUTPUT
Establishing connection to {$target}...
Routing through proxy chain...
Connection established.

WARNING: Trace level increased. Work quickly.
OUTPUT;

        return CommandResult::success(
            output: $output,
            traceIncrease: 0.05,
            delayMs: 2000,
            stateChanges: ['connectedTo' => $target],
        );
    }

    private function handleScan(string $sessionId, array $args): CommandResult
    {
        $target = $args[0] ?? 'localhost';

        $this->increaseTrace($sessionId, 0.02);

        $output = <<<OUTPUT
Scanning {$target}...

PORT     STATE    SERVICE
22/tcp   open     ssh
80/tcp   open     http
443/tcp  open     https
3306/tcp filtered mysql
8080/tcp open     http-proxy

Scan complete. 5 ports identified.
OUTPUT;

        return CommandResult::success(
            output: $output,
            traceIncrease: 0.02,
            delayMs: 1500,
        );
    }

    private function handleDisconnect(string $sessionId): CommandResult
    {
        return CommandResult::success(
            output: "Connection terminated.\nTrace level stabilizing...",
            delayMs: 500,
            stateChanges: ['connectedTo' => null],
        );
    }

    private function increaseTrace(string $sessionId, float $amount): void
    {
        $current = $this->traceLevels[$sessionId] ?? 0.0;
        $this->traceLevels[$sessionId] = min(1.0, $current + $amount);
    }

    private function handleMail(array $args): CommandResult
    {
        $subcommand = $args[0] ?? 'list';

        return match ($subcommand) {
            'list', '' => $this->handleMailList(),
            'read' => $this->handleMailRead($args[1] ?? null),
            'help' => $this->handleMailHelp(),
            default => is_numeric($subcommand)
                ? $this->handleMailRead($subcommand)
                : CommandResult::error("mail: unknown subcommand '{$subcommand}'. Try 'mail help'."),
        };
    }

    private function handleMailList(): CommandResult
    {
        $messages = $this->messageService->getMessages();
        $unreadCount = $this->messageService->getUnreadCount();

        $output = "INBOX ({$unreadCount} unread)\n";
        $output .= str_repeat('─', 60) . "\n";

        foreach ($messages as $message) {
            $output .= $this->messageService->formatForTerminal($message) . "\n";
        }

        $output .= str_repeat('─', 60) . "\n";
        $output .= "Type 'mail read <id>' to read a message, 'mail help' for more options.";

        return CommandResult::success($output, delayMs: 100);
    }

    private function handleMailRead(?string $id): CommandResult
    {
        if ($id === null) {
            return CommandResult::error("mail read: missing message ID");
        }

        $message = $this->messageService->getMessage((int) $id);

        if (!$message) {
            return CommandResult::error("mail read: message #{$id} not found");
        }

        $this->messageService->markAsRead((int) $id);

        return CommandResult::success(
            $this->messageService->formatFullForTerminal($message),
            delayMs: 150
        );
    }

    private function handleMailHelp(): CommandResult
    {
        $output = <<<HELP
MAIL COMMANDS
─────────────────────────────────────────
  mail              List all messages
  mail list         List all messages
  mail read <id>    Read message by ID
  mail <id>         Shortcut to read message
  mail help         Show this help

TIPS
─────────────────────────────────────────
  [*] indicates unread messages
  JOB messages contain contract offers
  Reply to messages using the Messages app
HELP;

        return CommandResult::success($output, delayMs: 50);
    }

    private function handleSentinel(): CommandResult
    {
        return CommandResult::success(
            $this->sentinelService->formatForTerminal(),
            delayMs: 100
        );
    }
}
