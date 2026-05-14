<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Support\Collection;

/**
 * All bounty, Open Season, and leaderboard logic.
 *
 * Thresholds
 * ----------
 * BOUNTY_BOARD_THRESHOLD  — nodes hacked in one run to appear on the bounty board
 * OPEN_SEASON_NODE_THRESHOLD — nodes hacked in one run to trigger Open Season
 * OPEN_SEASON_PVP_THRESHOLD  — PvP wins while already on the board to trigger Open Season
 *
 * Steal percentages
 * -----------------
 *   Off bounty board:          10 %
 *   On bounty board (lv 15–24): 25 % → 59 %  (scales linearly)
 *   On bounty board (lv 25+):   60 % → 75 %  (tighter band, still scales)
 *   Open Season:               87.5 %
 *
 * Bounty multiplier
 * -----------------
 * Starts at 1.00. Each PvP win while on the bounty board adds 0.15, capped at 5.00.
 * Banked at Street Doc (extract). Determines the payout bonus the winner receives.
 */
class BountyService
{
    // --- Thresholds ---
    public const BOUNTY_BOARD_THRESHOLD    = 15;
    public const OPEN_SEASON_NODE_THRESHOLD = 25;
    public const OPEN_SEASON_PVP_THRESHOLD  = 5;

    // --- Multiplier ---
    public const MULTIPLIER_PER_PVP_WIN = 0.15;
    public const MULTIPLIER_CAP         = 5.00;

    // -------------------------------------------------------------------------
    // Node hack tracking
    // -------------------------------------------------------------------------

    /**
     * Record that a player successfully hacked a node.
     * Increments run counters and checks bounty/open-season thresholds.
     *
     * Returns a BountyEvent describing any threshold that was just crossed.
     */
    public function recordNodeHack(Player $player): BountyEvent
    {
        $wasOnBoard     = $player->nodes_hacked_this_run >= self::BOUNTY_BOARD_THRESHOLD;
        $wasOpenSeason  = $player->is_open_season;

        $player->nodes_hacked_this_run++;
        $player->bounty_level = $player->nodes_hacked_this_run;

        $event = BountyEvent::none();

        // Open season — node threshold
        if (!$wasOpenSeason && $player->nodes_hacked_this_run >= self::OPEN_SEASON_NODE_THRESHOLD) {
            $player->is_open_season = true;
            $event = BountyEvent::openSeasonTriggered($player->bounty_level);
        }
        // Bounty board entry
        elseif (!$wasOnBoard && $player->nodes_hacked_this_run >= self::BOUNTY_BOARD_THRESHOLD) {
            $event = BountyEvent::bountyMarked($player->bounty_level);
        }

        $player->save();
        return $event;
    }

    // -------------------------------------------------------------------------
    // PvP win tracking
    // -------------------------------------------------------------------------

    /**
     * Record a PvP win for the given player.
     * Increments the run win counter, adds a multiplier bonus, and checks
     * whether the player should enter Open Season.
     */
    public function recordPvpWin(Player $player): BountyEvent
    {
        $wasOpenSeason = $player->is_open_season;

        $player->pvp_wins_this_run++;

        // Multiplier bonus if on the bounty board
        if ($player->bounty_level >= self::BOUNTY_BOARD_THRESHOLD) {
            $player->bounty_multiplier = min(
                self::MULTIPLIER_CAP,
                (float) $player->bounty_multiplier + self::MULTIPLIER_PER_PVP_WIN,
            );
        }

        // Open Season — PvP wins threshold (while already on bounty board)
        $event = BountyEvent::none();
        if (
            !$wasOpenSeason
            && $player->bounty_level >= self::BOUNTY_BOARD_THRESHOLD
            && $player->pvp_wins_this_run >= self::OPEN_SEASON_PVP_THRESHOLD
        ) {
            $player->is_open_season = true;
            $event = BountyEvent::openSeasonTriggered($player->bounty_level);
        }

        // Update Open Season win streak
        if ($player->is_open_season) {
            $player->open_season_current_wins++;
            if ($player->open_season_current_wins > $player->open_season_best_wins) {
                $player->open_season_best_wins = $player->open_season_current_wins;
                // Also update the legacy open_season_wins column (all-time record)
                $player->open_season_wins = $player->open_season_best_wins;
            }
        }

        $player->save();
        return $event;
    }

    // -------------------------------------------------------------------------
    // Steal percentage
    // -------------------------------------------------------------------------

    /**
     * Returns the percentage of carried creds the winner steals from this loser.
     * Scales with the loser's bounty level. More dangerous to lose at higher bounty.
     */
    public function calculateStealPercentage(Player $loser): float
    {
        if ($loser->is_open_season) {
            return 87.5;
        }

        $level = (int) $loser->bounty_level;

        if ($level < self::BOUNTY_BOARD_THRESHOLD) {
            // Off the board — flat 10 %
            return 10.0;
        }

        if ($level < self::OPEN_SEASON_NODE_THRESHOLD) {
            // Board tier: 15–24 → 25 % to 59 %
            $progress = ($level - self::BOUNTY_BOARD_THRESHOLD)
                      / (self::OPEN_SEASON_NODE_THRESHOLD - self::BOUNTY_BOARD_THRESHOLD);
            return round(25.0 + $progress * 35.0, 1);
        }

        // High tier (25+): 60 % → 75 %, capped at 75 before Open Season kicks in
        $progress = min(1.0, ($level - self::OPEN_SEASON_NODE_THRESHOLD) / 25.0);
        return round(60.0 + $progress * 15.0, 1);
    }

    // -------------------------------------------------------------------------
    // Extract (banking a run at Street Doc)
    // -------------------------------------------------------------------------

    /**
     * The player reaches a Street Doc and banks their run.
     * Resets all per-run counters. The bounty_multiplier bonus is paid out
     * (caller handles cred transfer — we just reset state here).
     */
    public function extractToStreetDoc(Player $player): void
    {
        // Snapshot district before wipe so it can be shown on leaderboard until next run
        // (district was already updated on district-crossing — we leave it as-is)

        $player->nodes_hacked_this_run    = 0;
        $player->pvp_wins_this_run        = 0;
        $player->bounty_level             = 0;
        $player->bounty_multiplier        = 1.00;
        $player->is_open_season           = false;
        $player->open_season_current_wins = 0;
        $player->bounty_district_snapshot = null;

        $player->save();
    }

    // -------------------------------------------------------------------------
    // Leaderboards
    // -------------------------------------------------------------------------

    /**
     * Active bounty targets: players currently on the bounty board, sorted by
     * bounty_level descending.
     *
     * Returns players with bounty_level ≥ BOUNTY_BOARD_THRESHOLD.
     */
    public function getBountyLeaderboard(): Collection
    {
        return Player::where('bounty_level', '>=', self::BOUNTY_BOARD_THRESHOLD)
            ->orderByDesc('bounty_level')
            ->orderByDesc('pvp_wins_this_run')
            ->get(['id', 'handle', 'bounty_level', 'bounty_district_snapshot',
                   'nodes_hacked_this_run', 'pvp_wins_this_run', 'bounty_multiplier', 'is_open_season']);
    }

    /**
     * All-time Open Season hall of fame: sorted by open_season_best_wins descending.
     */
    public function getOpenSeasonHallOfFame(): Collection
    {
        return Player::where('open_season_best_wins', '>', 0)
            ->orderByDesc('open_season_best_wins')
            ->get(['id', 'handle', 'open_season_best_wins', 'open_season_wins']);
    }
}
