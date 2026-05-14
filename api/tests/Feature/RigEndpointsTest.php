<?php

namespace Tests\Feature;

use App\Models\ChassisTemplate;
use App\Models\Player;
use App\Models\PlayerRig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RigEndpointsTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helper — creates an authenticated user, player, chassis, and rig.
    // Returns [$user, $player, $rig] ready for actingAs().
    // =========================================================================

    private function scaffold(array $rigOverrides = [], array $chassisOverrides = []): array
    {
        $user    = User::factory()->create();
        $chassis = ChassisTemplate::factory()->create(array_merge(
            ['total_point_cap' => 10],
            $chassisOverrides,
        ));
        $player  = Player::factory()->create(['user_id' => $user->id]);
        $rig     = PlayerRig::factory()->create(array_merge(
            [
                'player_id'           => $player->id,
                'chassis_template_id' => $chassis->id,
                'cpu_level'           => 2,
                'ram_level'           => 2,
                'firewall_level'      => 2,
                'storage_level'       => 2,
                'os_level'            => 2,
                'current_ss'          => 20,
            ],
            $rigOverrides,
        ));

        return [$user, $player, $rig];
    }

    // =========================================================================
    // Unauthenticated — all protected routes must return 401
    // =========================================================================

    public function test_unauthenticated_get_rig_returns_401(): void
    {
        $this->getJson('/api/rig?player_id=' . fake()->uuid())
             ->assertUnauthorized();
    }

    public function test_unauthenticated_post_damage_returns_401(): void
    {
        $this->postJson('/api/rig/damage', [])
             ->assertUnauthorized();
    }

    public function test_unauthenticated_post_upgrade_returns_401(): void
    {
        $this->postJson('/api/rig/upgrade', [])
             ->assertUnauthorized();
    }

    public function test_unauthenticated_post_repair_returns_401(): void
    {
        $this->postJson('/api/rig/repair', [])
             ->assertUnauthorized();
    }

    public function test_unauthenticated_get_player_status_returns_401(): void
    {
        $this->getJson('/api/player/' . fake()->uuid() . '/status')
             ->assertUnauthorized();
    }

    // =========================================================================
    // GET /api/rig — correct response structure
    // =========================================================================

    public function test_get_rig_returns_correct_structure(): void
    {
        [$user, $player] = $this->scaffold();

        $this->actingAs($user, 'sanctum')
             ->getJson("/api/rig?player_id={$player->id}")
             ->assertOk()
             ->assertJsonStructure([
                 'rig_id',
                 'chassis',
                 'is_limping',
                 'current_ss',
                 'max_ss',
                 'stats' => [
                     'cpu'      => ['level', 'base', 'peripheral_boost', 'effective'],
                     'ram'      => ['level', 'base', 'peripheral_boost', 'effective'],
                     'firewall' => ['level', 'base', 'peripheral_boost', 'effective'],
                     'storage'  => ['level', 'base', 'peripheral_boost', 'effective'],
                     'os'       => ['level', 'base', 'peripheral_boost', 'effective'],
                 ],
                 'points' => ['spent', 'cap'],
             ]);
    }

    public function test_get_rig_returns_404_for_unknown_player(): void
    {
        [$user] = $this->scaffold();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/rig?player_id=' . fake()->uuid())
             ->assertNotFound();
    }

    // =========================================================================
    // POST /api/rig/damage — event types
    // =========================================================================

    public function test_pve_damage_without_zero_returns_null_event(): void
    {
        [$user, $player] = $this->scaffold(['current_ss' => 50]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/damage', [
                 'player_id' => $player->id,
                 'amount'    => 10,
                 'source'    => 'pve',
             ])
             ->assertOk()
             ->assertJsonFragment(['event' => null, 'current_ss' => 40]);
    }

    public function test_pve_damage_to_zero_returns_limp_mode(): void
    {
        [$user, $player] = $this->scaffold(['current_ss' => 10]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/damage', [
                 'player_id' => $player->id,
                 'amount'    => 999,
                 'source'    => 'pve',
             ])
             ->assertOk()
             ->assertJsonFragment(['event' => 'limp_mode', 'current_ss' => 1, 'is_limping' => true]);
    }

    public function test_pvp_damage_to_zero_returns_street_doc_reset(): void
    {
        [$user, $player] = $this->scaffold(['current_ss' => 10]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/damage', [
                 'player_id' => $player->id,
                 'amount'    => 999,
                 'source'    => 'pvp',
             ])
             ->assertOk()
             ->assertJsonFragment(['event' => 'street_doc_reset', 'is_limping' => false]);
    }

    // =========================================================================
    // POST /api/rig/upgrade — tax event at cap
    // =========================================================================

    public function test_upgrade_below_cap_returns_null_tax(): void
    {
        // total = 5 (all at 1), cap = 10
        [$user, $player] = $this->scaffold([
            'cpu_level' => 1, 'ram_level' => 1, 'firewall_level' => 1,
            'storage_level' => 1, 'os_level' => 1, 'current_ss' => 10,
        ]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/upgrade', [
                 'player_id' => $player->id,
                 'stat'      => 'cpu',
             ])
             ->assertOk()
             ->assertJsonFragment(['stat_upgraded' => 'cpu', 'tax_event' => null]);
    }

    public function test_upgrade_at_cap_returns_tax_event(): void
    {
        // All at 2, total = 10 = cap. Upgrading OS should tax RAM.
        [$user, $player] = $this->scaffold([
            'cpu_level' => 2, 'ram_level' => 2, 'firewall_level' => 2,
            'storage_level' => 2, 'os_level' => 2, 'current_ss' => 20,
        ]);

        $response = $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/upgrade', [
                 'player_id' => $player->id,
                 'stat'      => 'os',
             ])
             ->assertOk()
             ->assertJsonFragment(['stat_upgraded' => 'os'])
             ->json();

        $this->assertNotNull($response['tax_event']);
        $this->assertEquals('ram',  $response['tax_event']['stat']);
        $this->assertEquals(2, $response['tax_event']['old_level']);
        $this->assertEquals(1, $response['tax_event']['new_level']);
    }

    public function test_upgrade_at_chassis_max_returns_422(): void
    {
        [$user, $player] = $this->scaffold(['cpu_level' => 10], ['total_point_cap' => 10]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/upgrade', [
                 'player_id' => $player->id,
                 'stat'      => 'cpu',
             ])
             ->assertUnprocessable();
    }

    // =========================================================================
    // POST /api/rig/repair — restores SS to max
    // =========================================================================

    public function test_repair_restores_ss_to_max(): void
    {
        [$user, $player] = $this->scaffold([
            'os_level'   => 3,
            'current_ss' => 5,   // damaged — max is 30 (os 3 × 10)
        ]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/repair', ['player_id' => $player->id])
             ->assertOk()
             ->assertJsonFragment(['current_ss' => 30, 'max_ss' => 30]);
    }

    public function test_repair_with_peripherals_flag_clears_damaged_peripherals(): void
    {
        [$user, $player] = $this->scaffold();

        $peripheral = \App\Models\Peripheral::factory()->create();
        \App\Models\PlayerPeripheral::factory()->create([
            'player_id'     => $player->id,
            'peripheral_id' => $peripheral->id,
            'is_installed'  => true,
            'is_damaged'    => true,
        ]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/repair', [
                 'player_id'          => $player->id,
                 'repair_peripherals' => true,
             ])
             ->assertOk();

        $this->assertDatabaseHas('player_peripherals', [
            'player_id'  => $player->id,
            'is_damaged' => false,
        ]);
    }
}
