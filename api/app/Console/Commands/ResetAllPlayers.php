<?php

namespace App\Console\Commands;

use App\Models\Player;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Resets every player on the server back to factory defaults.
 *
 * Wipes: economy, bounty, quest progress, watcher messages, inventory
 *        (peripherals, commands, consumables), node traces, rig SS.
 * Preserves: user accounts, player handles, chassis templates.
 *
 * Usage:
 *   php artisan player:reset-all
 *   php artisan player:reset-all --force   # skip confirmation prompt
 */
class ResetAllPlayers extends Command
{
    protected $signature = 'player:reset-all
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Reset ALL players to fresh-start state (playtest use only)';

    public function handle(): int
    {
        $count = Player::count();

        if ($count === 0) {
            $this->warn('No players found.');
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("This will reset {$count} player(s) to factory defaults. Continue?")) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }
        }

        $this->info("Resetting {$count} player(s)...");
        $this->newLine();

        DB::transaction(function () {
            // ── Quest / story progress ────────────────────────────────────────
            DB::table('player_arc_progress')->delete();
            DB::table('player_stage_progress')->delete();
            DB::table('player_quest_log')->delete();
            DB::table('player_watcher_messages')->delete();
            DB::table('player_reputation')->delete();

            // ── Inventory ─────────────────────────────────────────────────────
            DB::table('player_peripherals')->delete();
            DB::table('player_commands')->delete();
            DB::table('player_consumables')->delete();

            // ── Map traces ────────────────────────────────────────────────────
            DB::table('node_traces')->delete();

            // ── Combat / challenge records ────────────────────────────────────
            DB::table('combat_challenges')->delete();

            // ── Economy + bounty + position ───────────────────────────────────
            DB::table('players')->update([
                'wallet_creds'             => 0,
                'pocket_creds'             => 0,
                'tech_points'              => 0,
                'cache'                    => 0,
                'bounty_level'             => 0,
                'nodes_hacked_this_run'    => 0,
                'pvp_wins_this_run'        => 0,
                'bounty_multiplier'        => 1.00,
                'bounty_district_snapshot' => null,
                'is_open_season'           => false,
                'open_season_wins'         => 0,
                'is_limping'               => false,
                'post_combat_silent_moves' => 0,
                'active_effects'           => null,
                'cyberdoc_cooldowns'       => null,
                'current_node_id'          => null,
                'current_district'         => 'DOWNTOWN',
                'last_cyber_doc_id'        => null,
                'tutorial_state'           => null,
                'persona'                  => null,
                'persona_desc'             => null,
                'updated_at'               => now(),
            ]);

            // ── Rigs — restore to BlackHat v1.0 base stats ────────────────────
            DB::table('player_rigs')->update([
                'cpu_level'      => 0,
                'ram_level'      => 0,
                'firewall_level' => 0,
                'storage_level'  => 0,
                'os_level'       => 0,
                'current_ss'     => 100,
                'is_limping'     => false,
                'current_uplink' => null,
                'updated_at'     => now(),
            ]);
        });

        $players = Player::all();
        foreach ($players as $p) {
            $this->info("✓ {$p->handle}");
        }

        $this->newLine();
        $this->info('✓ Economy reset        (wallet, pocket, tech_points, cache → 0)');
        $this->info('✓ Bounty reset         (level, multiplier, open_season → cleared)');
        $this->info('✓ Position reset       (node, district → DOWNTOWN)');
        $this->info('✓ Rigs reset           (all invested levels → 0, SS → 100)');
        $this->info('✓ Quest progress wiped (arc, stage, log, watcher messages, reputation)');
        $this->info('✓ Inventory wiped      (peripherals, commands, consumables)');
        $this->info('✓ Node traces wiped');
        $this->info('✓ Tutorial state wiped');
        $this->info('✓ Personas cleared     (everyone gets persona select + world tone on next login)');
        $this->newLine();
        $this->info("All {$count} player(s) reset. Ready for a fresh prologue run.");

        return self::SUCCESS;
    }
}
