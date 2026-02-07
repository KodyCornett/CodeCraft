<?php

declare(strict_types=1);

namespace App\Services\Messaging;

/**
 * Message Service - Handles in-game messaging system.
 * Messages come from contacts, job postings, and system notifications.
 */
class MessageService
{
    /**
     * Get all messages for the player.
     *
     * @return array<array{id: int, from: string, fromId: string, subject: string, preview: string, body: string, read: bool, timestamp: string, type: string, replyable: bool}>
     */
    public function getMessages(): array
    {
        // Mock messages - in real game, comes from database
        return [
            [
                'id' => 1,
                'from' => 'Ghost',
                'fromId' => 'ghost',
                'avatar' => '👻',
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
                'id' => 2,
                'from' => 'Ghost',
                'fromId' => 'ghost',
                'avatar' => '👻',
                'subject' => 'Job: Data Extraction - NovaCorp',
                'preview' => 'Got a job for you. NovaCorp has some files a client wants...',
                'body' => "Got a job for you.\n\nNovaCorp has some files a client wants. Nothing too sensitive - employee records from their HR department. Client's willing to pay 2,500 credits for the data.\n\n**Target:** nova-corp.matrix (192.168.50.10)\n**Objective:** Extract /data/hr/employees.db\n**Payout:** 2,500 credits\n**Difficulty:** Easy\n\nTheir security is basic - probably just need a port scan and SSH exploit. Good starter job.\n\nReply if you're interested and I'll send you the access codes.\n\n- Ghost",
                'read' => false,
                'timestamp' => '35 minutes ago',
                'datetime' => '2025-01-15 12:05:00',
                'type' => 'job',
                'replyable' => true,
                'jobData' => [
                    'target' => '192.168.50.10',
                    'objective' => 'Extract employee database',
                    'payout' => 2500,
                    'difficulty' => 'easy',
                ],
            ],
            [
                'id' => 3,
                'from' => 'Cipher',
                'fromId' => 'cipher',
                'avatar' => '🔐',
                'subject' => 'Tools for sale',
                'preview' => 'Got some new exploits in stock. Check the store...',
                'body' => "Yo,\n\nJust restocked my inventory. Got some fresh exploits that might interest you:\n\n- **SSHCracker v2.1** - Faster brute force, lower trace\n- **PortMapper Pro** - Advanced port scanning with service detection\n- **LogWiper** - Clean your traces after a job\n\nCheck them out at store.matrix. Mention my name and I'll throw in a 10% discount on your first purchase.\n\n- Cipher",
                'read' => true,
                'timestamp' => '1 day ago',
                'datetime' => '2025-01-14 16:20:00',
                'type' => 'contact',
                'replyable' => true,
            ],
            [
                'id' => 4,
                'from' => 'System',
                'fromId' => 'system',
                'avatar' => '⚙️',
                'subject' => 'Tutorial: Getting Started',
                'preview' => 'Welcome to your new system. Here are some tips...',
                'body' => "Welcome to your new hacking terminal.\n\n**Quick Start Guide:**\n\n1. Use `help` to see available commands\n2. Use `scan` to discover targets on the network\n3. Use `connect` to establish connections\n4. Use `ls` and `cat` to explore file systems\n\n**Tips:**\n- Watch your trace level - if it hits 100%, you're caught\n- Buy better tools from the MatrixNet Store\n- Check the wiki for detailed command documentation\n\n**Need Help?**\nVisit wiki.matrix for tutorials and command references.\n\nGood luck, hacker.",
                'read' => true,
                'timestamp' => '3 days ago',
                'datetime' => '2025-01-12 09:00:00',
                'type' => 'system',
                'replyable' => false,
            ],
            [
                'id' => 5,
                'from' => 'Unknown',
                'fromId' => 'unknown',
                'avatar' => '❓',
                'subject' => 'We\'ve been watching',
                'preview' => 'Your skills haven\'t gone unnoticed...',
                'body' => "Your skills haven't gone unnoticed.\n\nWe represent an organization that values... discretion. There may be opportunities for someone with your talents.\n\nWe'll be in touch.\n\n- ███████",
                'read' => false,
                'timestamp' => '5 hours ago',
                'datetime' => '2025-01-15 07:30:00',
                'type' => 'mysterious',
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
     * In real implementation, this would update the database.
     */
    public function markAsRead(int $id): bool
    {
        // Mock - would update database
        return true;
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
            ['id' => 'ghost', 'name' => 'Ghost', 'avatar' => '👻', 'status' => 'online'],
            ['id' => 'cipher', 'name' => 'Cipher', 'avatar' => '🔐', 'status' => 'online'],
            ['id' => 'zero', 'name' => 'Zer0', 'avatar' => '💀', 'status' => 'offline'],
            ['id' => 'nexus', 'name' => 'Nexus', 'avatar' => '🌐', 'status' => 'away'],
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
}
