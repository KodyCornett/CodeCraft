<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GameEngine\KotlinGameEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    public function __construct(
        private readonly KotlinGameEngine $engine
    ) {}

    /**
     * Get active mission status
     */
    public function getActiveMission(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-Session-Id') ?? session()->getId();
        $mission = $this->engine->getActiveMission($sessionId);

        return response()->json([
            'success' => true,
            'active' => $mission !== null,
            'mission' => $mission,
        ]);
    }

    /**
     * Complete active mission
     */
    public function completeMission(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-Session-Id') ?? session()->getId();
        $result = $this->engine->completeMission($sessionId);

        return response()->json($result);
    }

    /**
     * Abandon active mission
     */
    public function abandonMission(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-Session-Id') ?? session()->getId();
        $result = $this->engine->abandonMission($sessionId);

        return response()->json($result);
    }

    /**
     * Accept a mission from the board
     */
    public function acceptMission(Request $request, string $missionId): JsonResponse
    {
        $sessionId = $request->header('X-Session-Id') ?? session()->getId();
        $result = $this->engine->acceptMission($sessionId, $missionId);

        // Handle game events (e.g., tutorial_mission_start for mission_1)
        if (!empty($result['gameEvents'])) {
            $this->handleGameEvents($result['gameEvents']);
        }

        return response()->json($result);
    }

    /**
     * Handle game events from engine responses
     */
    private function handleGameEvents(array $events): void
    {
        $messageService = app(\App\Services\Messaging\MessageService::class);

        foreach ($events as $event) {
            \Log::info('[MissionController] GameEvent received', ['event' => $event]);

            // Handle tutorial mission start (mission_1 acceptance)
            if (($event['type'] ?? '') === 'tutorial_mission_start') {
                $tutorialMessage = $this->getTutorialWalkthroughMessage();
                $messageService->injectMessage($tutorialMessage);

                \Log::info('[MissionController] Tutorial walkthrough message injected');
            }

            // Handle mission completion events
            if (($event['type'] ?? '') === 'mission_completed') {
                // Additional mission completion handling if needed
                \Log::info('[MissionController] Mission completed event', ['data' => $event['data'] ?? []]);
            }
        }
    }

    /**
     * Get the Field Manual tutorial message
     */
    private function getTutorialWalkthroughMessage(): array
    {
        return [
            'id' => 5001,  // High ID to avoid conflicts
            'from' => 'Ghost',
            'fromId' => 'ghost',
            'avatar' => '👻',
            'subject' => '⚠️ HIGH PRIORITY — Field Manual: Network Operations',
            'preview' => 'Network encryption keys unlocked. Read this before you do anything else.',
            'body' => "You just accepted your first real job.\n\nI've unlocked your network encryption keys. Three commands are now active in your terminal:\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nCORE NETWORK COMMANDS\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n**scan**\n  Discover nodes on the network. Shows IPs, open ports, and security levels.\n  Usage: scan\n  Example: scan → discovers 192.168.50.10\n\n**connect <ip>**\n  Establish a connection to a remote system.\n  Usage: connect <ip_address>\n  Example: connect 192.168.50.10\n\n**disconnect**\n  Sever your connection and return to localhost.\n  Usage: disconnect\n  WARNING: Always disconnect when exposure gets high!\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nTHE NODE MANAGER\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\nOpen the **Node Manager** app on your desktop.\n\nThis is your visual matrix. Every node you discover with 'scan' appears as a glowing point in the network graph. Connected nodes show link lines.\n\n• Click nodes to see details (IP, type, security)\n• Your current connection is highlighted\n• Use it to plan your path through multi-hop networks\n\nThink of it as your tactical map. The terminal is how you move. The Node Manager is how you see.\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nTHE HACK WIKI\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\nYou don't know what you don't know.\n\nOpen the **Matrix Browser** app and navigate to:\n  **wiki.matrix**\n\nFull command reference. Every exploit. Every tool. Every technique.\n\nIf you get stuck, the wiki has the answer. Use it.\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nMISSION DATA HANDLING\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\nMost jobs require you to steal files. Two-step process:\n\n**1. DOWNLOAD FILES**\n  Command: download <filename>\n  Example: download company_secrets.txt\n\n  Downloads file from connected system to your local machine.\n  Increases exposure by 15% per file. Be quick.\n\n**2. EXFILTRATE TO CONTACT**\n  Command: exfil <filename>\n  Example: exfil company_secrets.txt\n\n  Securely transmits file to me through encrypted tunnel.\n  Must be done from localhost (disconnect first).\n  Marks delivery objectives complete.\n\nCheck Jobs Board → ACTIVE → Required Files to see what I need.\n\nDownload. Disconnect. Exfil. Simple.\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nYOUR FIRST JOB\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n1. Run 'scan' to find the target\n2. Run 'connect <ip>' to link in\n3. Use 'ls' and 'cd' to explore files\n4. Use 'download <filename>' to grab data\n5. Run 'disconnect' when done\n6. Run 'exfil <filename>' to deliver files to me\n7. Complete all objectives (check Jobs Board → ACTIVE)\n\nWatch your exposure in Sentinel. If it hits red, disconnect immediately.\n\nDon't improvise. Follow the steps. Get the data. Get out.\n\n- Ghost",
            'read' => false,
            'timestamp' => 'Just now',
            'datetime' => time(),
            'type' => 'contact',
            'replyable' => false,
        ];
    }

    /**
     * Get available missions (filtered by visibility and marked with decryption status)
     */
    public function getAvailableMissions(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-Session-Id') ?? session()->getId();
        $allMissions = $this->engine->getAvailableMissions($sessionId);

        $missionService = app(\App\Services\Mission\MissionService::class);

        // Filter to only visible missions
        $visibleMissions = $missionService->getAvailableJobsForBoard($sessionId, $allMissions);

        // Add 'decrypted' flag to each mission
        $missionsWithState = array_map(function($mission) use ($missionService) {
            return array_merge($mission, [
                'decrypted' => $missionService->isMissionDecrypted($mission['id'])
            ]);
        }, $visibleMissions);

        return response()->json([
            'success' => true,
            'missions' => $missionsWithState,
        ]);
    }

    /**
     * Mark a mission as decrypted
     */
    public function decrypt(string $missionId): JsonResponse
    {
        $missionService = app(\App\Services\Mission\MissionService::class);

        // Mark as decrypted
        $missionService->markMissionDecrypted($missionId);

        return response()->json([
            'success' => true,
            'missionId' => $missionId,
            'decrypted' => true
        ]);
    }

    /**
     * Get job offers
     */
    public function getJobOffers(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-Session-Id') ?? session()->getId();
        $offers = $this->engine->getJobOffers($sessionId);

        return response()->json([
            'offers' => $offers,
        ]);
    }
}
