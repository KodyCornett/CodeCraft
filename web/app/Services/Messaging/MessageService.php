<?php

declare(strict_types=1);

namespace App\Services\Messaging;

/**
 * Message Service - Handles in-game messaging system.
 * Messages come from contacts, job postings, and system notifications.
 *
 * Three message sources are merged in getMessages():
 * 1. Base messages (filtered by mission state)
 * 2. Dynamic messages (injected by MissionService on completion)
 * 3. Encrypted messages (from secure channel puzzle flow)
 */
class MessageService
{
    /**
     * Available jobs that can be offered through secure channel.
     */
    private array $availableJobs = [
        'nova-corp-hr' => [
            'id' => 'nova-corp-hr',
            'senderName' => 'Ghost',
            'senderAvatar' => "\u{1F47B}",
            'description' => "I've got a client looking for some data.\n\nNovaCorp's been sloppy with their HR department security. My contact wants employee records - nothing that'll raise red flags, just basic personnel data.\n\nQuick in-and-out job. Good for building your rep.",
            'target' => '192.168.50.10',
            'objective' => 'Extract employee database',
            'payout' => 2500,
            'difficulty' => 'easy',
        ],
        'nova-corp-recon' => [
            'id' => 'nova-corp-recon',
            'senderName' => 'Ghost',
            'senderAvatar' => "\u{1F47B}",
            'description' => "NovaCorp's web server is sitting at 192.168.50.10. My client wants eyes on their employee database — names, roles, access levels.\n\nConnect to the target, find the data directory, and open the employee records. Quick in-and-out.",
            'target' => '192.168.50.10',
            'objective' => 'Access employee database',
            'payout' => 1500,
            'difficulty' => 'easy',
        ],
        'nova-corp-db-heist' => [
            'id' => 'nova-corp-db-heist',
            'senderName' => 'Ghost',
            'senderAvatar' => "\u{1F47B}",
            'description' => "The database server at 10.0.100.50 has the financial records. You'll need to pivot through the web server to reach it — scan the internal network once you're connected.\n\nTougher security on this one. The accounts export in /backup/ is the target.",
            'target' => '10.0.100.50',
            'objective' => 'Extract financial records',
            'payout' => 5000,
            'difficulty' => 'medium',
        ],
        'cybertech-research' => [
            'id' => 'cybertech-research',
            'senderName' => 'Ghost',
            'senderAvatar' => "\u{1F47B}",
            'description' => "Bigger fish this time.\n\nCyberTech Industries has some research files a competitor is willing to pay premium for. Their security is tighter - expect firewalls and active monitoring.\n\nHigh risk, high reward.",
            'target' => '10.0.100.50',
            'objective' => 'Retrieve research documents',
            'payout' => 7500,
            'difficulty' => 'medium',
        ],
    ];

    /**
     * Words for cipher puzzles.
     */
    private array $puzzleWords = ['HACK', 'CODE', 'DATA', 'NODE', 'LINK', 'SCAN', 'BYTE', 'CORE', 'GRID', 'ZERO'];

    /**
     * Get all messages for the player (excluding deleted).
     * Merges: encrypted messages (top) + dynamic messages + visible base messages.
     *
     * @return array<array{id: int, from: string, fromId: string, subject: string, preview: string, body: string, read: bool, timestamp: string, type: string, replyable: bool}>
     */
    public function getMessages(): array
    {
        $deletedIds = $this->getDeletedMessageIds();

        $encryptedMessages = array_values($this->getEncryptedMessages());
        $dynamicMessages = $this->getDynamicMessages();
        $baseMessages = $this->getVisibleBaseMessages();

        // Merge all messages
        $allMessages = array_merge($encryptedMessages, $dynamicMessages, $baseMessages);

        // Filter out deleted messages and apply read state
        $filtered = [];
        foreach ($allMessages as $msg) {
            // Exclude deleted messages
            if (in_array($msg['id'], $deletedIds, true)) {
                continue;
            }

            // Apply persistent read state from session
            $msg['read'] = $this->isMessageRead($msg['id']) || ($msg['read'] ?? false);
            $filtered[] = $msg;
        }

        return $filtered;
    }

