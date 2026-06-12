<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Resets a player back to factory defaults for testing.
 *
 * Wipes: economy, bounty, quest progress, watcher messages, inventory
 *        (peripherals, commands, consumables), node traces, rig SS.
 * Preserves: user account, player handle, chassis template.
 *
 * Usage:
 *   php artisan player:reset                         # resets test@example.com
 *   php artisan player:reset --email=other@test.com  # resets any player
 */
class ResetPlayer extends Command
{
    protected $signature = 'player:reset
                            {--email=test@example.com : The user email of the player to reset}';

    protected $description = 'Reset a player to fresh-start state for testing';

    public function handle(): int
    {
        $email = $this->option('email');

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("No user found for email: {$email}");
            return self::FAILURE;
        }

        $player = \App\Models\Player::where('user_id', $user->id)->first();
        if (!$player) {
            $this->error("No player found for user: {$email}");
            return self::FAILURE;
        }

        $playerId = $player->id;
        $this->info("Resetting player: {$player->handle} ({$playerId})");

        DB::transaction(function () use ($playerId) {

            // ── Quest / story progress ────────────────────────────────────────
            DB::table('player_arc_progress')->where('player_id', $playerId)->delete();
            DB::table('player_stage_progress')->where('player_id', $playerId)->delete();
            DB::table('player_quest_log')->where('player_id', $playerId)->delete();
            DB::table('player_watcher_messages')->where('player_id', $playerId)->delete();
            DB::table('player_reputation')->where('player_id', $playerId)->delete();

            // ── Inventory ─────────────────────────────────────────────────────
            DB::table('player_peripherals')->where('player_id', $playerId)->delete();
            DB::table('player_commands')->where('player_id', $playerId)->delete();
            DB::table('player_consumables')->where('player_id', $playerId)->delete();

            // ── Map traces left by this player ────────────────────────────────
            DB::table('node_traces')->where('player_id', $playerId)->delete();

            // ── Combat / challenge records ────────────────────────────────────
            DB::table('combat_challenges')
                ->where('challenger_id', $playerId)
                ->orWhere('target_id', $playerId)
                ->delete();

            // ── Economy + bounty + position ───────────────────────────────────
            DB::table('players')->where('id', $playerId)->update([
                'wallet_creds'            => 0,
                'pocket_creds'            => 0,
                'tech_points'             => 0,
                'cache'                   => 0,
                'bounty_level'            => 0,
                'nodes_hacked_this_run'   => 0,
                'pvp_wins_this_run'       => 0,
                'bounty_multiplier'       => 1.00,
                'bounty_district_snapshot'=> null,
                'is_open_season'          => false,
                'open_season_wins'        => 0,
                'is_limping'              => false,
                'post_combat_silent_moves'=> 0,
                'active_effects'          => null,
                'cyberdoc_cooldowns'      => null,
                'current_node_id'         => null,
                'current_district'        => 'DOWNTOWN',
                'last_cyber_doc_id'        => null,
                'tutorial_state'           => null,
                'persona'                  => null,
                'persona_desc'             => null,
                'updated_at'               => now(),
            ]);

            // ── Rig — restore to BlackHat v1.0 base stats ─────────────────────
            DB::table('player_rigs')->where('player_id', $playerId)->update([
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

        $this->info('✓ Economy reset       (wallet, pocket, tech_points, cache → 0)');
        $this->info('✓ Bounty reset        (level, multiplier, open_season → cleared)');
        $this->info('✓ Position reset      (node, district → DOWNTOWN)');
        $this->info('✓ Rig reset           (all invested levels → 0, SS → 100)');
        $this->info('✓ Quest progress wiped (arc, stage, log, watcher messages, reputation)');
        $this->info('✓ Inventory wiped     (peripherals, commands, consumables)');
        $this->info('✓ Node traces wiped');
        $this->info('✓ Tutorial state wiped');
        $this->info('✓ Persona cleared      (will trigger persona selection + world tone on next login)');
        $this->newLine();
        $this->info("Player '{$player->handle}' is ready for a fresh prologue run.");

        return self::SUCCESS;
    }
}
