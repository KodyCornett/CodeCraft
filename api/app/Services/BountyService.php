<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Support\Collection;

/**
 * All bounty, Open Season, leaderboard, and PvP loot logic.
 *
 * Bounty ladder (nodes_hacked_this_run → client level)
 * ─────────────────────────────────────────────────────
 *   < 10 hacks  → off the board (Level 0)
 *  10–14 hacks  → Level 1  ×1.25
 *  15–19 hacks  → Level 2  ×1.50
 *  20–24 hacks  → Level 3  ×1.75
 *  25–29 hacks  → Level 4  ×2.00  → Open Season (node route)
 *   30+  hacks  → Level 5  ×2.25
 *
 * Steal percentages (winner's cut of loser's pocket on PvP combat)
 * ──────────────────────────────────────────────────────────────────
 *  Level 0 (off board) :  20 % to winner, loser keeps 80 %
 *  Level 1             :  30 %  loser keeps 70 %
 *  Level 2             :  40 %  loser keeps 60 %
 *  Level 3             :  50 %  loser keeps 50 %
 *  Level 4             :  60 %  loser keeps 40 %
 *  Level 5 / Open Season:  75 %  loser keeps 25 %
 *
 * On a SURVIVABLE loss the loser keeps (100 - steal_pct)% of their pocket.
 * On ELIMINATION (SS hits 0) the loser's pocket is fully zeroed — the winner
 * still takes steal_pct% and ICE seizes the remainder.
 *
 * The same steal formula applies when a player DECLINES a challenge — they
 * take the same financial hit as losing without even having to fight.
 *
 * PvP win multiplier bonus
 * ─────────────────────────
 * Each PvP win while on the board (≥ Level 1) adds +0.15 to bounty_multiplier,
 * capped at 5.00. Surviving hunters makes you worth even more to the next one.
 */
class BountyService
{
    // ── Thresholds (hack counts per CLAUDE.md) ───────────────────────────────
    public const BOUNTY_BOARD_THRESHOLD    = 10;  // hacks to appear on bounty board (Star 1)
    public const OPEN_SEASON_THRESHOLD     = 25;  // hacks to trigger Open Season via nodes (Star 4)
    public const OPEN_SEASON_PVP_THRESHOLD = 5;   // PvP wins while on board → Open Season

    // ── Star tier thresholds (hack count → 0–5 star level) ──────────────────
    private const STAR_TIERS = [
        30 => 5,   // ★★★★★
        25 => 4,   // ★★★★  — Open Season at this point
        20 => 3,   // ★★★
        15 => 2,   // ★★
        10 => 1,   // ★
    ];

    // ── Multiplier ────────────────────────────────────────────────────────────
    public const MULTIPLIER_PER_PVP_WIN = 0.15;
    public const MULTIPLIER_CAP         = 5.00;

