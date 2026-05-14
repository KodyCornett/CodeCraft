<?php

namespace Database\Seeders;

use App\Models\Command;
use Illuminate\Database\Seeder;

/**
 * Seeds the commands catalog.
 *
 * 12 offensive commands, 1 defensive command (Blackout).
 * Movement commands are stubs for now.
 */
class CommandSeeder extends Seeder
{
    public function run(): void
    {
        $commands = [
            // ---- Offensive ----
            [
                'name'        => 'Databomb',
                'type'        => 'offensive',
                'description' => 'Plants a false hit marker on the opponent\'s board. Has a subtle visual tell. Opponent may waste shots chasing a fake ship location.',
            ],
            [
                'name'        => 'Overload',
                'type'        => 'offensive',
                'description' => 'Displaces the opponent\'s next shot randomly near their intended target. The displacement is silent — they don\'t know it happened.',
            ],
            [
                'name'        => 'Crash',
                'type'        => 'offensive',
                'description' => 'Scans a full row on the opponent\'s grid. Returns green (ship present in row) or red (clear) — visible only to you.',
            ],
            [
                'name'        => 'Trojan',
                'type'        => 'offensive',
                'description' => 'Hides inside one of your own ships. When that ship is sunk, a negative effect triggers on the opponent. They don\'t know it\'s there.',
            ],
            [
                'name'        => 'Exploit',
                'type'        => 'offensive',
                'description' => 'Passive trap. The next time the opponent hits one of your ships, you immediately receive a free retaliatory shot.',
            ],
            [
                'name'        => 'Packet Flood',
                'type'        => 'offensive',
                'description' => 'The opponent\'s next 3 shots each carry a 5-second response timer. Miss the timer and that shot is forfeited.',
            ],
            [
                'name'        => 'Scramble',
                'type'        => 'offensive',
                'description' => 'Wipes the opponent\'s shot-history markers from their board for up to 3 of their shots. Ends early if they score a hit.',
            ],
            [
                'name'        => 'SQL_Injection',
                'type'        => 'offensive',
                'description' => 'Applied to one of your ships (3+ squares) at match start. The opponent\'s first hit on that ship reads as a miss. Revealed when they hit it again on a different square.',
            ],
            [
                'name'        => 'Fork Bomb',
                'type'        => 'offensive',
                'description' => 'If the initial shot hits, a second shot fires on an adjacent square of your choosing. Both must be declared together.',
            ],
            [
                'name'        => 'DDOS FOG',
                'type'        => 'offensive',
                'description' => 'Blocks a 2×2 area on the opponent\'s grid for 2 of their shots. Shots into the fogged zone receive "Signal Blocked" — no permanent marker is left.',
            ],
            [
                'name'        => 'RootKit Install',
                'type'        => 'offensive',
                'description' => 'If you sink a ship while this is active, you steal one use of a randomly chosen unused command from the opponent. DDOS FOG can block the kill shot and prevent the trigger.',
            ],
            [
                'name'        => 'Buffer Overflow',
                'type'        => 'offensive',
                'description' => 'If your next shot hits, one of the opponent\'s unused commands is silently disabled for the rest of combat. They don\'t know which command was blocked.',
            ],

            // ---- Defensive ----
            [
                'name'        => 'Blackout',
                'type'        => 'defensive',
                'description' => 'Silences all incoming offensive commands for 3 of the opponent\'s turns. Their commands fire and go on cooldown but have no effect. They are not notified.',
            ],
        ];

        foreach ($commands as $data) {
            Command::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
