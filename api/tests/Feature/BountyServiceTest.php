<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\BountyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BountyServiceTest extends TestCase
{
    use RefreshDatabase;

    private BountyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BountyService();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makePlayer(array $overrides = []): Player
    {
        return Player::factory()->create($overrides);
    }

    // =========================================================================
    // recordNodeHack — bounty board threshold
    // =========================================================================

    public function test_hack_below_threshold_returns_none_event(): void
    {
        $player = $this->makePlayer(['nodes_hacked_this_run' => 0]);

        $event = $this->service->recordNodeHack($player);

        $this->assertSame('none', $event->type);
        $this->assertSame(1, $player->fresh()->nodes_hacked_this_run);
    }

    public function test_crossing_bounty_board_threshold_returns_bounty_marked(): void
    {
        $player = $this->makePlayer([
            'nodes_hacked_this_run' => BountyService::BOUNTY_BOARD_THRESHOLD - 1,
            'bounty_level'          => BountyService::BOUNTY_BOARD_THRESHOLD - 1,
        ]);

        $event = $this->service->recordNodeHack($player);

        $this->assertSame('bounty_marked', $event->type);
        $this->assertSame(BountyService::BOUNTY_BOARD_THRESHOLD, $event->data['bounty_level']);
    }

    public function test_hacking_above_threshold_does_not_refire_bounty_marked(): void
    {
        $player = $this->makePlayer([
            'nodes_hacked_this_run' => BountyService::BOUNTY_BOARD_THRESHOLD,
            'bounty_level'          => BountyService::BOUNTY_BOARD_THRESHOLD,
        ]);

        $event = $this->service->recordNodeHack($player);

        $this->assertSame('none', $event->type);
    }

    // =========================================================================
    // recordNodeHack — open season node threshold
    // =========================================================================

    public function test_crossing_open_season_node_threshold_triggers_open_season(): void
    {
        $player = $this->makePlayer([
            'nodes_hacked_this_run' => BountyService::OPEN_SEASON_NODE_THRESHOLD - 1,
            'bounty_level'          => BountyService::OPEN_SEASON_NODE_THRESHOLD - 1,
            'is_open_season'        => false,
        ]);

        $event = $this->service->recordNodeHack($player);

        $this->assertSame('open_season_triggered', $event->type);
        $this->assertTrue($player->fresh()->is_open_season);
    }

    // =========================================================================
    // recordPvpWin — bounty multiplier and open season via PvP wins
    // =========================================================================

    public function test_pvp_win_while_on_board_increments_multiplier(): void
    {
        $player = $this->makePlayer([
            'bounty_level'      => BountyService::BOUNTY_BOARD_THRESHOLD,
            'bounty_multiplier' => 1.00,
        ]);

        $this->service->recordPvpWin($player);

        $this->assertEqualsWithDelta(
            1.00 + BountyService::MULTIPLIER_PER_PVP_WIN,
            (float) $player->fresh()->bounty_multiplier,
            0.001,
        );
    }

    public function test_pvp_win_off_board_does_not_increment_multiplier(): void
    {
        $player = $this->makePlayer([
            'bounty_level'      => 0,
            'bounty_multiplier' => 1.00,
        ]);

        $this->service->recordPvpWin($player);

        $this->assertEqualsWithDelta(1.00, (float) $player->fresh()->bounty_multiplier, 0.001);
    }

    public function test_multiplier_is_capped_at_maximum(): void
    {
        $player = $this->makePlayer([
            'bounty_level'      => BountyService::BOUNTY_BOARD_THRESHOLD,
            'bounty_multiplier' => BountyService::MULTIPLIER_CAP,
        ]);

        $this->service->recordPvpWin($player);

        $this->assertEqualsWithDelta(
            BountyService::MULTIPLIER_CAP,
            (float) $player->fresh()->bounty_multiplier,
            0.001,
        );
    }

    public function test_open_season_triggered_by_pvp_wins_threshold(): void
    {
        $player = $this->makePlayer([
            'bounty_level'      => BountyService::BOUNTY_BOARD_THRESHOLD,
            'pvp_wins_this_run' => BountyService::OPEN_SEASON_PVP_THRESHOLD - 1,
            'is_open_season'    => false,
        ]);

        $event = $this->service->recordPvpWin($player);

        $this->assertSame('open_season_triggered', $event->type);
        $this->assertTrue($player->fresh()->is_open_season);
    }

    public function test_open_season_pvp_threshold_not_triggered_if_not_on_board(): void
    {
        $player = $this->makePlayer([
            'bounty_level'      => 0,
            'pvp_wins_this_run' => BountyService::OPEN_SEASON_PVP_THRESHOLD - 1,
            'is_open_season'    => false,
        ]);

        $event = $this->service->recordPvpWin($player);

        $this->assertSame('none', $event->type);
        $this->assertFalse($player->fresh()->is_open_season);
    }

    public function test_open_season_win_streak_tracked_and_best_updated(): void
    {
        $player = $this->makePlayer([
            'bounty_level'             => BountyService::BOUNTY_BOARD_THRESHOLD,
            'is_open_season'           => true,
            'open_season_current_wins' => 3,
            'open_season_best_wins'    => 3,
        ]);

        $this->service->recordPvpWin($player);

        $fresh = $player->fresh();
        $this->assertSame(4, $fresh->open_season_current_wins);
        $this->assertSame(4, $fresh->open_season_best_wins);
    }

    // =========================================================================
    // calculateStealPercentage — scaling at each tier
    // =========================================================================

    public function test_steal_percentage_is_10_when_off_bounty_board(): void
    {
        $player = $this->makePlayer(['bounty_level' => 0, 'is_open_season' => false]);

        $this->assertEquals(10.0, $this->service->calculateStealPercentage($player));
    }

    public function test_steal_percentage_is_25_at_board_entry_threshold(): void
    {
        $player = $this->makePlayer([
            'bounty_level'  => BountyService::BOUNTY_BOARD_THRESHOLD,
            'is_open_season' => false,
        ]);

        $this->assertEqualsWithDelta(20.0, $this->service->calculateStealPercentage($player), 0.1);
    }

    public function test_steal_percentage_scales_up_mid_board(): void
    {
        // bounty_level 20 = ★3 (20–24 hacks) → 60%
        $player = $this->makePlayer(['bounty_level' => 20, 'is_open_season' => false]);

        $this->assertEqualsWithDelta(60.0, $this->service->calculateStealPercentage($player), 0.5);
    }

    public function test_steal_percentage_is_60_at_open_season_node_threshold(): void
    {
        $player = $this->makePlayer([
            'bounty_level'  => BountyService::OPEN_SEASON_NODE_THRESHOLD,
            'is_open_season' => false,
        ]);

        $this->assertEqualsWithDelta(60.0, $this->service->calculateStealPercentage($player), 0.1);
    }

    public function test_steal_percentage_is_87_5_for_open_season_player(): void
    {
        $player = $this->makePlayer(['is_open_season' => true, 'bounty_level' => 30]);

        $this->assertEquals(85.0, $this->service->calculateStealPercentage($player));
    }

    public function test_steal_percentage_never_exceeds_75_before_open_season(): void
    {
        $player = $this->makePlayer(['bounty_level' => 200, 'is_open_season' => false]);

        $this->assertLessThanOrEqual(75.0, $this->service->calculateStealPercentage($player));
    }

    // =========================================================================
    // extractToStreetDoc — resets all run counters
    // =========================================================================

    public function test_extract_resets_all_run_counters(): void
    {
        $player = $this->makePlayer([
            'nodes_hacked_this_run'    => 20,
            'pvp_wins_this_run'        => 4,
            'bounty_level'             => 20,
            'bounty_multiplier'        => 2.50,
            'is_open_season'           => true,
            'open_season_current_wins' => 3,
            'bounty_district_snapshot' => 'Downtown',
        ]);

        $this->service->extractToStreetDoc($player);

        $fresh = $player->fresh();
        $this->assertSame(0, $fresh->nodes_hacked_this_run);
        $this->assertSame(0, $fresh->pvp_wins_this_run);
        $this->assertSame(0, $fresh->bounty_level);
        $this->assertEqualsWithDelta(1.00, (float) $fresh->bounty_multiplier, 0.001);
        $this->assertFalse($fresh->is_open_season);
        $this->assertSame(0, $fresh->open_season_current_wins);
        $this->assertNull($fresh->bounty_district_snapshot);
    }

    public function test_extract_preserves_all_time_best_wins(): void
    {
        $player = $this->makePlayer([
            'open_season_current_wins' => 5,
            'open_season_best_wins'    => 5,
        ]);

        $this->service->extractToStreetDoc($player);

        $this->assertSame(5, $player->fresh()->open_season_best_wins);
    }

    // =========================================================================
    // Leaderboard queries
    // =========================================================================

    public function test_bounty_leaderboard_only_shows_players_on_board(): void
    {
        Player::factory()->create(['bounty_level' => 5,  'handle' => 'ghost']);
        Player::factory()->create(['bounty_level' => 20, 'handle' => 'wraith']);
        Player::factory()->create(['bounty_level' => 18, 'handle' => 'spectre']);

        $board = $this->service->getBountyLeaderboard();

        $handles = $board->pluck('handle')->toArray();
        $this->assertContains('wraith', $handles);
        $this->assertContains('spectre', $handles);
        $this->assertNotContains('ghost', $handles);
        $this->assertSame('wraith', $handles[0]);
    }

    public function test_open_season_hall_of_fame_excludes_zero_best_wins(): void
    {
        Player::factory()->create(['open_season_best_wins' => 0, 'handle' => 'nobody']);
        Player::factory()->create(['open_season_best_wins' => 7, 'handle' => 'legend']);

        $fame = $this->service->getOpenSeasonHallOfFame();

        $handles = $fame->pluck('handle')->toArray();
        $this->assertContains('legend', $handles);
        $this->assertNotContains('nobody', $handles);
    }
}