    // ── Steal tiers (aligned to CLAUDE.md star thresholds) ───────────────────
    private const STEAL_TIERS = [
        30 => 75.0,   // Level 5 / Open Season
        25 => 60.0,   // Level 4  (Open Season triggers here, steal goes to 75 via is_open_season check)
        20 => 50.0,   // Level 3
        15 => 40.0,   // Level 2
        10 => 30.0,   // Level 1
         0 => 20.0,   // off board
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Node hack tracking
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record a successful node hack.
     * Increments run counters and fires bounty/open-season threshold events.
     */
    public function recordNodeHack(Player $player): BountyEvent
    {
        $wasOnBoard    = $player->nodes_hacked_this_run >= self::BOUNTY_BOARD_THRESHOLD;
        $wasOpenSeason = $player->is_open_season;

        $player->nodes_hacked_this_run++;
        // bounty_level stores the 0–5 star tier; nodes_hacked_this_run is the raw counter
        $player->bounty_level = $this->hackCountToStarLevel($player->nodes_hacked_this_run);

        $event = BountyEvent::none();

        if (!$wasOpenSeason && $player->nodes_hacked_this_run >= self::OPEN_SEASON_THRESHOLD) {
            $player->is_open_season = true;
            $event = BountyEvent::openSeasonTriggered($player->bounty_level);
        } elseif (!$wasOnBoard && $player->nodes_hacked_this_run >= self::BOUNTY_BOARD_THRESHOLD) {
            $event = BountyEvent::bountyMarked($player->bounty_level);
        }

        $player->save();
        return $event;
    }

    /**
     * Convert a raw hack count to a 0–5 star level.
     */
    public function hackCountToStarLevel(int $hacks): int
    {
        foreach (self::STAR_TIERS as $threshold => $stars) {
            if ($hacks >= $threshold) {
                return $stars;
            }
        }
        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PvP win tracking
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record a PvP win for the given player.
     * Adds a multiplier bonus and checks for Open Season via PvP route.
     */
    public function recordPvpWin(Player $player): BountyEvent
    {
        $wasOpenSeason = $player->is_open_season;

        $player->pvp_wins_this_run++;

        if ($player->nodes_hacked_this_run >= self::BOUNTY_BOARD_THRESHOLD) {
            $player->bounty_multiplier = min(
                self::MULTIPLIER_CAP,
                (float) $player->bounty_multiplier + self::MULTIPLIER_PER_PVP_WIN,
            );
        }

        $event = BountyEvent::none();

        if (
            !$wasOpenSeason
            && $player->nodes_hacked_this_run >= self::BOUNTY_BOARD_THRESHOLD
            && $player->pvp_wins_this_run     >= self::OPEN_SEASON_PVP_THRESHOLD
        ) {
            $player->is_open_season = true;
            $event = BountyEvent::openSeasonTriggered($player->bounty_level);
        }

        if ($player->is_open_season) {
            $player->open_season_current_wins++;
            if ($player->open_season_current_wins > $player->open_season_best_wins) {
                $player->open_season_best_wins = $player->open_season_current_wins;
                $player->open_season_wins      = $player->open_season_best_wins;
            }
        }

        $player->save();
        return $event;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PvP loot resolution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve PvP loot: transfer a cut of the loser's pocket to the winner.
     *
     * Survivable loss  ($isElimination = false):
     *   Winner takes steal_pct% of loser's pocket.
     *   Loser keeps the remaining (100 - steal_pct)%.
     *   ICE seizes nothing.
     *
     * Elimination      ($isElimination = true, SS hit 0):
     *   Winner takes steal_pct% of loser's pocket.
     *   ICE seizes the rest. Loser's pocket is fully zeroed.
     *
     * Returns:
     *   pocket_before   — what the loser was carrying
     *   steal_pct       — percentage the winner takes
     *   stolen          — creds transferred to winner's pocket
     *   seized_by_ice   — creds destroyed (0 on survivable loss)
     */
    public function resolvePvpLoot(Player $winner, Player $loser, bool $isElimination = false): array
    {
        $pocket   = (int) ($loser->pocket_creds ?? 0);
        $stealPct = $this->calculateStealPercentage($loser);
        $stolen   = (int) floor($pocket * $stealPct / 100);

        $winner->pocket_creds = (int) ($winner->pocket_creds ?? 0) + $stolen;
        $winner->save();

        if ($isElimination) {
            // ICE seizes whatever the winner didn't take
            $seized              = $pocket - $stolen;
            $loser->pocket_creds = 0;
        } else {
            // Loser keeps the remainder — ICE gets nothing
            $seized              = 0;
            $loser->pocket_creds = $pocket - $stolen;
        }
        // loser is saved by resetAfterPvpLoss() — do not save here

        return [
            'pocket_before' => $pocket,
            'steal_pct'     => $stealPct,
            'stolen'        => $stolen,
            'seized_by_ice' => $seized,
        ];
    }

    /**
     * Reset the loser's run state after a survivable PvP loss.
     * Pocket is already updated by resolvePvpLoot(); we only set the limp flag here.
     * Full resets happen in criticalFailure() (SS = 0) or extractToCyberDoc().
     */
    public function resetAfterPvpLoss(Player $loser): void
    {
        $loser->is_limping = true;
        $loser->save();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Steal percentage
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Percentage of the loser's pocket the winner receives.
     * Scales with the loser's hack count (nodes_hacked_this_run).
     */
    public function calculateStealPercentage(Player $loser): float
    {
        if ($loser->is_open_season) {
            return self::STEAL_TIERS[30];
        }

        $hacks = (int) ($loser->nodes_hacked_this_run ?? 0);

        foreach (self::STEAL_TIERS as $threshold => $pct) {
            if ($hacks >= $threshold) {
                return $pct;
            }
        }

        return 20.0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Extract (banking at CyberDoc)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Player banks their run at the CyberDoc.
     * Returns the pocket_creds amount so the caller can credit the player's
     * safe wallet (handled outside this service — we only reset run state here).
     */
    public function extractToCyberDoc(Player $player): int
    {
        $pocket = (int) ($player->pocket_creds ?? 0);

        $player->pocket_creds             = 0;
        $player->nodes_hacked_this_run    = 0;
        $player->pvp_wins_this_run        = 0;
        $player->bounty_level             = 0;
        $player->bounty_multiplier        = 1.00;
        $player->is_open_season           = false;
        $player->open_season_current_wins = 0;
        $player->bounty_district_snapshot = null;

        $player->save();

        return $pocket; // caller adds this to safe wallet
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Leaderboards
    // ─────────────────────────────────────────────────────────────────────────

    public function getBountyLeaderboard(): Collection
    {
        // Filter by nodes_hacked_this_run so the board threshold is based on the
        // raw hack count (BOUNTY_BOARD_THRESHOLD = 10), not the 0–5 star value.
        return Player::where('nodes_hacked_this_run', '>=', self::BOUNTY_BOARD_THRESHOLD)
            ->orderByDesc('bounty_level')
            ->orderByDesc('pvp_wins_this_run')
            ->get([
                'id', 'handle', 'bounty_level', 'bounty_district_snapshot',
                'nodes_hacked_this_run', 'pvp_wins_this_run',
                'bounty_multiplier', 'is_open_season', 'pocket_creds',
            ]);
    }

    public function getOpenSeasonHallOfFame(): Collection
    {
        return Player::where('open_season_best_wins', '>', 0)
            ->orderByDesc('open_season_best_wins')
            ->get(['id', 'handle', 'open_season_best_wins', 'open_season_wins']);
    }
}
