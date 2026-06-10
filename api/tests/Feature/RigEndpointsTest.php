<?php

namespace Tests\Feature;

use App\Models\ChassisTemplate;
use App\Models\Node;
use App\Models\Player;
use App\Models\PlayerRig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RigEndpointsTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function scaffold(
        array $rigOverrides    = [],
        array $chassisOverrides = [],
        array $playerOverrides  = [],
    ): array {
        $user    = User::factory()->create();
        $chassis = ChassisTemplate::factory()->create(array_merge(
            ['total_point_cap' => 10],
            $chassisOverrides,
        ));
        $player = Player::factory()->create(array_merge(
            ['user_id' => $user->id],
            $playerOverrides,
        ));
        $rig = PlayerRig::factory()->create(array_merge(
            [
                'player_id'           => $player->id,
                'chassis_template_id' => $chassis->id,
                'cpu_level'           => 2,
                'ram_level'           => 2,
                'firewall_level'      => 2,
                'storage_level'       => 2,
                'os_level'            => 2,
                'current_ss'          => 100,
            ],
            $rigOverrides,
        ));

        return [$user, $player, $rig];
    }

    /** Create a cyberdoc node, move the player there, and return the node. */
    private function placePlayerAtCyberDoc(Player $player): Node
    {
        $node = Node::create([
            'canvas_id' => 'test-cyberdoc-' . uniqid(),
            'x'         => 0,
            'y'         => 0,
            'type'      => 'cyberdoc',
            'ice'       => 0,
        ]);
        $player->current_node_id = $node->id;
        $player->save();
        return $node;
    }

    /** Create an action node with given ICE and return it. */
    private function createActionNode(int $ice = 3, string $canvasId = 'test-action'): Node
    {
        return Node::create([
            'canvas_id' => $canvasId,
            'x'         => 0,
            'y'         => 0,
            'type'      => 'action',
            'ice'       => $ice,
        ]);
    }

    // =========================================================================
    // Unauthenticated — all protected routes must return 401
    // =========================================================================

    public function test_unauthenticated_get_rig_returns_401(): void
    {
        $this->getJson('/api/rig')->assertUnauthorized();
    }

    public function test_unauthenticated_post_damage_returns_401(): void
    {
        $this->postJson('/api/rig/damage', [])->assertUnauthorized();
    }

    public function test_unauthenticated_post_upgrade_returns_401(): void
    {
        $this->postJson('/api/rig/upgrade', [])->assertUnauthorized();
    }

    public function test_unauthenticated_post_repair_returns_401(): void
    {
        $this->postJson('/api/rig/repair', [])->assertUnauthorized();
    }

    public function test_unauthenticated_get_player_status_returns_401(): void
    {
        $this->getJson('/api/player/' . fake()->uuid() . '/status')->assertUnauthorized();
    }

    // =========================================================================
    // GET /api/rig — correct response structure
    // =========================================================================

    public function test_get_rig_returns_correct_structure(): void
    {
        [$user] = $this->scaffold();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/rig')
             ->assertOk()
             ->assertJsonStructure([
                 'rig_id', 'chassis', 'is_limping', 'current_ss', 'max_ss',
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

    public function test_get_rig_returns_404_when_player_has_no_rig(): void
    {
        // User exists but has no player record → 404
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/rig')
             ->assertNotFound();
    }

    // =========================================================================
    // POST /api/rig/damage — PvE only
    // =========================================================================

    public function test_pve_damage_without_zero_returns_null_event(): void
    {
        // current_ss=90: 10% SS lost → no degradation tiers fire.
        // effectiveFW = base(2) + level(2) = 4. ICE=14 → damage=max(1,14-4)=10 → new SS=80.
        [$user, $player] = $this->scaffold(['current_ss' => 90, 'firewall_level' => 2]);
        $node = $this->createActionNode(ice: 14, canvasId: 'dmg-test-node');

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/damage', [
                 'node_canvas_id' => $node->canvas_id,
                 'source'         => 'pve',
             ])
             ->assertOk()
             ->assertJsonFragment(['event' => null, 'current_ss' => 80]);
    }

    public function test_pve_damage_to_zero_triggers_critical_failure(): void
    {
        // ICE=30 → damage=26, enough to zero out SS=10.
        [$user, $player] = $this->scaffold(
            ['current_ss' => 10, 'firewall_level' => 2],
            [],
            ['pocket_creds' => 500],
        );
        $node = $this->createActionNode(ice: 30, canvasId: 'dmg-zero-node');

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/damage', [
                 'node_canvas_id' => $node->canvas_id,
                 'source'         => 'pve',
             ])
             ->assertOk()
             ->assertJsonFragment(['event' => 'critical_failure', 'current_ss' => 25, 'is_limping' => true]);
    }

    public function test_pvp_source_rejected_by_damage_endpoint(): void
    {
        // PvP damage is handled by CombatController — this endpoint only accepts 'pve'.
        [$user, $player] = $this->scaffold(['current_ss' => 50]);
        $node = $this->createActionNode(ice: 5, canvasId: 'pvp-reject-node');

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/damage', [
                 'node_canvas_id' => $node->canvas_id,
                 'source'         => 'pvp',
             ])
             ->assertUnprocessable();
    }

    // =========================================================================
    // POST /api/rig/upgrade — tax event at cap
    // =========================================================================

    public function test_upgrade_below_cap_returns_null_tax(): void
    {
        // total = 5 (all at 1), cap = 10 — room to upgrade without tax
        [$user, $player] = $this->scaffold(
            ['cpu_level' => 1, 'ram_level' => 1, 'firewall_level' => 1, 'storage_level' => 1, 'os_level' => 1],
            [],
            ['wallet_creds' => 10000, 'tech_points' => 100],
        );
        $this->placePlayerAtCyberDoc($player);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/upgrade', ['stat' => 'os'])
             ->assertOk()
             ->assertJsonFragment(['stat_upgraded' => 'os', 'tax_event' => null]);
    }

    public function test_upgrade_at_cap_returns_tax_event(): void
    {
        // All at 2, total = 10 = cap. Upgrading OS should tax RAM.
        [$user, $player] = $this->scaffold(
            ['cpu_level' => 2, 'ram_level' => 2, 'firewall_level' => 2, 'storage_level' => 2, 'os_level' => 2],
            [],
            ['wallet_creds' => 10000, 'tech_points' => 100],
        );
        $this->placePlayerAtCyberDoc($player);

        $response = $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/upgrade', ['stat' => 'os'])
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
        [$user, $player] = $this->scaffold(
            ['cpu_level' => 10],
            ['total_point_cap' => 10],
            ['wallet_creds' => 10000, 'tech_points' => 100],
        );
        $this->placePlayerAtCyberDoc($player);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/upgrade', ['stat' => 'cpu'])
             ->assertUnprocessable();
    }

    public function test_upgrade_returns_403_when_not_at_cyberdoc(): void
    {
        [$user, $player] = $this->scaffold(
            ['cpu_level' => 1, 'os_level' => 1],
            [],
            ['wallet_creds' => 10000, 'tech_points' => 100],
        );
        // player->current_node_id is null by default → 403

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/upgrade', ['stat' => 'os'])
             ->assertForbidden();
    }

    // =========================================================================
    // POST /api/rig/repair — restores SS to max
    // =========================================================================

    public function test_repair_restores_ss_to_max(): void
    {
        [$user] = $this->scaffold(['current_ss' => 25]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/rig/repair', [])
             ->assertOk()
             ->assertJsonFragment(['current_ss' => 100, 'max_ss' => 100]);
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
             ->postJson('/api/rig/repair', ['repair_peripherals' => true])
             ->assertOk();

        $this->assertDatabaseHas('player_peripherals', [
            'player_id'  => $player->id,
            'is_damaged' => false,
        ]);
    }
}