    /**
     * Get deleted messages.
     */
    public function getDeletedMessages(): array
    {
        $deletedIds = $this->getDeletedMessageIds();

        $encryptedMessages = array_values($this->getEncryptedMessages());
        $dynamicMessages = $this->getDynamicMessages();
        $baseMessages = $this->getVisibleBaseMessages();

        $allMessages = array_merge($encryptedMessages, $dynamicMessages, $baseMessages);

        $deleted = [];
        foreach ($allMessages as $msg) {
            if (in_array($msg['id'], $deletedIds, true)) {
                // Apply persistent read state
                $msg['read'] = $this->isMessageRead($msg['id']) || ($msg['read'] ?? false);
                $deleted[] = $msg;
            }
        }

        return $deleted;
    }

    /**
     * Check if a message is read.
     */
    private function isMessageRead(int $id): bool
    {
        $readMessages = session()->get('game_read_messages', []);
        return in_array($id, $readMessages, true);
    }

    /**
     * Get deleted message IDs.
     */
    private function getDeletedMessageIds(): array
    {
        return session()->get('game_deleted_messages', []);
    }

    /**
     * Inject a dynamic message (called when mission events are received from the engine).
     */
    public function injectMessage(array $message): void
    {
        $messages = session()->get('game_dynamic_messages', []);

        // Don't inject duplicates
        foreach ($messages as $existing) {
            if ($existing['id'] === $message['id']) {
                return;
            }
        }

        // Prepend so newest are first
        array_unshift($messages, $message);
        session()->put('game_dynamic_messages', $messages);
    }

    /**
     * Get dynamic messages from session.
     */
    private function getDynamicMessages(): array
    {
        return session()->get('game_dynamic_messages', []);
    }

    /**
     * Get base messages filtered by mission state.
     *
     * - Messages 1 (Ghost welcome) + 4 (System tutorial): Always visible
     * - Message 6 (Ghost "Read this first"): Always visible — Mission 1 trigger
     * - Message 2 (Ghost "Opportunity"): Removed — replaced by mission progression
     * - Message 3 (Cipher "Tools for sale"): Injected dynamically after Mission 3
     * - Message 5 (Unknown "watching"): Injected dynamically after Mission 2
     */
    private function getVisibleBaseMessages(): array
    {
        // Only show the always-visible base messages
        return [
            [
                'id' => 6,
                'from' => 'Ghost',
                'fromId' => 'ghost',
                'avatar' => "\u{1F47B}",
                'subject' => 'Read this first',
                'preview' => "Hey rookie. Before anything else, get familiar with your system...",
                'body' => "Hey,\n\nBefore you do anything else, get familiar with your system.\n\nTry these commands in the terminal:\n\n1. `ls` — see what's in your current directory\n2. `cd documents` — navigate to your documents folder\n3. `cat mission_briefing.txt` — read the briefing I left you\n4. `mail` — check your inbox (you're reading this, so good start)\n\nOnce you've done all four, I'll know you're ready and send you the real work.\n\nDon't skip steps. This isn't a game.\n\n- Ghost",
                'read' => false,
                'timestamp' => '10 minutes ago',
                'datetime' => '2025-01-15 12:20:00',
                'type' => 'contact',
                'replyable' => false,
            ],
            [
                'id' => 1,
                'from' => 'Ghost',
                'fromId' => 'ghost',
                'avatar' => "\u{1F47B}",
                'subject' => 'Welcome to the Network',
                'preview' => 'Hey, welcome aboard. I heard you\'re looking to make some credits...',
                'body' => "Hey,\n\nWelcome aboard. I heard you're looking to make some credits in the underground. Smart move.\n\nI've got connections - corps, fixers, other hackers. When jobs come up that match your skills, I'll send them your way.\n\nFor now, get familiar with your tools. Check out the wiki at wiki.matrix for command references. When you're ready for your first real job, hit me up.\n\nStay dark.\n\n- Ghost",
                'read' => true,
                'timestamp' => '2 hours ago',
                'datetime' => '2025-01-15 10:30:00',
                'type' => 'contact',
                'replyable' => true,
            ],
            [
                'id' => 4,
                'from' => 'System',
                'fromId' => 'system',
                'avatar' => "\u{2699}\u{FE0F}",
                'subject' => 'Tutorial: Getting Started',
                'preview' => 'Welcome to your new system. Here are some tips...',
                'body' => "Welcome to your new hacking terminal.\n\n**Quick Start Guide:**\n\n1. Use `help` to see available commands\n2. Use `scan` to discover targets on the network\n3. Use `connect` to establish connections\n4. Use `ls` and `cat` to explore file systems\n\n**Tips:**\n- Watch your trace level - if it hits 100%, you're caught\n- Buy better tools from the MatrixNet Store\n- Check the wiki for detailed command documentation\n\n**Need Help?**\nVisit wiki.matrix for tutorials and command references.\n\nGood luck, hacker.",
                'read' => true,
                'timestamp' => '3 days ago',
                'datetime' => '2025-01-12 09:00:00',
                'type' => 'system',
                'replyable' => false,
            ],
        ];
    }

