<?php

declare(strict_types=1);

namespace App\Services\Mission;

/**
 * Mission Service — Tracks Act I mission progression.
 *
 * Owns all mission state: current mission, objective flags, credits, completion.
 * Mission state is now managed by the Kotlin engine; this service handles UI state.
 */
class MissionService
{
    private const TOTAL_MISSIONS = 5;

    /**
     * Default state for a fresh game.
     */
    private function defaultState(): array
    {
        return [
            'current_mission' => 1,
            'completed' => [],
            'credits' => 0,
            'act' => 1,
            'flags' => [
                // Mission 1: First Boot
                'm1_used_ls' => false,
                'm1_used_cd' => false,
                'm1_read_briefing' => false,
                'm1_used_mail' => false,
                // Mission 2: Ping Sweep
                'm2_ran_scan' => false,
                'm2_scanned_target' => false,
                // Mission 3: Breach
                'm3_connected' => false,
                'm3_opened_employees' => false,
                // Mission 4: Data Grab
                'm4_downloaded_creds' => false,
                'm4_disconnected' => false,
                'm4_read_local_creds' => false,
                // Mission 5: Deep Dive
                'm5_scanned_from_web' => false,
                'm5_connected_db' => false,
                'm5_downloaded_accounts' => false,
            ],
        ];
    }

    public function getState(): array
    {
        return session()->get('game_mission_state', $this->defaultState());
    }

    public function getCurrentMission(): ?int
    {
        return $this->getState()['current_mission'];
    }

    public function getCredits(): int
    {
        return $this->getState()['credits'];
    }

    public function isCompleted(int $mission): bool
    {
        return in_array($mission, $this->getState()['completed'], true);
    }

    /**
     * Track a player action and check if it completes any mission objectives.
     *
     * @param string $verb    The command verb (ls, cd, cat, etc.)
     * @param array  $args    Command arguments
     * @param array  $context Current game context (connectedTo, currentPath, etc.)
     * @return array|null     Returns completion data on mission complete, null otherwise
     */
    public function trackAction(string $verb, array $args, array $context): ?array
    {
        $state = $this->getState();
        $currentMission = $state['current_mission'];

        // No active mission (Act I complete)
        if ($currentMission === null) {
            return null;
        }

        $flagSet = false;
        $connectedTo = $context['connectedTo'] ?? null;
        $currentPath = $context['currentPath'] ?? '/home/user';
        $arg0 = $args[0] ?? null;

        switch ($currentMission) {
            case 1:
                $flagSet = $this->trackMission1($state, $verb, $arg0);
                break;
            case 2:
                $flagSet = $this->trackMission2($state, $verb, $arg0);
                break;
            case 3:
                $flagSet = $this->trackMission3($state, $verb, $arg0, $connectedTo, $currentPath, $context);
                break;
            case 4:
                $flagSet = $this->trackMission4($state, $verb, $arg0, $connectedTo, $currentPath, $context);
                break;
            case 5:
                $flagSet = $this->trackMission5($state, $verb, $arg0, $connectedTo, $context);
                break;
        }

        if (!$flagSet) {
            return null;
        }

        $this->saveState($state);

        // Check if mission is now complete
        if ($this->checkCompletion($currentMission, $state['flags'])) {
            return $this->completeMission($state, $currentMission);
        }

        return null;
    }

    private function trackMission1(array &$state, string $verb, ?string $arg0): bool
    {
        $changed = false;

        if ($verb === 'ls' && !$state['flags']['m1_used_ls']) {
            $state['flags']['m1_used_ls'] = true;
            $changed = true;
        }
        if ($verb === 'cd' && !$state['flags']['m1_used_cd']) {
            $state['flags']['m1_used_cd'] = true;
            $changed = true;
        }
        if ($verb === 'cat' && $arg0 === 'mission_briefing.txt' && !$state['flags']['m1_read_briefing']) {
            $state['flags']['m1_read_briefing'] = true;
            $changed = true;
        }
        if (($verb === 'mail' || $verb === 'inbox') && !$state['flags']['m1_used_mail']) {
            $state['flags']['m1_used_mail'] = true;
            $changed = true;
        }

        return $changed;
    }

    private function trackMission2(array &$state, string $verb, ?string $arg0): bool
    {
        $changed = false;

        if ($verb === 'scan' && $arg0 === null && !$state['flags']['m2_ran_scan']) {
            $state['flags']['m2_ran_scan'] = true;
            $changed = true;
        }
        if ($verb === 'scan' && $arg0 === '192.168.50.10' && !$state['flags']['m2_scanned_target']) {
            $state['flags']['m2_scanned_target'] = true;
            $changed = true;
        }

        return $changed;
    }

