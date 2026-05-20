<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Resets the test player's rig and economy to a clean starting state.
 * Use this before testing upgrade purchases, economy flow, or PvP balance.
 *
 * Usage:
 *   php artisan dev:reset-player
 *   php artisan dev:reset-player --email=test@example.com
 *   php artisan dev:reset-player --creds=10000
 */
class ResetTestPlayer extends Command
{
    protected $signature = 'dev:reset-player
                            {--email=test@example.com : The user email to reset}
                            {--creds=500             : Starting wallet creds for testing}
                            {--tech=0                : Starting tech points for testing}';

    protected $description = 'Reset a test player rig and economy to base state (dev only)';

    public function handle(): int
    {
        $email  = $this->option('email');
        $creds  = (int) $this->option('creds');
        $tech   = (int) $this->option('tech');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email: {$email}");
            $this->line('Available users:');
            User::select('email')->each(fn ($u) => $this->line("  • {$u->email}"));
            return self::FAILURE;
        }

        $player = Player::where('user_id', $user->id)->first();

        if ($player === null) {
            $this->error("No player found for user: {$email}");
            return self::FAILURE;
        }

        $rig = $player->rig()->with('chassis')->first();

        if ($rig === null) {
            $this->error("No rig found for player: {$player->handle}");
            return self::FAILURE;
        }

        $this->info("Resetting player: {$player->handle}");

        // ── Reset rig to bare base — all invested levels zeroed ───────────────
        $rig->cpu_level      = 0;
        $rig->ram_level      = 0;
        $rig->firewall_level = 0;
        $rig->storage_level  = 0;
        $rig->os_level       = 0;
        $rig->current_ss     = 100;
        $rig->is_limping     = false;
        $rig->current_uplink = (int) ($rig->chassis->base_uplink ?? 3);
        $rig->save();

        // ── Reset player economy and run state ───────────────────────────────
        $player->wallet_creds          = $creds;
        $player->pocket_creds          = 0;
        $player->tech_points           = $tech;
        $player->bounty_level          = 0;
        $player->bounty_multiplier     = 1.0;
        $player->is_open_season        = false;
        $player->nodes_hacked_this_run = 0;
        $player->pvp_wins_this_run     = 0;
        $player->is_limping            = false;
        $player->current_node_id       = null;
        $player->active_effects        = null;
        $player->save();

        // ── Summary ──────────────────────────────────────────────────────────
        $chassis  = $rig->chassis;
        $baseCpu  = $chassis->base_cpu;
        $baseRam  = $chassis->base_ram;
        $baseFw   = $chassis->base_firewall;
        $baseStg  = $chassis->base_storage;
        $baseOs   = $chassis->base_os_level ?? 2;
        $pointCap = $chassis->total_point_cap;

        $this->newLine();
        $this->line("<fg=cyan>── Rig: {$chassis->name} ──────────────────────────</>");
        $this->line("  CPU       {$baseCpu}  (invested: 0 / cap: {$chassis->cap_cpu})");
        $this->line("  RAM       {$baseRam}  (invested: 0 / cap: {$chassis->cap_ram})");
        $this->line("  Firewall  {$baseFw}  (invested: 0 / cap: {$chassis->cap_firewall})");
        $this->line("  Storage   {$baseStg}  (invested: 0 / cap: {$chassis->cap_storage})");
        $this->line("  OS        {$baseOs}  (invested: 0 / cap: {$chassis->cap_os})");
        $this->line("  SS        100/100");
        $this->line("  Point cap {$pointCap} available");
        $this->newLine();
        $this->line("<fg=cyan>── Economy ─────────────────────────────────────</>");
        $this->line("  Wallet    ◈{$creds}");
        $this->line("  Pocket    ◈0");
        $this->line("  Tech pts  {$tech}");
        $this->newLine();
        $this->info('Player reset complete. Reload the game to see the fresh state.');

        return self::SUCCESS;
    }
}
