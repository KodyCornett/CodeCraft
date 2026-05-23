<?php

namespace Tests\Feature;

use App\Models\ChassisTemplate;
use App\Models\Peripheral;
use App\Models\Player;
use App\Models\PlayerPeripheral;
use App\Models\PlayerRig;
use App\Services\RigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RigServiceTest extends TestCase
{
    use RefreshDatabase;

    private RigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RigService();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Create a chassis + rig pair with explicit stat levels. */
    private function makeRig(array $stats = [], array $chassisOverrides = []): array
    {
        $chassis = ChassisTemplate::factory()->create(array_merge(
            ['total_point_cap' => 10],
            $chassisOverrides,
        ));

        $defaults = [
            'cpu_level'      => 1,
            'ram_level'      => 1,
            'firewall_level' => 1,
            'storage_level'  => 1,
            'os_level'       => 1,
            'current_ss'     => 10,
        ];

        $rig = PlayerRig::factory()->create(array_merge(
            $defaults,
            ['chassis_template_id' => $chassis->id],
            $stats,
        ));

        $rig->load('chassis');
        return [$rig, $chassis];
    }

    // =========================================================================
    // upgradeStat — normal upgrade (below cap)
    // =========================================================================

    public function test_upgrade_below_cap_increments_stat_and_returns_no_tax(): void
    {
        [$rig] = $this->makeRig(['cpu_level' => 1]);   // total = 5, cap = 10

        ['rig' => $rig, 'tax' => $tax] = $this->service->upgradeStat($rig, 'cpu');

        $this->assertEquals(2, $rig->fresh()->cpu_level);
        $this->assertNull($tax);
    }

    public function test_upgrade_does_not_affect_other_stats_when_below_cap(): void
    {
        [$rig] = $this->makeRig();

        $this->service->upgradeStat($rig, 'ram');

        $fresh = $rig->fresh();
        $this->assertEquals(1, $fresh->cpu_level);
        $this->assertEquals(1, $fresh->firewall_level);
        $this->assertEquals(1, $fresh->storage_level);
        $this->assertEquals(1, $fresh->os_level);
    }

    // =========================================================================
    // upgradeStat — ring tax direction (all 5 upgrade/tax pairs)
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\DataProvider('ringTaxProvider')]
    public function test_upgrade_at_cap_taxes_correct_neighbor(
        string $upgrading,
        string $expectedTaxed,
    ): void {
        // All stats at 2 → total 10 = cap. Every stat is above MIN so tax is always possible.
        [$rig] = $this->makeRig([
            'cpu_level'      => 2,
            'ram_level'      => 2,
            'firewall_level' => 2,
            'storage_level'  => 2,
            'os_level'       => 2,
            'current_ss'     => 20,
        ]);

        ['rig' => $rig, 'tax' => $tax] = $this->service->upgradeStat($rig, $upgrading);

        $this->assertNotNull($tax, "Tax event should be present when upgrading at cap.");
        $this->assertEquals($expectedTaxed, $tax['stat']);
        $this->assertEquals(2, $tax['old_level']);
        $this->assertEquals(1, $tax['new_level']);

        $fresh = $rig->fresh();
        $this->assertEquals(3, $fresh->{"{$upgrading}_level"}, "Upgraded stat should be 3.");
        $this->assertEquals(1, $fresh->{"{$expectedTaxed}_level"}, "Taxed stat should be 1.");
    }

    public static function ringTaxProvider(): array
    {
        return [
            'upgrading OS taxes RAM'          => ['os',       'ram'],
            'upgrading RAM taxes CPU'         => ['ram',      'cpu'],
            'upgrading CPU taxes Storage'     => ['cpu',      'storage'],
            'upgrading Storage taxes Firewall' => ['storage', 'firewall'],
            'upgrading Firewall taxes OS'     => ['firewall', 'os'],
        ];
    }

    public function test_tax_skips_stats_at_minimum_and_hits_next_eligible(): void
    {
        // cpu=2, ram=1 (at min), os=2, storage=2, firewall=2 → total 9, cap 9
        // Upgrade OS → tries RAM first (at min, skip) → taxes CPU
        [$rig] = $this->makeRig([
            'os_level'       => 2,
            'ram_level'      => 1,   // at minimum — should be skipped
            'cpu_level'      => 2,
            'storage_level'  => 2,
            'firewall_level' => 2,
            'current_ss'     => 20,
        ], ['total_point_cap' => 9]);

        ['tax' => $tax] = $this->service->upgradeStat($rig, 'os');

        $this->assertEquals('cpu', $tax['stat'], "Should skip RAM (at min) and tax CPU instead.");
    }

    // =========================================================================
    // upgradeStat — blocked at chassis max
    // =========================================================================

    public function test_upgrade_throws_when_stat_equals_chassis_cap(): void
    {
        // cap_cpu = 5, base_cpu (factory) = 2 → effective CPU = 2 + 3 = 5 = cap → blocked.
        // base_os_level stays at factory default (10) so the OS gate never fires first.
        [$rig] = $this->makeRig(['cpu_level' => 3], ['cap_cpu' => 5, 'total_point_cap' => 10]);

        $this->expectException(\OverflowException::class);
        $this->service->upgradeStat($rig, 'cpu');
    }

    // =========================================================================
    // upgradeStat — OS level change recalculates current_ss
    // =========================================================================

    public function test_upgrading_os_recalculates_current_ss(): void
    {
        [$rig] = $this->makeRig(['os_level' => 2, 'current_ss' => 20]);

        $this->service->upgradeStat($rig, 'os');

        $this->assertEquals(30, $rig->fresh()->current_ss);
    }

    public function test_taxing_os_via_ring_recalculates_current_ss(): void
    {
        // Upgrade Firewall at cap → OS is taxed → max_ss drops → current_ss recalculated
        [$rig] = $this->makeRig([
            'firewall_level' => 2,
            'os_level'       => 2,
            'ram_level'      => 2,
            'cpu_level'      => 2,
            'storage_level'  => 2,
            'current_ss'     => 20,
        ]);

        $this->service->upgradeStat($rig, 'firewall');

        // os_level drops to 1 → max_ss = 10
        $this->assertEquals(10, $rig->fresh()->current_ss);
    }

    // =========================================================================
    // applyDamage — PvE: damage without hitting zero
    // =========================================================================

    public function test_pve_damage_reduces_ss_without_state_change(): void
    {
        [$rig] = $this->makeRig(['current_ss' => 50]);
        $player = Player::factory()->create();

        ['event' => $event] = $this->service->applyDamage($rig, 20, 'pve', $player);

        $this->assertNull($event);
        $this->assertEquals(30, $rig->fresh()->current_ss);
        $this->assertFalse($rig->fresh()->is_limping);
        $this->assertFalse($player->fresh()->is_limping);
    }

    // =========================================================================
    // applyDamage — PvE: SS hits zero → Limp Mode
    // =========================================================================

    public function test_pve_damage_to_zero_triggers_limp_mode(): void
    {
        [$rig] = $this->makeRig([
            'cpu_level'  => 2, 'ram_level' => 2, 'firewall_level' => 2,
            'storage_level' => 2, 'os_level' => 2, 'current_ss' => 10,
        ]);
        $player = Player::factory()->create();

        $pointsBefore = $this->service->totalPointsSpent($rig);

        ['event' => $event] = $this->service->applyDamage($rig, 999, 'pve', $player);

        $this->assertEquals('limp_mode', $event);
        $this->assertEquals(1, $rig->fresh()->current_ss);
        $this->assertTrue($rig->fresh()->is_limping);
        $this->assertTrue($player->fresh()->is_limping);
        // No stat levels lost in PvE
        $this->assertEquals($pointsBefore, $this->service->totalPointsSpent($rig->fresh()));
    }

    // =========================================================================
    // applyDamage — PvP: SS hits zero → Critical Crash
    // =========================================================================

    public function test_pvp_damage_to_zero_triggers_street_doc_reset(): void
    {
        $streetDocId = (string) \Illuminate\Support\Str::uuid();
        $player = Player::factory()->create([
            'last_street_doc_id' => $streetDocId,
            'current_node_id'    => (string) \Illuminate\Support\Str::uuid(),
        ]);

        [$rig] = $this->makeRig([
            'player_id'      => $player->id,
            'cpu_level'      => 2,
            'ram_level'      => 2,
            'firewall_level' => 2,
            'storage_level'  => 2,
            'os_level'       => 2,
            'current_ss'     => 20,
        ]);

        $pointsBefore = $this->service->totalPointsSpent($rig);

        ['event' => $event] = $this->service->applyDamage($rig, 999, 'pvp', $player);

        $freshRig    = $rig->fresh();
        $freshPlayer = $player->fresh();

        $this->assertEquals('street_doc_reset', $event);
        $this->assertFalse($freshRig->is_limping);
        $this->assertFalse($freshPlayer->is_limping);

        // Player teleported to last street doc
        $this->assertEquals($streetDocId, $freshPlayer->current_node_id);

        // Exactly one stat level was lost
        $this->assertEquals(
            $pointsBefore - 1,
            $this->service->totalPointsSpent($freshRig),
            'PvP crash must cost exactly 1 stat level.',
        );

        // SS restored to max (based on possibly-reduced os_level)
        $this->assertEquals($this->service->maxSs($freshRig), $freshRig->current_ss);
    }

    public function test_pvp_crash_does_not_lose_stat_when_all_at_minimum(): void
    {
        // All stats at 1 (minimum) — no stat can be lost, should not throw
        $player = Player::factory()->create(['last_street_doc_id' => null]);
        [$rig]  = $this->makeRig(['player_id' => $player->id, 'current_ss' => 10]);

        ['event' => $event] = $this->service->applyDamage($rig, 999, 'pvp', $player);

        $this->assertEquals('street_doc_reset', $event);
        $this->assertEquals(5, $this->service->totalPointsSpent($rig->fresh()));
    }

    // =========================================================================
    // effectiveStats — no peripherals
    // =========================================================================

    public function test_effective_stats_without_peripherals_equals_base_plus_levels(): void
    {
        $chassis = ChassisTemplate::factory()->create([
            'base_cpu'      => 3,
            'base_ram'      => 4,
            'base_firewall' => 2,
            'base_storage'  => 5,
        ]);
        [$rig] = $this->makeRig([
            'chassis_template_id' => $chassis->id,
            'cpu_level'           => 2,
            'ram_level'           => 1,
            'firewall_level'      => 3,
            'storage_level'       => 2,
            'os_level'            => 1,
        ]);
        $player = Player::factory()->create();

        $stats = $this->service->effectiveStats($rig, $player);

        $this->assertEquals(3 + 2, $stats['cpu']['effective']);      // base 3 + level 2
        $this->assertEquals(4 + 1, $stats['ram']['effective']);      // base 4 + level 1
        $this->assertEquals(2 + 3, $stats['firewall']['effective']); // base 2 + level 3
        $this->assertEquals(5 + 2, $stats['storage']['effective']); // base 5 + level 2
        $this->assertEquals(0, $stats['cpu']['peripheral_boost']);
    }

    // =========================================================================
    // effectiveStats — installed undamaged peripheral adds boost
    // =========================================================================

    public function test_installed_undamaged_peripheral_adds_boost(): void
    {
        [$rig] = $this->makeRig(['cpu_level' => 2]);
        $player     = Player::factory()->create();
        $peripheral = Peripheral::factory()->create(['stat_boosted' => 'cpu', 'boost_amount' => 3]);

        PlayerPeripheral::factory()->create([
            'player_id'     => $player->id,
            'peripheral_id' => $peripheral->id,
            'is_installed'  => true,
            'is_damaged'    => false,
        ]);

        $stats = $this->service->effectiveStats($rig, $player);

        $this->assertEquals(3, $stats['cpu']['peripheral_boost']);
        $this->assertEquals(2 + 2 + 3, $stats['cpu']['effective']); // base 2 + level 2 + boost 3
    }

    // =========================================================================
    // effectiveStats — damaged peripheral does NOT boost
    // =========================================================================

    public function test_damaged_peripheral_does_not_contribute_boost(): void
    {
        [$rig]      = $this->makeRig(['ram_level' => 2]);
        $player     = Player::factory()->create();
        $peripheral = Peripheral::factory()->create(['stat_boosted' => 'ram', 'boost_amount' => 5]);

        PlayerPeripheral::factory()->create([
            'player_id'     => $player->id,
            'peripheral_id' => $peripheral->id,
            'is_installed'  => true,
            'is_damaged'    => true,   // damaged — should not count
        ]);

        $stats = $this->service->effectiveStats($rig, $player);

        $this->assertEquals(0, $stats['ram']['peripheral_boost']);
    }

    // =========================================================================
    // effectiveStats — uninstalled peripheral does NOT boost
    // =========================================================================

    public function test_uninstalled_peripheral_does_not_contribute_boost(): void
    {
        [$rig]      = $this->makeRig(['storage_level' => 1]);
        $player     = Player::factory()->create();
        $peripheral = Peripheral::factory()->create(['stat_boosted' => 'storage', 'boost_amount' => 4]);

        PlayerPeripheral::factory()->create([
            'player_id'     => $player->id,
            'peripheral_id' => $peripheral->id,
            'is_installed'  => false,  // in inventory, not installed
            'is_damaged'    => false,
        ]);

        $stats = $this->service->effectiveStats($rig, $player);

        $this->assertEquals(0, $stats['storage']['peripheral_boost']);
    }

    // =========================================================================
    // effectiveStats — multiple peripherals stack correctly
    // =========================================================================

    public function test_multiple_installed_peripherals_stack_boosts(): void
    {
        [$rig]  = $this->makeRig(['firewall_level' => 1]);
        $player = Player::factory()->create();

        foreach ([2, 3] as $boost) {
            $p = Peripheral::factory()->create(['stat_boosted' => 'firewall', 'boost_amount' => $boost]);
            PlayerPeripheral::factory()->create([
                'player_id'     => $player->id,
                'peripheral_id' => $p->id,
                'is_installed'  => true,
                'is_damaged'    => false,
            ]);
        }

        $stats = $this->service->effectiveStats($rig, $player);

        $this->assertEquals(5, $stats['firewall']['peripheral_boost']);
        $this->assertEquals(2 + 1 + 5, $stats['firewall']['effective']); // base 2 + level 1 + boost 5
    }
}