    private function trackMission3(array &$state, string $verb, ?string $arg0, ?string $connectedTo, string $currentPath, array $context): bool
    {
        $changed = false;

        // connect succeeded — check stateChanges for the actual connection result
        if ($verb === 'connect' && ($connectedTo === 'nova-corp-web' || ($context['stateChanges']['connectedTo'] ?? null) === 'nova-corp-web') && !$state['flags']['m3_connected']) {
            $state['flags']['m3_connected'] = true;
            $changed = true;
        }
        // Also catch solve — if player just solved the puzzle and got connected
        if ($verb === 'solve' && ($context['stateChanges']['connectedTo'] ?? null) === 'nova-corp-web' && !$state['flags']['m3_connected']) {
            $state['flags']['m3_connected'] = true;
            $changed = true;
        }
        if ($verb === 'open' && $arg0 === 'employees.db' && $connectedTo === 'nova-corp-web' && !$state['flags']['m3_opened_employees']) {
            $state['flags']['m3_opened_employees'] = true;
            $changed = true;
        }

        return $changed;
    }

    private function trackMission4(array &$state, string $verb, ?string $arg0, ?string $connectedTo, string $currentPath, array $context): bool
    {
        $changed = false;

        if ($verb === 'download' && $arg0 === 'credentials.dat' && !$state['flags']['m4_downloaded_creds']) {
            $state['flags']['m4_downloaded_creds'] = true;
            $changed = true;
        }
        if ($verb === 'disconnect' && $state['flags']['m4_downloaded_creds'] && !$state['flags']['m4_disconnected']) {
            $state['flags']['m4_disconnected'] = true;
            $changed = true;
        }
        if ($verb === 'cat' && $arg0 === 'credentials.dat' && $currentPath === '/home/user/downloads' && !$state['flags']['m4_read_local_creds']) {
            $state['flags']['m4_read_local_creds'] = true;
            $changed = true;
        }

        return $changed;
    }

    private function trackMission5(array &$state, string $verb, ?string $arg0, ?string $connectedTo, array $context): bool
    {
        $changed = false;

        if ($verb === 'scan' && $arg0 === null && $connectedTo === 'nova-corp-web' && !$state['flags']['m5_scanned_from_web']) {
            $state['flags']['m5_scanned_from_web'] = true;
            $changed = true;
        }
        if ($verb === 'connect' && ($connectedTo === 'nova-corp-db' || ($context['stateChanges']['connectedTo'] ?? null) === 'nova-corp-db') && !$state['flags']['m5_connected_db']) {
            $state['flags']['m5_connected_db'] = true;
            $changed = true;
        }
        if ($verb === 'solve' && ($context['stateChanges']['connectedTo'] ?? null) === 'nova-corp-db' && !$state['flags']['m5_connected_db']) {
            $state['flags']['m5_connected_db'] = true;
            $changed = true;
        }
        if ($verb === 'download' && $arg0 === 'accounts_export.csv' && !$state['flags']['m5_downloaded_accounts']) {
            $state['flags']['m5_downloaded_accounts'] = true;
            $changed = true;
        }

        return $changed;
    }