    /**
     * Get a single message by ID.
     */
    public function getMessage(int $id): ?array
    {
        $messages = $this->getMessages();

        foreach ($messages as $message) {
            if ($message['id'] === $id) {
                return $message;
            }
        }

        return null;
    }

    /**
     * Get unread message count.
     */
    public function getUnreadCount(): int
    {
        $count = 0;
        foreach ($this->getMessages() as $message) {
            if (!$message['read']) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Mark a message as read.
     * Persistent across refreshes.
     */
    public function markAsRead(int $id): bool
    {
        $readMessages = session()->get('game_read_messages', []);
        if (!in_array($id, $readMessages, true)) {
            $readMessages[] = $id;
            session()->put('game_read_messages', $readMessages);
        }

        return true;
    }

    /**
     * Mark a message as deleted (moves to trash).
     */
    public function markAsDeleted(int $id): bool
    {
        $deletedMessages = session()->get('game_deleted_messages', []);
        if (!in_array($id, $deletedMessages, true)) {
            $deletedMessages[] = $id;
            session()->put('game_deleted_messages', $deletedMessages);
        }

        return true;
    }

    /**
     * Restore a message from trash.
     */
    public function restoreMessage(int $id): bool
    {
        $deletedMessages = session()->get('game_deleted_messages', []);
        $key = array_search($id, $deletedMessages, true);

        if ($key !== false) {
            unset($deletedMessages[$key]);
            session()->put('game_deleted_messages', array_values($deletedMessages));
            return true;
        }

        return false;
    }

    /**
     * Send a message/reply.
     * In real implementation, this would trigger game events.
     */
    public function sendMessage(string $toId, string $subject, string $body, ?int $replyTo = null): array
    {
        // Mock response - in real game, this might trigger NPC responses
        return [
            'success' => true,
            'message' => 'Message sent successfully.',
            'response' => $this->generateAutoResponse($toId, $subject),
        ];
    }

    /**
     * Generate automatic NPC response (for game flow).
     */
    private function generateAutoResponse(string $toId, string $subject): ?array
    {
        // Some contacts auto-respond for game progression
        if ($toId === 'ghost' && str_contains(strtolower($subject), 'job')) {
            return [
                'delay' => 30, // seconds until response appears
                'preview' => 'Ghost will respond shortly with job details...',
            ];
        }

        return null;
    }

    /**
     * Get contacts list for compose dropdown.
     */
    public function getContacts(): array
    {
        return [
            ['id' => 'ghost', 'name' => 'Ghost', 'avatar' => "\u{1F47B}", 'status' => 'online'],
            ['id' => 'cipher', 'name' => 'Cipher', 'avatar' => "\u{1F510}", 'status' => 'online'],
            ['id' => 'zero', 'name' => 'Zer0', 'avatar' => "\u{1F480}", 'status' => 'offline'],
            ['id' => 'nexus', 'name' => 'Nexus', 'avatar' => "\u{1F310}", 'status' => 'away'],
        ];
    }

    /**
     * Format message for terminal display.
     */
    public function formatForTerminal(array $message): string
    {
        $status = $message['read'] ? ' ' : '*';
        $from = str_pad($message['from'], 12);
        $subject = mb_substr($message['subject'], 0, 40);

        return sprintf("[%s] #%d %s %s - %s", $status, $message['id'], $from, $message['timestamp'], $subject);
    }

    /**
     * Format full message for terminal reading.
     */
    public function formatFullForTerminal(array $message): string
    {
        $lines = [
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "From:    {$message['from']} {$message['avatar']}",
            "Date:    {$message['timestamp']}",
            "Subject: {$message['subject']}",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "",
            $message['body'],
            "",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
        ];

        if ($message['replyable']) {
            $lines[] = "Reply with: mail reply {$message['id']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Get a job by ID.
     */
    public function getJob(string $jobId): ?array
    {
        return $this->availableJobs[$jobId] ?? null;
    }

    /**
     * Attempt to negotiate a job payout.
     * Success is based on "reputation" (randomized for now).
     */
    public function negotiateJob(string $jobId): array
    {
        $job = $this->getJob($jobId);

        if (!$job) {
            return [
                'success' => false,
                'message' => 'Job not found.',
            ];
        }

        // 60% chance of successful negotiation
        $success = random_int(1, 100) <= 60;

        if ($success) {
            $bonus = (int) ($job['payout'] * 0.15); // 15% bonus
            $newPayout = $job['payout'] + $bonus;

            return [
                'success' => true,
                'message' => "Negotiation successful! Payout increased to §" . number_format($newPayout) . ".",
                'newPayout' => $newPayout,
            ];
        } else {
            // 30% chance of payout reduction, 70% chance of no change
            $reduction = random_int(1, 100) <= 30;

            if ($reduction) {
                $penalty = (int) ($job['payout'] * 0.10); // 10% reduction
                $newPayout = $job['payout'] - $penalty;

                return [
                    'success' => false,
                    'message' => "Negotiation failed. Client is annoyed - payout reduced to §" . number_format($newPayout) . ".",
                    'newPayout' => $newPayout,
                ];
            }

            return [
                'success' => false,
                'message' => "Negotiation failed. Take it or leave it.",
            ];
        }
    }

    /**
     * Accept a job and generate the verification puzzle.
     */
    public function acceptJob(string $jobId): array
    {
        $job = $this->getJob($jobId);

        if (!$job) {
            return [
                'success' => false,
                'error' => 'Job not found.',
            ];
        }

        $puzzle = $this->generateCipherPuzzle();

        // Store the puzzle answer in session for verification
        session()->put("job_puzzle_{$jobId}", $puzzle['answer']);

        return [
            'success' => true,
            'puzzle' => $puzzle['puzzle'],
            'hint' => 'Decode: A=1, B=2, C=3...',
        ];
    }

    /**
     * Verify the puzzle answer and create encrypted mission briefing.
     */
    public function verifyPuzzle(string $jobId, string $answer): array
    {
        $expectedAnswer = session()->get("job_puzzle_{$jobId}");

        if (!$expectedAnswer) {
            return [
                'success' => false,
                'error' => 'Puzzle session expired. Please try again.',
            ];
        }

        if (strtoupper(trim($answer)) !== $expectedAnswer) {
            return [
                'success' => false,
                'error' => 'Incorrect. Try again.',
            ];
        }

        // Puzzle solved - create encrypted message with mission briefing
        $job = $this->getJob($jobId);
        $passcode = $expectedAnswer; // The puzzle answer IS the passcode

        // Store the encrypted message data
        $encryptedMessageId = $this->createEncryptedMissionBriefing($jobId, $job, $passcode);

        // Clear the puzzle from session
        session()->forget("job_puzzle_{$jobId}");

        return [
            'success' => true,
            'message' => 'Access verified. Encrypted briefing sent to your inbox.',
            'passcode' => $passcode, // Show the passcode so user knows what to use
            'messageId' => $encryptedMessageId,
        ];
    }

    /**
     * Generate a simple A=1, B=2 cipher puzzle.
     */
    public function generateCipherPuzzle(): array
    {
        $word = $this->puzzleWords[array_rand($this->puzzleWords)];

        // Convert each letter to its position (A=1, B=2, etc.)
        $encoded = implode('-', array_map(
            fn($char) => ord($char) - 64,
            str_split($word)
        ));

        return [
            'puzzle' => $encoded,
            'answer' => $word,
        ];
    }

    /**
     * Create an encrypted mission briefing message.
     * Returns the message ID.
     */
    private function createEncryptedMissionBriefing(string $jobId, array $job, string $passcode): int
    {
        // In a real implementation, this would insert into database
        // For now, we store in session
        $messageId = 100 + random_int(1, 999);

        $briefing = [
            'id' => $messageId,
            'from' => $job['senderName'],
            'fromId' => strtolower($job['senderName']),
            'avatar' => $job['senderAvatar'],
            'subject' => 'Mission Briefing [ENCRYPTED]',
            'preview' => 'Encrypted transmission...',
            'encrypted' => true,
            'passcode' => $passcode,
            'body' => $this->generateMissionBriefingBody($job),
            'read' => false,
            'timestamp' => 'Just now',
            'datetime' => now()->format('Y-m-d H:i:s'),
            'type' => 'job',
            'replyable' => false,
            'jobData' => [
                'target' => $job['target'],
                'objective' => $job['objective'],
                'payout' => $job['payout'],
                'difficulty' => $job['difficulty'],
            ],
        ];

        // Store encrypted messages in session
        $encryptedMessages = session()->get('encrypted_messages', []);
        $encryptedMessages[$messageId] = $briefing;
        session()->put('encrypted_messages', $encryptedMessages);

        return $messageId;
    }

    /**
     * Generate the full mission briefing body.
     */
    private function generateMissionBriefingBody(array $job): string
    {
        return "═══ MISSION BRIEFING ═══\n\n" .
               "**Target:** {$job['target']}\n" .
               "**Objective:** {$job['objective']}\n" .
               "**Payout:** §" . number_format($job['payout']) . "\n\n" .
               "═══ STEP-BY-STEP GUIDE ═══\n\n" .
               "**Step 1: Scan the target**\n" .
               "Open Terminal and run:\n" .
               "  scan {$job['target']}\n\n" .
               "This reveals open ports and services.\n\n" .
               "**Step 2: Connect to the target**\n" .
               "  connect {$job['target']}\n\n" .
               "**Step 3: Explore the filesystem**\n" .
               "  ls              (list files)\n" .
               "  cd <folder>     (enter folder)\n" .
               "  cat <file>      (read file)\n\n" .
               "**Step 4: Find and download the objective**\n" .
               "  download <filename>\n\n" .
               "**Step 5: Disconnect safely**\n" .
               "  disconnect\n\n" .
               "═══ TIPS ═══\n" .
               "- Watch your TRACE level in Sentinel\n" .
               "- Use 'help' to see all commands\n" .
               "- If trace gets high, disconnect fast!\n\n" .
               "Good luck, operator.";
    }

    /**
     * Attempt to decrypt a message with a passcode.
     */
    public function decryptMessage(int $messageId, string $passcode): array
    {
        $encryptedMessages = session()->get('encrypted_messages', []);

        if (!isset($encryptedMessages[$messageId])) {
            return [
                'success' => false,
                'error' => 'Message not found.',
            ];
        }

        $message = $encryptedMessages[$messageId];

        if (strtoupper(trim($passcode)) !== $message['passcode']) {
            return [
                'success' => false,
                'error' => 'Invalid passcode.',
            ];
        }

        // Mark as decrypted
        $encryptedMessages[$messageId]['encrypted'] = false;
        session()->put('encrypted_messages', $encryptedMessages);

        return [
            'success' => true,
            'body' => $message['body'],
            'jobData' => $message['jobData'] ?? null,
        ];
    }

    /**
     * Get encrypted messages from session.
     */
    public function getEncryptedMessages(): array
    {
        return session()->get('encrypted_messages', []);
    }
}
