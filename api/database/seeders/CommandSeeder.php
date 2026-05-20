<?php

namespace Database\Seeders;

use App\Models\Command;
use Illuminate\Database\Seeder;

/**
 * Seeds the full command catalog.
 *
 * Each command has both a map_effect (what it does during traversal)
 * and a hack_effect (what it does inside a Grid-Breach session).
 * Using a command in either context puts it on cooldown until CyberDoc.
 *
 * Tiers gate equipping: player RAM must equal or exceed the command tier.
 * Duration is stored as JSON — null means instant/permanent until CyberDoc.
 */
class CommandSeeder extends Seeder
{
    public function run(): void
    {
        $commands = [

            // ── Tier 1 ────────────────────────────────────────────────────────

            [
                'name'        => 'Crash',
                'tier'        => 1,
                'type'        => 'trap',
                'price_creds' => 200,
                'price_tp'    => 1,
                'target_type' => 'node',
                'duration'    => ['moves' => 5, 'minutes' => 5],
                'description' => 'Places a trap on a node that fires on the next visitor.',
                'map_effect'  => 'Place a mine on a node. First player to visit loses 1 Uplink. Expires after 5 of your moves or 5 minutes, whichever comes first.',
                'hack_effect' => "Hides opponent's hexakeys for 2 seconds.",
            ],
            [
                'name'        => 'Signal Noise',
                'tier'        => 1,
                'type'        => 'stealth',
                'price_creds' => 200,
                'price_tp'    => 1,
                'target_type' => 'self',
                'duration'    => ['moves' => 5, 'minutes' => 5],
                'description' => 'Plants false ICE pings to mask your real position.',
                'map_effect'  => 'Plants 2 false pings in a nearby district. Fades after 5 of your moves or 5 minutes.',
                'hack_effect' => "Adds 2 ghost hexakeys to opponent's board.",
            ],
            [
                'name'        => 'Firewall Patch',
                'tier'        => 1,
                'type'        => 'defensive',
                'price_creds' => 200,
                'price_tp'    => 1,
                'target_type' => 'self',
                'duration'    => ['moves' => 3],
                'description' => 'Temporary firewall boost for 3 moves.',
                'map_effect'  => 'Boost your Firewall +2 for your next 3 moves.',
                'hack_effect' => 'Shields your hexakeys from disruption for 1 round.',
            ],

            // ── Tier 2 ────────────────────────────────────────────────────────

            [
                'name'        => 'Ghost Protocol',
                'tier'        => 2,
                'type'        => 'stealth',
                'price_creds' => 450,
                'price_tp'    => 3,
                'target_type' => 'self',
                'duration'    => ['moves' => 3],
                'description' => 'Suppresses your movement trail, leaving no ICE trace.',
                'map_effect'  => 'Masks your movement trail for 3 moves — you leave no ping traces.',
                'hack_effect' => 'Your hexakey inputs leave no readable trace for 1 round.',
            ],
            [
                'name'        => 'Packet Flood',
                'tier'        => 2,
                'type'        => 'offensive',
                'price_creds' => 450,
                'price_tp'    => 3,
                'target_type' => 'player',
                'duration'    => null,
                'description' => "Drains a target player's Uplink.",
                'map_effect'  => "Instantly drains target player's Uplink by 2.",
                'hack_effect' => "Slows opponent's input timer by 30% for 1 round.",
            ],
            [
                'name'        => 'Decoy',
                'tier'        => 2,
                'type'        => 'stealth',
                'price_creds' => 450,
                'price_tp'    => 3,
                'target_type' => 'node',
                'duration'    => ['moves' => 5, 'minutes' => 5],
                'description' => 'Plants a single fake ping at any node on the map.',
                'map_effect'  => 'Plants a single fake ping at any node on the map. Fades after 5 of your moves or 5 minutes.',
                'hack_effect' => "Creates a duplicate false hexakey on opponent's board for 2 rounds.",
            ],

            // ── Tier 3 ────────────────────────────────────────────────────────

            [
                'name'        => 'Dark Mode',
                'tier'        => 3,
                'type'        => 'stealth',
                'price_creds' => 800,
                'price_tp'    => 6,
                'target_type' => 'self',
                'duration'    => ['moves' => 2],
                'description' => 'Completely suppresses ICE pings for 2 moves.',
                'map_effect'  => 'Suppresses all ICE pings you generate for your next 2 moves.',
                'hack_effect' => "Blacks out a section of opponent's grid for 3 seconds.",
            ],
            [
                'name'        => 'Blackout',
                'tier'        => 3,
                'type'        => 'defensive',
                'price_creds' => 800,
                'price_tp'    => 6,
                'target_type' => 'self',
                'duration'    => ['moves' => 2],
                'description' => 'Blocks all incoming player commands.',
                'map_effect'  => "Blocks all incoming player commands for your next 2 moves.",
                'hack_effect' => 'Immune to all opponent command effects for 1 full round.',
            ],
            [
                'name'        => 'Scramble',
                'tier'        => 3,
                'type'        => 'offensive',
                'price_creds' => 800,
                'price_tp'    => 6,
                'target_type' => 'player',
                'duration'    => ['moves' => 2],
                'description' => "Scrambles a target's node resource readouts.",
                'map_effect'  => "Target player's node resource values display as ??? for 2 of their moves.",
                'hack_effect' => "Shuffles all of opponent's hexakeys into random order.",
            ],

            // ── Tier 4 ────────────────────────────────────────────────────────

            [
                'name'        => 'Trojan',
                'tier'        => 4,
                'type'        => 'offensive',
                'price_creds' => 1400,
                'price_tp'    => 10,
                'target_type' => 'player',
                'duration'    => ['hacks' => 3],
                'description' => 'Embeds in a target and increases their cache cost.',
                'map_effect'  => "Embeds in target — they gain +1 cache cost per hack for their next 3 hacks.",
                'hack_effect' => "Copies opponent's next hexakey input and applies it for your own use.",
            ],
            [
                'name'        => 'OS Exploit',
                'tier'        => 4,
                'type'        => 'offensive',
                'price_creds' => 1400,
                'price_tp'    => 10,
                'target_type' => 'player',
                'duration'    => ['moves' => 5],
                'description' => "Lowers a target's OS, making their ICE pings more accurate.",
                'map_effect'  => "Lowers target OS by 2 for 5 of their moves — ICE pings on them become more accurate.",
                'hack_effect' => "Reveals opponent's full hexakey sequence for 2 seconds.",
            ],
            [
                'name'        => 'Buffer Overflow',
                'tier'        => 4,
                'type'        => 'offensive',
                'price_creds' => 1400,
                'price_tp'    => 10,
                'target_type' => 'player',
                'duration'    => ['moves' => 2],
                'description' => "Disables a random command in a target's loadout.",
                'map_effect'  => "Disables one random command in target's loadout for 2 of their moves.",
                'hack_effect' => 'Forces opponent to immediately discard their current hexakey.',
            ],

            // ── Tier 5 ────────────────────────────────────────────────────────

            [
                'name'        => 'RootKit',
                'tier'        => 5,
                'type'        => 'offensive',
                'price_creds' => 2500,
                'price_tp'    => 18,
                'target_type' => 'player',
                'duration'    => null,
                'description' => "Steals Tech Points earned from a target's last hack.",
                'map_effect'  => "Steals all Tech Points the target earned from their last node hack.",
                'hack_effect' => "Steals one of opponent's hexakeys and adds it to your own set.",
            ],
            [
                'name'        => 'DDOS',
                'tier'        => 5,
                'type'        => 'offensive',
                'price_creds' => 2500,
                'price_tp'    => 18,
                'target_type' => 'player',
                'duration'    => ['moves' => 2],
                'description' => "Locks a target out of all hacking for 2 of their moves.",
                'map_effect'  => "Locks target out of all hacking for 2 of their moves.",
                'hack_effect' => "Freezes opponent's entire board for 3 seconds.",
            ],
            [
                'name'        => 'Fork Bomb',
                'tier'        => 5,
                'type'        => 'offensive',
                'price_creds' => 2500,
                'price_tp'    => 18,
                'target_type' => 'player',
                'duration'    => null,
                'description' => "Fills a target's cache to maximum, forcing a CyberDoc retreat.",
                'map_effect'  => "Instantly fills target's cache to maximum — forces them to retreat to CyberDoc to continue hacking.",
                'hack_effect' => "Splits opponent's board into two conflicting states simultaneously for 4 seconds.",
            ],
        ];

        foreach ($commands as $data) {
            // duration needs to be JSON-encoded for storage
            $duration = $data['duration'];
            $data['duration'] = $duration !== null ? json_encode($duration) : null;

            Command::updateOrCreate(['name' => $data['name']], $data);
        }

        $this->command?->info('CommandSeeder: ' . count($commands) . ' commands seeded.');
    }
}