    /**
     * Check if all flags for a mission are complete.
     */
    private function checkCompletion(int $mission, array $flags): bool
    {
        $prefix = "m{$mission}_";
        foreach ($flags as $key => $value) {
            if (str_starts_with($key, $prefix) && $value === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Complete a mission: award credits, advance to next, return completion data.
     */
    private function completeMission(array &$state, int $mission): array
    {
        $reward = $this->getCompletionReward($mission);

        $state['completed'][] = $mission;
        $state['credits'] += $reward['credits'];

        if ($mission < self::TOTAL_MISSIONS) {
            $state['current_mission'] = $mission + 1;
        } else {
            $state['current_mission'] = null; // Act I complete
        }

        $this->saveState($state);

        return [
            'mission' => $mission,
            'credits' => $reward['credits'],
            'messages' => $this->getMissionMessages($mission),
        ];
    }

    /**
     * Get reward data for a completed mission.
     */
    private function getCompletionReward(int $mission): array
    {
        return match ($mission) {
            1 => ['credits' => 0],
            2 => ['credits' => 0],
            3 => ['credits' => 1500],
            4 => ['credits' => 2000],
            5 => ['credits' => 5000],
            default => ['credits' => 0],
        };
    }

    /**
     * Get messages to inject on mission completion.
     */
    private function getMissionMessages(int $mission): array
    {
        return match ($mission) {
            1 => [
                [
                    'id' => 101,
                    'from' => 'Ghost',
                    'fromId' => 'ghost',
                    'avatar' => "\u{1F47B}",
                    'subject' => 'Good. Now the real stuff.',
                    'preview' => "You know your way around. Here's the deal...",
                    'body' => "Operator,\n\nYou know your way around. Good.\n\nHere's the deal — I need someone to scope out a server for me. NovaCorp, a mid-tier corp with sloppy security and decent payroll data. Good money for a quick job.\n\nRun `scan` from your terminal to see what's on your local network. There's a public relay nearby — connect to it and scan again from there. That's how you reach NovaCorp's subnet. Once you spot their web server, port-scan the IP so I know what we're dealing with.\n\nDon't overthink it. Just start scanning.\n\n- Ghost",
                    'read' => false,
                    'timestamp' => 'Just now',
                    'datetime' => now()->format('Y-m-d H:i:s'),
                    'type' => 'contact',
                    'replyable' => false,
                ],
            ],
            2 => [
                [
                    'id' => 102,
                    'from' => 'Ghost',
                    'fromId' => 'ghost',
                    'avatar' => "\u{1F47B}",
                    'subject' => 'Nice recon',
                    'preview' => 'Good work on the scan. Ready for the real job?',
                    'body' => "Nice work on the recon. NovaCorp's web server is wide open — just like I thought.\n\nI've got a client who wants eyes on their employee database. Quick extraction job, low risk if you're careful.\n\n[secure://channel/nova-corp-recon|Open Secure Channel]\n\nOnce you accept, connect to the target and find the employee records. The data directory is your friend.\n\n- G",
                    'read' => false,
                    'timestamp' => 'Just now',
                    'datetime' => now()->format('Y-m-d H:i:s'),
                    'type' => 'job',
                    'replyable' => false,
                ],
                [
                    'id' => 103,
                    'from' => 'Unknown',
                    'fromId' => 'unknown',
                    'avatar' => "\u{2753}",
                    'subject' => "We've been watching",
                    'preview' => "Your skills haven't gone unnoticed...",
                    'body' => "Your skills haven't gone unnoticed.\n\nWe represent an organization that values... discretion. There may be opportunities for someone with your talents.\n\nWe'll be in touch.\n\n- \u{2588}\u{2588}\u{2588}\u{2588}\u{2588}\u{2588}\u{2588}",
                    'read' => false,
                    'timestamp' => 'Just now',
                    'datetime' => now()->format('Y-m-d H:i:s'),
                    'type' => 'mysterious',
                    'replyable' => false,
                ],
            ],
            3 => [
                [
                    'id' => 104,
                    'from' => 'Ghost',
                    'fromId' => 'ghost',
                    'avatar' => "\u{1F47B}",
                    'subject' => 'Confirmed. Now for the real prize.',
                    'preview' => 'Employee data received. But there\'s more...',
                    'body' => "Confirmed — the employee data checks out. Client's satisfied.\n\nBut here's the thing: while you were in there, I noticed something in the directory listing. There's a file called `credentials.dat` in the `/data` folder. That's the real prize — system passwords, API keys, the works.\n\nGet back in there, download `credentials.dat`, then disconnect and read it locally. Use `cat credentials.dat` in your `~/downloads` folder.\n\nThis is how we build leverage.\n\n- Ghost",
                    'read' => false,
                    'timestamp' => 'Just now',
                    'datetime' => now()->format('Y-m-d H:i:s'),
                    'type' => 'contact',
                    'replyable' => false,
                ],
                [
                    'id' => 105,
                    'from' => 'Cipher',
                    'fromId' => 'cipher',
                    'avatar' => "\u{1F510}",
                    'subject' => 'Heard you\'re moving up',
                    'preview' => 'Word travels fast. Got some tips...',
                    'body' => "Yo,\n\nWord travels fast in these circles. Heard you cracked into NovaCorp. Not bad for a newcomer.\n\nFew tips from someone who's been around:\n\n- Always check `/backup/` folders. Lazy admins dump gold there.\n- **LogWiper** is on sale in the store — cleans your traces after a job.\n- If you're going deeper into corporate networks, watch out for internal subnets. `10.0.x.x` ranges usually mean you've found the backend.\n\nStay sharp.\n\n- Cipher",
                    'read' => false,
                    'timestamp' => 'Just now',
                    'datetime' => now()->format('Y-m-d H:i:s'),
                    'type' => 'contact',
                    'replyable' => true,
                ],
            ],
            4 => [
                [
                    'id' => 106,
                    'from' => 'Ghost',
                    'fromId' => 'ghost',
                    'avatar' => "\u{1F47B}",
                    'subject' => 'Payday',
                    'preview' => 'Credentials confirmed. One more job...',
                    'body' => "Credentials confirmed. You're earning your keep.\n\nNow here's the big one. Those creds you pulled? One of them is a root password for NovaCorp's database server — `10.0.100.50`. That's where the financial records live.\n\nMy client wants the accounts data. It's a harder target — you'll need to get back into the web server first, then `scan` the internal network to find the database. Different security on that box, tougher puzzles.\n\n[secure://channel/nova-corp-db-heist|Open Secure Channel]\n\nThis is the real payday. Don't blow it.\n\n- Ghost",
                    'read' => false,
                    'timestamp' => 'Just now',
                    'datetime' => now()->format('Y-m-d H:i:s'),
                    'type' => 'job',
                    'replyable' => false,
                ],
            ],
            5 => [
                [
                    'id' => 107,
                    'from' => 'Ghost',
                    'fromId' => 'ghost',
                    'avatar' => "\u{1F47B}",
                    'subject' => "We're square",
                    'preview' => 'Job complete. You\'ve earned your reputation.',
                    'body' => "Accounts data delivered. Client is very happy.\n\nYou've done good work. We're square — for now. Your reputation in the underground just went up a few notches. More jobs will come, bigger targets, better payouts.\n\nTake a breather. Check your credits. And keep your eyes open — not everything in those files was just corporate data.\n\nSome things are better left alone. Others... well, that's your call.\n\n- Ghost",
                    'read' => false,
                    'timestamp' => 'Just now',
                    'datetime' => now()->format('Y-m-d H:i:s'),
                    'type' => 'contact',
                    'replyable' => false,
                ],
                [
                    'id' => 108,
                    'from' => 'Zer0',
                    'fromId' => 'zero',
                    'avatar' => "\u{1F480}",
                    'subject' => "I've got something big",
                    'preview' => 'Forget NovaCorp. I found something bigger...',
                    'body' => "Hey,\n\nI know we haven't talked much, but I've been watching your work. NovaCorp was small time.\n\nI've got a lead on something massive — a government contractor with zero-day vulnerabilities sitting wide open. The payout would be more than Ghost's jobs combined.\n\nI'm putting a team together. Interested?\n\nHit me back. This won't wait.\n\n- Zer0",
                    'read' => false,
                    'timestamp' => 'Just now',
                    'datetime' => now()->format('Y-m-d H:i:s'),
                    'type' => 'contact',
                    'replyable' => true,
                ],
                [
                    'id' => 109,
                    'from' => 'Unknown',
                    'fromId' => 'unknown',
                    'avatar' => "\u{2753}",
                    'subject' => 'You found the Meridian file',
                    'preview' => 'The data you pulled from NovaCorp contains something...',
                    'body' => "You found it.\n\nThe database you extracted from NovaCorp — look closer. There's a script in the DBA's home directory that syncs employee data to an external endpoint: `api.meridian-group.internal`.\n\nThat's not a NovaCorp system. That's **Project Meridian** — a domestic surveillance program built on corporate data pipelines. NovaCorp is just one source.\n\nThe name Erik Holst appears in their query logs. He was a NovaCorp contractor who found the same thing you did. His employment was terminated December 2023. His current status is... unknown.\n\nThis goes deeper than corporate espionage. SIGINT is involved.\n\nBe very careful who you trust.\n\n- [REDACTED]",
                    'read' => false,
                    'timestamp' => 'Just now',
                    'datetime' => now()->format('Y-m-d H:i:s'),
                    'type' => 'mysterious',
                    'replyable' => false,
                ],
            ],
            default => [],
        };
    }

    private function saveState(array $state): void
    {
        session()->put('game_mission_state', $state);
    }

    /**
     * Mark mission as visible on Jobs Board
     */
    public function markMissionVisible(string $missionId): void
    {
        $visible = session()->get('game_visible_missions', []);
        if (!in_array($missionId, $visible, true)) {
            $visible[] = $missionId;
            session()->put('game_visible_missions', $visible);
        }
    }

    /**
     * Mark mission as decrypted (details viewed)
     */
    public function markMissionDecrypted(string $missionId): void
    {
        $decrypted = session()->get('game_decrypted_missions', []);
        if (!in_array($missionId, $decrypted, true)) {
            $decrypted[] = $missionId;
            session()->put('game_decrypted_missions', $decrypted);
        }
    }

    /**
     * Get list of visible (but not necessarily decrypted) missions
     */
    public function getVisibleMissions(): array
    {
        return session()->get('game_visible_missions', []);
    }

    /**
     * Check if mission has been decrypted
     */
    public function isMissionDecrypted(string $missionId): bool
    {
        $decrypted = session()->get('game_decrypted_missions', []);
        return in_array($missionId, $decrypted, true);
    }

    /**
     * Get missions available for display on board
     * (intersection of unlocked from engine + visible via triggers)
     */
    public function getAvailableJobsForBoard(string $sessionId, array $allAvailable): array
    {
        // Filter to only those marked visible
        $visibleIds = $this->getVisibleMissions();

        return array_filter($allAvailable, function($mission) use ($visibleIds) {
            return in_array($mission['id'], $visibleIds, true);
        });
    }
}
