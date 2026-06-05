<?php

namespace Database\Seeders;

use App\Models\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the full command catalog — 10 map commands + 12 hack commands.
 *
 * MAP commands are used during map traversal (trap placement, self-buffs, stealth).
 * HACK commands are used inside GridBreach or Packet Hijack PvP sessions only.
 *
 * Level scaling (all commands max level 3):
 *   L1 — base effect
 *   L2 — enhanced effect
 *   L3 — L2 effect + second use per run
 *
 * Commands reset at CyberDoc (single use per run unless L3).
 * Placement range for trap commands: 2–3 nodes max (never district-wide).
 */
class CommandSeeder extends Seeder
{
    public function run(): void
    {
        // ── Purge commands no longer in the catalog ───────────────────────────
        $catalogNames = array_column($this->catalog(), 'name');
        DB::table('commands')->whereNotIn('name', $catalogNames)->delete();

        foreach ($this->catalog() as $data) {
            $data['duration']      = $data['duration'] !== null ? json_encode($data['duration']) : null;
            $data['level_scaling'] = json_encode($data['level_scaling']);

            Command::updateOrCreate(['name' => $data['name']], $data);
        }

        $count = count($this->catalog());
        $this->command?->info("CommandSeeder: {$count} commands seeded.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Catalog
    // ─────────────────────────────────────────────────────────────────────────

    private function catalog(): array
    {
        return [

            // ══════════════════════════════════════════════════════════════════
            // MAP COMMANDS  (11)
            // Used during map traversal. Context = 'map'.
            // gridbreach_effect and packethijack_effect are null for map commands.
            // ══════════════════════════════════════════════════════════════════

            [
                'name'                => 'Ghost Protocol',
                'context'             => 'map',
                'type'                => 'stealth',
                'target_type'         => 'self',
                'price_creds'         => 400,
                'price_tp'            => 3,
                'upgrade_cost_tp'     => 3,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Suppress your movement trail, moving freely without generating ICE pings.',
                'map_effect'          => 'Move without generating ICE pings or leaving a movement trace. L1: 2 free silent moves. L2: 3 free silent moves.',
                'gridbreach_effect'   => null,
                'packethijack_effect' => null,
                'level_scaling'       => [
                    '1' => ['moves' => 2],
                    '2' => ['moves' => 3],
                    '3' => ['moves' => 3, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Dark Mode',
                'context'             => 'map',
                'type'                => 'stealth',
                'target_type'         => 'self',
                'price_creds'         => 600,
                'price_tp'            => 5,
                'upgrade_cost_tp'     => 5,
                'max_level'           => 3,
                'duration'            => ['minutes' => 1],
                'description'         => 'Completely suppresses all ICE pings you generate for a short window.',
                'map_effect'          => 'Suppresses all ICE pings you generate for the duration. L1: 60 seconds. L2: 90 seconds.',
                'gridbreach_effect'   => null,
                'packethijack_effect' => null,
                'level_scaling'       => [
                    '1' => ['seconds' => 60],
                    '2' => ['seconds' => 90],
                    '3' => ['seconds' => 90, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Signal Noise',
                'context'             => 'map',
                'type'                => 'stealth',
                'target_type'         => 'self',
                'price_creds'         => 300,
                'price_tp'            => 2,
                'upgrade_cost_tp'     => 2,
                'max_level'           => 3,
                'duration'            => ['moves' => 2],
                'description'         => 'Plants false pings at your current position to mask your location.',
                'map_effect'          => 'Plants false pings at your current position that persist for a number of your moves. L1: 1 false ping, lasts 2 moves. L2: 2 false pings, lasts 3 moves.',
                'gridbreach_effect'   => null,
                'packethijack_effect' => null,
                'level_scaling'       => [
                    '1' => ['pings' => 1, 'moves' => 2],
                    '2' => ['pings' => 2, 'moves' => 3],
                    '3' => ['pings' => 2, 'moves' => 3, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Crash',
                'context'             => 'map',
                'type'                => 'trap',
                'target_type'         => 'node',
                'price_creds'         => 350,
                'price_tp'            => 2,
                'upgrade_cost_tp'     => 2,
                'max_level'           => 3,
                'duration'            => ['moves' => 5, 'minutes' => 5],
                'description'         => 'Places a trap on a node that drains the next visitor\'s Uplink.',
                'map_effect'          => 'The first player to visit the trapped node loses Uplink. Trap expires after 5 of your moves or 5 minutes. L1: 1 Uplink drained. L2: 2 Uplink drained.',
                'gridbreach_effect'   => null,
                'packethijack_effect' => null,
                'level_scaling'       => [
                    '1' => ['uplink_drain' => 1],
                    '2' => ['uplink_drain' => 2],
                    '3' => ['uplink_drain' => 2, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Packet Flood',
                'context'             => 'map',
                'type'                => 'trap',
                'target_type'         => 'node',
                'price_creds'         => 500,
                'price_tp'            => 4,
                'upgrade_cost_tp'     => 4,
                'max_level'           => 3,
                'duration'            => ['moves' => 5, 'minutes' => 5],
                'description'         => 'Places a trap that deals SS damage to the next player who visits.',
                'map_effect'          => 'The first player to visit the trapped node takes SS damage. Trap expires after 5 of your moves or 5 minutes. L1: 10 SS damage. L2: 15 SS damage.',
                'gridbreach_effect'   => null,
                'packethijack_effect' => null,
                'level_scaling'       => [
                    '1' => ['ss_damage' => 10],
                    '2' => ['ss_damage' => 15],
                    '3' => ['ss_damage' => 15, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'OS Exploit',
                'context'             => 'map',
                'type'                => 'trap',
                'target_type'         => 'node',
                'price_creds'         => 500,
                'price_tp'            => 4,
                'upgrade_cost_tp'     => 4,
                'max_level'           => 3,
                'duration'            => ['moves' => 5, 'minutes' => 5],
                'description'         => 'Places a trap that temporarily reduces the next visitor\'s OS stat.',
                'map_effect'          => 'The next player to visit the trapped node has their OS reduced for a number of their moves. Trap expires after 5 of your moves or 5 minutes. L1: OS −1 for 3 moves. L2: OS −2 for 4 moves.',
                'gridbreach_effect'   => null,
                'packethijack_effect' => null,
                'level_scaling'       => [
                    '1' => ['os_reduction' => 1, 'moves' => 3],
                    '2' => ['os_reduction' => 2, 'moves' => 4],
                    '3' => ['os_reduction' => 2, 'moves' => 4, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Blackout',
                'context'             => 'map',
                'type'                => 'defensive',
                'target_type'         => 'self',
                'price_creds'         => 450,
                'price_tp'            => 3,
                'upgrade_cost_tp'     => 3,
                'max_level'           => 3,
                'duration'            => ['moves' => 2],
                'description'         => 'Shields you from incoming player challenges.',
                'map_effect'          => 'Blocks incoming PvP challenges for a number of your moves. L1: 2 moves. L2: 3 moves.',
                'gridbreach_effect'   => null,
                'packethijack_effect' => null,
                'level_scaling'       => [
                    '1' => ['moves' => 2],
                    '2' => ['moves' => 3],
                    '3' => ['moves' => 3, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Buffer Overflow',
                'context'             => 'map',
                'type'                => 'trap',
                'target_type'         => 'node',
                'price_creds'         => 550,
                'price_tp'            => 4,
                'upgrade_cost_tp'     => 4,
                'max_level'           => 3,
                'duration'            => ['moves' => 5, 'minutes' => 5],
                'description'         => 'Places a trap that disables a random command in the next visitor\'s loadout.',
                'map_effect'          => 'The next player to visit the trapped node has 1 random command in their loadout disabled for a number of their moves. Trap expires after 5 of your moves or 5 minutes. L1: disabled for 2 moves. L2: disabled for 3 moves.',
                'gridbreach_effect'   => null,
                'packethijack_effect' => null,
                'level_scaling'       => [
                    '1' => ['moves' => 2],
                    '2' => ['moves' => 3],
                    '3' => ['moves' => 3, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'RootKit',
                'context'             => 'map',
                'type'                => 'trap',
                'target_type'         => 'node',
                'price_creds'         => 600,
                'price_tp'            => 5,
                'upgrade_cost_tp'     => 5,
                'max_level'           => 3,
                'duration'            => ['moves' => 5, 'minutes' => 5],
                'description'         => 'Places a trap that locks the next visitor out of all their commands.',
                'map_effect'          => 'The next player to visit the trapped node cannot use any commands for a number of their moves. Trap expires after 5 of your moves or 5 minutes. L1: locked for 2 moves. L2: locked for 3 moves.',
                'gridbreach_effect'   => null,
                'packethijack_effect' => null,
                'level_scaling'       => [
                    '1' => ['moves' => 2],
                    '2' => ['moves' => 3],
                    '3' => ['moves' => 3, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Decoy',
                'context'             => 'map',
                'type'                => 'stealth',
                'target_type'         => 'node',
                'price_creds'         => 400,
                'price_tp'            => 3,
                'upgrade_cost_tp'     => 3,
                'max_level'           => 3,
                'duration'            => ['moves' => 3],
                'description'         => 'Plants a false hack trace and ping at a nearby node, triggering after your next move.',
                'map_effect'          => 'Places a fake hack trace at a target node within range. Triggers after your next move, making it appear you hacked that node — useful for misdirecting ICE under bounty. L1: range 2 nodes, lasts 3 moves. L2: range 3 nodes, lasts 5 moves.',
                'gridbreach_effect'   => null,
                'packethijack_effect' => null,
                'level_scaling'       => [
                    '1' => ['range_nodes' => 2, 'lasts_moves' => 3],
                    '2' => ['range_nodes' => 3, 'lasts_moves' => 5],
                    '3' => ['range_nodes' => 3, 'lasts_moves' => 5, 'extra_use' => true],
                ],
            ],

            // ══════════════════════════════════════════════════════════════════
            // HACK COMMANDS  (12)
            // Used inside GridBreach or Packet Hijack PvP sessions only.
            // Context = 'hack'. map_effect is null for hack commands.
            // ══════════════════════════════════════════════════════════════════

            [
                'name'                => 'Hardlock',
                'context'             => 'hack',
                'type'                => 'offensive',
                'target_type'         => 'player',
                'price_creds'         => 400,
                'price_tp'            => 3,
                'upgrade_cost_tp'     => 3,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Freezes the opponent\'s input for a short window.',
                'map_effect'          => null,
                'gridbreach_effect'   => null,
                'packethijack_effect' => 'Opponent\'s terminal input is fully locked for the duration. L1: 2.5 seconds. L2: 3.5 seconds.',
                'level_scaling'       => [
                    '1' => ['seconds' => 2.5],
                    '2' => ['seconds' => 3.5],
                    '3' => ['seconds' => 3.5, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Sector Corrupt',
                'context'             => 'hack',
                'type'                => 'offensive',
                'target_type'         => 'player',
                'price_creds'         => 500,
                'price_tp'            => 4,
                'upgrade_cost_tp'     => 4,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Glitches one or two rows on the opponent\'s board for 10 seconds.',
                'map_effect'          => null,
                'gridbreach_effect'   => null,
                'packethijack_effect' => 'Wipes revealed intel (ping, arp, traceroute, whois) from up to 2 suspects in the opponent\'s active Phase 1 case file — they must re-investigate those entries. L1: wipes 2 suspects. L2: wipes 3 suspects.',
                'level_scaling'       => [
                    '1' => ['rows' => 1, 'seconds' => 10],
                    '2' => ['rows' => 2, 'seconds' => 10],
                    '3' => ['rows' => 2, 'seconds' => 10, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Null Byte',
                'context'             => 'hack',
                'type'                => 'offensive',
                'target_type'         => 'player',
                'price_creds'         => 450,
                'price_tp'            => 3,
                'upgrade_cost_tp'     => 3,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Removes hexakeys from the opponent\'s current sequence, forcing a board reseed.',
                'map_effect'          => null,
                'gridbreach_effect'   => null,
                'packethijack_effect' => 'Injects fresh decoy IPs directly into the opponent\'s active Phase 1 suspect list — they appear as uninvestigated entries that must be worked through. L1: 1 decoy. L2: 2 decoys.',
                'level_scaling'       => [
                    '1' => ['hexakeys' => 1],
                    '2' => ['hexakeys' => 2],
                    '3' => ['hexakeys' => 2, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Static Burst',
                'context'             => 'hack',
                'type'                => 'offensive',
                'target_type'         => 'player',
                'price_creds'         => 350,
                'price_tp'            => 2,
                'upgrade_cost_tp'     => 2,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Inverts the opponent\'s grid colors to cause visual disorientation.',
                'map_effect'          => null,
                'gridbreach_effect'   => null,
                'packethijack_effect' => 'Floods the attacker\'s terminal with garbage output, obscuring all readable data. L1: 2 seconds. L2: 3 seconds.',
                'level_scaling'       => [
                    '1' => ['seconds' => 2.5],
                    '2' => ['seconds' => 3.5],
                    '3' => ['seconds' => 3.5, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Trace Route',
                'context'             => 'hack',
                'type'                => 'defensive',
                'target_type'         => 'self',
                'price_creds'         => 300,
                'price_tp'            => 2,
                'upgrade_cost_tp'     => 2,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Delays your next board scramble, protecting current sequence progress.',
                'map_effect'          => null,
                'gridbreach_effect'   => null,
                'packethijack_effect' => 'Phase 1: Reveals the first octet of the target IP (e.g. 192.x.x.x), narrowing your search to one RFC-1918 range. Phase 2: Auto-confirms 1 (L1) or 2 (L2) chain adjacencies — outputs confirmed port pairs to your terminal without consuming trace attempts.',
                'level_scaling'       => [
                    '1' => ['delay_seconds' => 4],
                    '2' => ['delay_seconds' => 7],
                    '3' => ['delay_seconds' => 7, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Phase Shift',
                'context'             => 'hack',
                'type'                => 'defensive',
                'target_type'         => 'self',
                'price_creds'         => 500,
                'price_tp'            => 4,
                'upgrade_cost_tp'     => 4,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Reactively breaks free from any active opponent command effect.',
                'map_effect'          => null,
                'gridbreach_effect'   => null,
                'packethijack_effect' => 'Interrupts the attacker\'s active cascade, reverting their most recent successful port exploit.',
                'level_scaling'       => [
                    '1' => ['immunity_seconds' => 0],
                    '2' => ['immunity_seconds' => 2],
                    '3' => ['immunity_seconds' => 2, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Overclock',
                'context'             => 'hack',
                'type'                => 'defensive',
                'target_type'         => 'self',
                'price_creds'         => 450,
                'price_tp'            => 3,
                'upgrade_cost_tp'     => 3,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Temporarily widens your hexakey input window.',
                'map_effect'          => null,
                'gridbreach_effect'   => null,
                'packethijack_effect' => 'Grants a chain-skip for your next exploit — you can shatter any remaining chain port regardless of order, bypassing chain-head enforcement. Consumed on your next exploit attempt.',
                'level_scaling'       => [
                    '1' => ['input_boost_pct' => 35, 'seconds' => 5],
                    '2' => ['input_boost_pct' => 50, 'seconds' => 7],
                    '3' => ['input_boost_pct' => 50, 'seconds' => 7, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Mirror Protocol',
                'context'             => 'hack',
                'type'                => 'offensive',
                'target_type'         => 'self',
                'price_creds'         => 700,
                'price_tp'            => 6,
                'upgrade_cost_tp'     => 6,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Auto-completes your current hexakey sequence for a free score.',
                'map_effect'          => null,
                'gridbreach_effect'   => null,
                'packethijack_effect' => 'Activates a mirror shield. The next opponent rig command is reflected: if it was a self-buff they used, you receive the same buff; if it was an attack against you, it also fires back on them. Consumed on first trigger.',
                'level_scaling'       => [
                    '1' => ['scramble_delay_seconds' => 0],
                    '2' => ['scramble_delay_seconds' => 3],
                    '3' => ['scramble_delay_seconds' => 3, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Data Spike',
                'context'             => 'hack',
                'type'                => 'offensive',
                'target_type'         => 'player',
                'price_creds'         => 400,
                'price_tp'            => 3,
                'upgrade_cost_tp'     => 3,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Highlights your next correct hexakey targets and widens the input window for those inputs.',
                'map_effect'          => null,
                'gridbreach_effect'   => null,
                'packethijack_effect' => 'Offensive — phase-aware. Phase 1: injects decoy IPs into the opponent\'s active suspect list. Phase 2: injects dead-end ports into the opponent\'s port topology. L1: 1 injection. L2: 2 injections.',
                'level_scaling'       => [
                    '1' => ['hexakeys_highlighted' => 1, 'window_boost_pct' => 50],
                    '2' => ['hexakeys_highlighted' => 2, 'window_boost_pct' => 50],
                    '3' => ['hexakeys_highlighted' => 2, 'window_boost_pct' => 50, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Phantom Key',
                'context'             => 'hack',
                'type'                => 'offensive',
                'target_type'         => 'player',
                'price_creds'         => 550,
                'price_tp'            => 4,
                'upgrade_cost_tp'     => 4,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Injects extra false steps into the opponent\'s current sequence.',
                'map_effect'          => null,
                'gridbreach_effect'   => null,
                'packethijack_effect' => 'Adds extra ports to the attacker\'s Phase 2 cascade that must be broken before the final payload can execute. L1: 1 extra port. L2: 2 extra ports.',
                'level_scaling'       => [
                    '1' => ['extra_steps' => 1],
                    '2' => ['extra_steps' => 2],
                    '3' => ['extra_steps' => 2, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Sector Purge',
                'context'             => 'hack',
                'type'                => 'offensive',
                'target_type'         => 'player',
                'price_creds'         => 650,
                'price_tp'            => 5,
                'upgrade_cost_tp'     => 5,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Forces rapid successive scrambles on the opponent\'s board.',
                'map_effect'          => null,
                'gridbreach_effect'   => null,
                'packethijack_effect' => 'Resets up to 1 (L1) or 2 (L2) of the opponent\'s probed ports back to unprobed — they must re-probe those ports before they can attempt to exploit them.',
                'level_scaling'       => [
                    '1' => ['scrambles' => 3, 'interval_seconds' => 0.5],
                    '2' => ['scrambles' => 4, 'interval_seconds' => 0.5],
                    '3' => ['scrambles' => 4, 'interval_seconds' => 0.5, 'extra_use' => true],
                ],
            ],

            [
                'name'                => 'Bait',
                'context'             => 'hack',
                'type'                => 'defensive',
                'target_type'         => 'self',
                'price_creds'         => 500,
                'price_tp'            => 4,
                'upgrade_cost_tp'     => 4,
                'max_level'           => 3,
                'duration'            => null,
                'description'         => 'Plants a honeypot that penalises the opponent if they target it.',
                'map_effect'          => null,
                'gridbreach_effect'   => null,
                'packethijack_effect' => 'Marks one port with an artificially low bias reading. If the attacker runs exploit on it, their terminal locks for the penalty duration. L1: locks for 2 seconds. L2: locks for 3 seconds.',
                'level_scaling'       => [
                    '1' => ['lock_seconds' => 2],
                    '2' => ['lock_seconds' => 3],
                    '3' => ['lock_seconds' => 3, 'extra_use' => true],
                ],
            ],

        ];
    }
}