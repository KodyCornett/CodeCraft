<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\GameEngine\GameEngineInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    public function __construct(
        private readonly GameEngineInterface $engine,
    ) {}

    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'required|string|max:1000',
            'context' => 'array',
            'context.currentPath' => 'nullable|string',
            'context.connectedTo' => 'nullable|string',
        ]);

        $sessionId = session()->getId();
        \Log::debug('[Terminal] Session ID: ' . $sessionId . ' | Command: ' . $validated['command']);
        $command = $validated['command'];
        $context = $validated['context'] ?? [];

        // Bootstrap: Mark mission_1 visible on first command execution
        $this->ensureInitialMissionVisible();

        $result = $this->engine->executeCommand($sessionId, $command, $context);

        // Handle game events from Kotlin engine
        $messagesInjected = false;
        if (!empty($result->stateChanges['gameEvents'])) {
            $messagesInjected = $this->handleGameEvents($result->stateChanges['gameEvents']);
        }

        // Merge mission completion flag into stateChanges
        $stateChanges = $result->stateChanges ?? [];
        if ($messagesInjected) {
            $stateChanges['missionCompleted'] = true;
        }

        return response()->json([
            'success' => $result->success,
            'output' => $result->output,
            'lines' => $result->lines,
            'delayMs' => $result->delayMs,
            'traceIncrease' => $result->traceIncrease,
            'stateChanges' => $stateChanges,
            'errorType' => $result->errorType,
        ]);
    }

    /**
     * Ensure mission_1 is visible on first boot
     */
    private function ensureInitialMissionVisible(): void
    {
        if (!session()->has('game_initial_mission_visible')) {
            $missionService = app(\App\Services\Mission\MissionService::class);
            $missionService->markMissionVisible('mission_1');
            session()->put('game_initial_mission_visible', true);
        }
    }

    private function handleGameEvents(array $events): bool
    {
        $messageService = app(\App\Services\Messaging\MessageService::class);
        $messagesInjected = false;

        foreach ($events as $event) {
            \Log::info('[TerminalController] GameEvent received', ['event' => $event]);

            if (($event['type'] ?? '') === 'mission_completed') {
                $missionId = $event['data']['missionId'] ?? '';
                $unlocksString = $event['data']['unlocks'] ?? '';
                $unlockedMissions = !empty($unlocksString) ? explode(',', $unlocksString) : [];

                $messages = $this->getMissionCompletionMessages($missionId, $unlockedMissions);

                \Log::info('[TerminalController] Injecting mission messages', [
                    'missionId' => $missionId,
                    'unlocked' => $unlockedMissions,
                    'count' => count($messages)
                ]);

                foreach ($messages as $msg) {
                    $messageService->injectMessage($msg);
                    $messagesInjected = true;
                }
            }

            // Handle tutorial mission start
            if (($event['type'] ?? '') === 'tutorial_mission_start') {
                $tutorialMessage = $this->getTutorialWalkthroughMessage();
                $messageService->injectMessage($tutorialMessage);
                $messagesInjected = true;

                \Log::info('[TerminalController] Tutorial walkthrough message injected');
            }
        }

        return $messagesInjected;
    }

    private function getMissionCompletionMessages(string $missionId, array $unlockedMissions = []): array
    {
        $messages = [];

        // 1. Add completion message for completed mission
        $completionMessage = match($missionId) {
            'tutorial_basics' => null,  // No completion message - unlock message will be sent instead
            'mission_1' => [
                'id' => 1001,
                'from' => 'Ghost',
                'fromId' => 'ghost',
                'avatar' => '👻',
                'subject' => 'Job complete - payment sent',
                'preview' => 'Not bad. Client confirmed receipt. §1500 transferred.',
                'body' => "Not bad.\n\nClient confirmed receipt. §1500 transferred to your account.\n\nYou kept exposure low. That's the kind of professionalism that gets noticed.\n\n- Ghost",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_2' => [
                'id' => 1002,
                'from' => 'Cipher',
                'fromId' => 'cipher',
                'avatar' => '🔐',
                'subject' => 'Decryption confirmed',
                'preview' => 'The decryption was adequate. My client received the memo.',
                'body' => "The decryption was adequate.\n\nMy client received the memo. Operation Blackout. Meridian. You may want to remember those names.\n\n§3000 transferred.\n\n- Cipher",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_3' => [
                'id' => 1003,
                'from' => 'Zero',
                'fromId' => 'zero',
                'avatar' => '💀',
                'subject' => 'Clean work!',
                'preview' => 'Binary decode like that? You are GOOD.',
                'body' => "Binary decode like that? You are GOOD.\n\nClient loved the prototype specs. §4500 in your account.\n\nI got something bigger lined up. Real high-value target. Check the board soon.\n\n- Zero",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_4' => [
                'id' => 1004,
                'from' => 'Ghost',
                'fromId' => 'ghost',
                'avatar' => '👻',
                'subject' => 'EMERGENCY',
                'preview' => 'That contractor job was a honeypot. Zero is caught.',
                'body' => "EMERGENCY.\n\nThat contractor job was a honeypot. Zero's been caught. SIGINT has your connection records.\n\nThey're tracing back through your ISP RIGHT NOW.\n\nNew job posted — HIGHEST PRIORITY. Scrub your connection logs before they get a warrant.\n\nMove fast.\n\n- Ghost",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_5' => [
                'id' => 1005,
                'from' => 'Ghost',
                'fromId' => 'ghost',
                'avatar' => '👻',
                'subject' => 'You survived',
                'preview' => 'Logs are scrubbed. SIGINT lost the trail.',
                'body' => "Logs are scrubbed.\n\nSIGINT lost the trail. You bought yourself time, but you're on their radar now.\n\nLay low. Someone will contact you. Listen to what they have to say.\n\n- Ghost",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_6' => [
                'id' => 1006,
                'from' => '[REDACTED]',
                'fromId' => 'lena',
                'avatar' => '❓',
                'subject' => 'Good work',
                'preview' => 'The proxy compromise worked. SIGINT is chasing ghosts.',
                'body' => "Good work.\n\nThe proxy compromise worked. SIGINT is chasing false leads now.\n\nYou bought yourself time, but they won't stop. We need to erase the evidence trail completely.\n\nI'll contact you again soon.\n\n- [REDACTED]",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_7' => [
                'id' => 1007,
                'from' => '[REDACTED]',
                'fromId' => 'lena',
                'avatar' => '❓',
                'subject' => 'Evidence deleted',
                'preview' => "All five files deleted. SIGINT can't build a case now.",
                'body' => "All five files deleted.\n\nSIGINT can't build a case against you now. But they know someone hit their evidence server.\n\nHale is going to contact you directly. He'll make an offer. Think carefully before you decide.\n\nYou can call me Lena, by the way. We'll talk again soon.\n\n- Lena",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_8' => [
                'id' => 1008,
                'from' => 'Director Hale',
                'fromId' => 'hale',
                'avatar' => '🎖️',
                'subject' => 'Welcome aboard',
                'preview' => 'You made the right choice. Compliance is rewarded.',
                'body' => "You made the right choice.\n\nCompliance is rewarded. §5000 transferred. Your criminal record is... being reviewed.\n\nI've posted additional assignments to the Jobs Board. Prove your continued cooperation.\n\n- Director Hale\nSIGINT Division",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_9a' => [
                'id' => 1009,
                'from' => 'Director Hale',
                'fromId' => 'hale',
                'avatar' => '🎖️',
                'subject' => 'Target neutralized',
                'preview' => 'The activist database was delivered. Excellent work.',
                'body' => "The activist database was delivered.\n\nExcellent work. These individuals were organizing against vital national security programs.\n\n§6000 transferred. Continue demonstrating your loyalty.\n\n- Director Hale",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_9b' => [
                'id' => 1010,
                'from' => 'Lena',
                'fromId' => 'lena',
                'avatar' => '❓',
                'subject' => 'Meridian evidence secured',
                'preview' => "You got it. The original Meridian files. This changes everything.",
                'body' => "You got it.\n\nThe original Meridian files. Authorization documents signed by Hale himself.\n\nThis is the evidence we need. §5000 transferred, but the real payment is what comes next.\n\nCheck the board. Final job.\n\n- Lena",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_10' => [
                'id' => 1011,
                'from' => 'Director Hale',
                'fromId' => 'hale',
                'avatar' => '🎖️',
                'subject' => 'Data harvest complete',
                'preview' => 'The metadata extraction was successful. §7500 transferred.',
                'body' => "The metadata extraction was successful.\n\n§7500 transferred. You're proving to be a valuable asset.\n\nContinue your work. More assignments will follow.\n\n- Director Hale",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_11' => [
                'id' => 1012,
                'from' => 'Lena',
                'fromId' => 'lena',
                'avatar' => '❓',
                'subject' => "Holst's legacy",
                'preview' => 'You found it. The original Meridian authorization.',
                'body' => "You found it.\n\nThe original Meridian authorization documents. Erik Holst left them for someone like you.\n\nWe have everything now. The surveillance logs, the evidence, the signed authorization.\n\nOne final job. Check the board. This is how we end Meridian.\n\n- Lena",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_12' => [
                'id' => 1013,
                'from' => 'Lena',
                'fromId' => 'lena',
                'avatar' => '❓',
                'subject' => 'Meridian exposed',
                'preview' => 'The data is out. Every journalist, every oversight committee.',
                'body' => "The data is out.\n\nEvery journalist, every oversight committee, every media outlet has the Meridian files.\n\nHale's career is over. The program is exposed. It's done.\n\nGhost has extraction coordinates. Follow his instructions. You need to disappear for a while.\n\nYou finished what Erik Holst started. Thank you.\n\n- Lena",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            default => null
        };

        if ($completionMessage) {
            $messages[] = $completionMessage;
        }

        // 2. Add special story messages (before unlock notifications)
        $specialMessages = match($missionId) {
            'mission_2' => [[
                'id' => 1103,
                'from' => '[REDACTED]',
                'fromId' => 'lena',
                'avatar' => '❓',
                'subject' => 'Re: Operation Blackout',
                'preview' => 'You found the Meridian reference. Good.',
                'body' => "You found the Meridian reference.\n\nGood. Keep digging. You're going to see that name again.\n\nWe'll talk when the time is right.\n\n- [REDACTED]",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ]],
            default => []
        };

        $messages = array_merge($messages, $specialMessages);

        // 3. Mark unlocked missions as visible and add unlock messages
        $missionService = app(\App\Services\Mission\MissionService::class);
        foreach ($unlockedMissions as $unlockedId) {
            // Mark mission as visible on Jobs Board
            $missionService->markMissionVisible($unlockedId);

            $unlockMessage = $this->getJobUnlockMessage($unlockedId);
            if ($unlockMessage) {
                $messages[] = $unlockMessage;
            }
        }

        return $messages;
    }

    private function getJobUnlockMessage(string $missionId): ?array
    {
        return match($missionId) {
            'mission_1' => [
                'id' => 2001,
                'from' => 'Ghost',
                'fromId' => 'ghost',
                'avatar' => '👻',
                'subject' => 'New job available',
                'preview' => 'Posted a job on the board. NovaCorp target.',
                'body' => "Posted a job on the board.\n\nNovaCorp target — straightforward recon and extraction. Good entry-level work, decent pay.\n\nCheck the Jobs Board when you're ready.\n\n- Ghost",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_2' => [
                'id' => 2002,
                'from' => 'Cipher',
                'fromId' => 'cipher',
                'avatar' => '🔐',
                'subject' => 'New job available',
                'preview' => 'Your performance was observed. I have work for you.',
                'body' => "Your performance on the Ghost job was observed.\n\nI have work that requires discretion. Posted to the Jobs Board.\n\nEncrypted corporate communications. Check it if you're interested.\n\n- Cipher",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_3' => [
                'id' => 2003,
                'from' => 'Zero',
                'fromId' => 'zero',
                'avatar' => '💀',
                'subject' => 'Got a job for you',
                'preview' => 'Heard about your work with Cipher. Got something riskier.',
                'body' => "Heard about your work with Cipher. Impressive.\n\nGot a job that's riskier but pays better. Tech startup, AI research, binary-encoded.\n\nPosted to the board. You in?\n\n- Zero",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_4' => [
                'id' => 2004,
                'from' => 'Zero',
                'fromId' => 'zero',
                'avatar' => '💀',
                'subject' => 'THE big score',
                'preview' => 'Got intel on a BIG score. Government contractor.',
                'body' => "Got intel on a BIG score.\n\nGovernment contractor — they do work for SIGINT, DoD, the whole alphabet soup.\n\nGhost said this was too risky. That's how I know it's the real deal.\n\nPosted to the board. You in or you out?\n\n- Zero",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_5' => [
                'id' => 2005,
                'from' => 'Ghost',
                'fromId' => 'ghost',
                'avatar' => '👻',
                'subject' => 'EMERGENCY JOB POSTED',
                'preview' => 'ISP logs need scrubbing NOW. Check the board immediately.',
                'body' => "EMERGENCY JOB POSTED.\n\nISP logs need scrubbing NOW. Check the board immediately.\n\nYou have minutes, not hours.\n\n- Ghost",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_6' => [
                'id' => 2006,
                'from' => '[REDACTED]',
                'fromId' => 'lena',
                'avatar' => '❓',
                'subject' => 'I can help',
                'preview' => "You're in deep. I have a way out.",
                'body' => "You don't know me. Not yet.\n\nI've been watching your work. You're good, but you're in deep.\n\nI can help. Check the Jobs Board. SIGINT proxy compromise.\n\nTrust me or don't. Your choice.\n\n- [REDACTED]",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_7' => [
                'id' => 2007,
                'from' => '[REDACTED]',
                'fromId' => 'lena',
                'avatar' => '❓',
                'subject' => 'New job posted',
                'preview' => 'Evidence server. Five files. Delete them all.',
                'body' => "Evidence server job posted.\n\nSIGINT has five files that connect you to the contractor breach. All five must be deleted.\n\n20 minutes before backup cycle. Check the board.\n\n- [REDACTED]",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_8' => [
                'id' => 2008,
                'from' => 'Director Hale',
                'fromId' => 'hale',
                'avatar' => '🎖️',
                'subject' => 'Official contact',
                'preview' => 'Director Hale, SIGINT Division. We need to talk.',
                'body' => "This is Director Hale, SIGINT Division.\n\nWe know about the contractor breach. We know about the evidence deletion.\n\nYou have two options. I've posted the details to the Jobs Board.\n\nRead carefully. Your future depends on your choice.\n\n- Director Hale",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_9a' => [
                'id' => 2009,
                'from' => 'Director Hale',
                'fromId' => 'hale',
                'avatar' => '🎖️',
                'subject' => 'Next assignment',
                'preview' => 'Activist organization server. Posted to the board.',
                'body' => "Next assignment posted.\n\nActivist organization organizing against surveillance programs. Server compromise required.\n\nCheck the Jobs Board. Prove your continued loyalty.\n\n- Director Hale",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_9b' => [
                'id' => 2010,
                'from' => 'Lena',
                'fromId' => 'lena',
                'avatar' => '❓',
                'subject' => 'Counter-operation',
                'preview' => "Don't work for Hale. I have a better plan.",
                'body' => "I saw Hale's message.\n\nDon't work for them. They'll use you and then burn you like they did Erik Holst.\n\nI've posted a counter-operation to the board. We feed SIGINT false data while we get the REAL Meridian files.\n\nCheck it. Your choice.\n\n- Lena",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_10' => [
                'id' => 2011,
                'from' => 'Director Hale',
                'fromId' => 'hale',
                'avatar' => '🎖️',
                'subject' => 'High-value operation',
                'preview' => 'Meridian data extraction. Big payout.',
                'body' => "High-value operation posted.\n\nMeridian surveillance data extraction. 90 days of metadata.\n\n§7500 compensation. Check the Jobs Board.\n\n- Director Hale",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_11' => [
                'id' => 2012,
                'from' => 'Lena',
                'fromId' => 'lena',
                'avatar' => '❓',
                'subject' => 'The final piece',
                'preview' => "Erik Holst's dead drop. The original Meridian authorization.",
                'body' => "This is it.\n\nErik Holst left a dead drop. The ORIGINAL Meridian authorization documents.\n\nSigned by Hale himself. Proof of the whole operation.\n\nPosted to the board. After this, there's no going back.\n\n- Lena",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            'mission_12' => [
                'id' => 2013,
                'from' => 'Lena',
                'fromId' => 'lena',
                'avatar' => '❓',
                'subject' => 'FINALE',
                'preview' => 'Meridian core infiltration. This is how we end it.',
                'body' => "Final job posted.\n\nMeridian core server. Master database. Every target, every intercept, every illegal operation.\n\nWe copy it all and release it to the world.\n\nCheck the board. This is what Erik Holst died trying to do. Finish it.\n\n- Lena",
                'read' => false,
                'timestamp' => 'Just now',
                'datetime' => time(),
                'type' => 'contact',
                'replyable' => false,
            ],
            default => null
        };
    }

    private function getTutorialWalkthroughMessage(): array
    {
        return [
            'id' => 5001,  // High ID to avoid conflicts
            'from' => 'Ghost',
            'fromId' => 'ghost',
            'avatar' => '👻',
            'subject' => '⚠️ HIGH PRIORITY — Field Manual: Network Operations',
            'preview' => 'Network encryption keys unlocked. Read this before you do anything else.',
            'body' => "You just accepted your first real job.\n\nI've unlocked your network encryption keys. Three commands are now active in your terminal:\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nCORE NETWORK COMMANDS\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n**scan**\n  Discover nodes on the network. Shows IPs, open ports, and security levels.\n  Usage: scan\n  Example: scan → discovers 192.168.50.10\n\n**connect <ip>**\n  Establish a connection to a remote system.\n  Usage: connect <ip_address>\n  Example: connect 192.168.50.10\n\n**disconnect**\n  Sever your connection and return to localhost.\n  Usage: disconnect\n  WARNING: Always disconnect when exposure gets high!\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nTHE NODE MANAGER\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\nOpen the **Node Manager** app on your desktop.\n\nThis is your visual matrix. Every node you discover with 'scan' appears as a glowing point in the network graph. Connected nodes show link lines.\n\n• Click nodes to see details (IP, type, security)\n• Your current connection is highlighted\n• Use it to plan your path through multi-hop networks\n\nThink of it as your tactical map. The terminal is how you move. The Node Manager is how you see.\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nTHE HACK WIKI\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\nYou don't know what you don't know.\n\nOpen the **Matrix Browser** app and navigate to:\n  **wiki.matrix**\n\nFull command reference. Every exploit. Every tool. Every technique.\n\nIf you get stuck, the wiki has the answer. Use it.\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nYOUR FIRST JOB\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n1. Run 'scan' to find the target\n2. Run 'connect <ip>' to link in\n3. Use 'ls' and 'cd' to explore files\n4. Complete the objectives (check Jobs Board → ACTIVE)\n5. Run 'disconnect' when done\n\nWatch your exposure in Sentinel. If it hits red, disconnect immediately.\n\nDon't improvise. Follow the steps. Get the data. Get out.\n\n- Ghost",
            'read' => false,
            'timestamp' => 'Just now',
            'datetime' => time(),
            'type' => 'contact',
            'replyable' => false,
        ];
    }

    public function networkState(): JsonResponse
    {
        $sessionId = session()->getId();
        \Log::debug('[Network State] Session ID: ' . $sessionId);
        $state = $this->engine->getNetworkState($sessionId);

        // Normalise key: Kotlin uses 'currentNode', frontend expects 'currentNodeId'
        if (isset($state['currentNode']) && !isset($state['currentNodeId'])) {
            $state['currentNodeId'] = $state['currentNode'];
        }

        return response()->json($state);
    }
}
