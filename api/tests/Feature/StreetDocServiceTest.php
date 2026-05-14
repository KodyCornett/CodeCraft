<?php

namespace Tests\Feature;

use App\Models\ChassisTemplate;
use App\Models\Command;
use App\Models\District;
use App\Models\HardwareEncrypt;
use App\Models\Node;
use App\Models\Peripheral;
use App\Models\Player;
use App\Models\PlayerPeripheral;
use App\Models\PlayerRig;
use App\Models\StreetDoc;
use App\Services\BountyService;
use App\Services\RigService;
use App\Services\StreetDocService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StreetDocServiceTest extends TestCase
{
    use RefreshDatabase;

    private StreetDocService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StreetDocService(new RigService(), new BountyService());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Create a player with a rig. Defaults: all stats level 1, full SS (10), not limping. */
    private function makePlayer(array $playerOverrides = [], array $rigOverrides = [], array $chassisOverrides = []): array
    {
        $chassis = ChassisTemplate::factory()->create(array_merge([
            'total_point_cap'  => 20,
            'peripheral_slots' => 4,
        ], $chassisOverrides));

        $player = Player::factory()->create($playerOverrides);

        $rigDefaults = [
            'player_id'          => $player->id,
            'chassis_template_id' => $chassis->id,
            'cpu_level'          => 1,
            'ram_level'          => 2, // 2 loadout slots by default
            'firewall_level'     => 1,
            'storage_level'      => 1,
            'os_level'           => 2, // max SS = 20
            'current_ss'         => 20,
            'is_limping'         => false,
        ];

        $rig = PlayerRig::factory()->create(array_merge($rigDefaults, $rigOverrides));
        $rig->load('chassis');

        return [$player, $rig, $chassis];
    }

    /** Create a StreetDoc with the minimum required relations (District + Node). */
    private function makeStreetDoc(): StreetDoc
    {
        $district = District::create(['name' => 'Test District']);
        $node = Node::create([
            'district_id'              => $district->id,
            'business_name'            => 'Test Node',
            'latitude'                 => 47.6588,
            'longitude'                => -117.4260,
            'tier'                     => 1,
            'cred_value_base'          => 100,
            'cred_resource_depleted'   => false,
            'movement_resource_depleted' => false,
        ]);

        return StreetDoc::create([
            'node_id'     => $node->id,
            'district_id' => $district->id,
            'name'        => 'Test Street Doc',
        ]);
    }

    /** Create a Command and assign it to the player via player_commands. */
    private function assignCommand(Player $player, bool $isActive = false): Command
    {
        $command = Command::create([
            'name'        => 'TestCmd_' . uniqid(),
            'type'        => 'offensive',
            'description' => 'Test command',
        ]);

        DB::table('player_commands')->insert([
            'player_id'  => $player->id,
            'command_id' => $command->id,
            'is_active'  => $isActive,
        ]);

        return $command;
    }

    /** Create a Peripheral + HardwareEncrypt for the player. */
    private function makeEncrypt(Player $player, array $peripheralOverrides = [], bool $alreadyInstalled = false): array
    {
        $peripheral = Peripheral::factory()->create(array_merge(
            ['stat_boosted' => 'cpu', 'boost_amount' => 1, 'port_cost' => 1],
            $peripheralOverrides,
        ));

        $encrypt = HardwareEncrypt::create([
            'player_id'     => $player->id,
            'peripheral_id' => $peripheral->id,
            'is_installed'  => $alreadyInstalled,
        ]);

        return [$encrypt, $peripheral];
    }

    // =========================================================================
    // visitStreetDoc
    // =========================================================================

    public function test_visit_sets_last_street_doc_id(): void
    {
        [$player] = $this->makePlayer();
        $doc = $this->makeStreetDoc();

        $this->service->visitStreetDoc($player, $doc->id);

        $this->assertSame($doc->id, $player->fresh()->last_street_doc_id);
    }

    public function test_visit_returns_street_doc_model(): void
    {
        [$player] = $this->makePlayer();
        $doc = $this->makeStreetDoc();

        $result = $this->service->visitStreetDoc($player, $doc->id);

        $this->assertSame($doc->id, $result->id);
        $this->assertSame('Test Street Doc', $result->name);
    }

    public function test_visit_triggers_extract_when_nodes_hacked(): void
    {
        [$player] = $this->makePlayer(['nodes_hacked_this_run' => 5, 'bounty_level' => 5]);
        $doc = $this->makeStreetDoc();

        $this->service->visitStreetDoc($player, $doc->id);

        // extractToStreetDoc resets nodes_hacked_this_run to 0
        $this->assertSame(0, $player->fresh()->nodes_hacked_this_run);
    }

    public function test_visit_triggers_extract_when_pvp_wins_exist(): void
    {
        [$player] = $this->makePlayer(['pvp_wins_this_run' => 2, 'bounty_level' => 0]);
        $doc = $this->makeStreetDoc();

        $this->service->visitStreetDoc($player, $doc->id);

        $this->assertSame(0, $player->fresh()->pvp_wins_this_run);
    }

    public function test_visit_does_not_extract_when_no_active_run(): void
    {
        [$player] = $this->makePlayer([
            'nodes_hacked_this_run' => 0,
            'pvp_wins_this_run'     => 0,
            'bounty_level'          => 0,
        ]);
        $doc = $this->makeStreetDoc();

        $this->service->visitStreetDoc($player, $doc->id);

        // Counters stay at zero — extract was not called
        $this->assertSame(0, $player->fresh()->nodes_hacked_this_run);
        $this->assertSame(0, $player->fresh()->pvp_wins_this_run);
    }

    public function test_visit_throws_when_street_doc_not_found(): void
    {
        [$player] = $this->makePlayer();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->visitStreetDoc($player, '00000000-0000-0000-0000-000000000000');
    }

    // =========================================================================
    // repairCost
    // =========================================================================

    public function test_repair_cost_is_missing_ss_times_two(): void
    {
        [$player, $rig] = $this->makePlayer([], ['os_level' => 3, 'current_ss' => 10]);
        // max SS = 30, missing = 20, cost = 40

        $cost = $this->service->repairCost($player);

        $this->assertSame(40, $cost);
    }

    public function test_repair_cost_is_zero_when_ss_is_full(): void
    {
        [$player, $rig] = $this->makePlayer([], ['os_level' => 2, 'current_ss' => 20]);

        $this->assertSame(0, $this->service->repairCost($player));
    }

    public function test_repair_cost_throws_when_no_rig(): void
    {
        $player = Player::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->service->repairCost($player);
    }

    // =========================================================================
    // repairSS
    // =========================================================================

    public function test_repair_ss_restores_to_maximum(): void
    {
        [$player, $rig] = $this->makePlayer([], ['os_level' => 3, 'current_ss' => 5]);

        $this->service->repairSS($player, 0);

        $this->assertSame(30, $rig->fresh()->current_ss);
    }

    public function test_repair_ss_clears_limping_flag(): void
    {
        [$player, $rig] = $this->makePlayer(
            ['is_limping' => true],
            ['current_ss' => 1, 'is_limping' => true],
        );

        $this->service->repairSS($player, 0);

        $this->assertFalse($player->fresh()->is_limping);
    }

    public function test_repair_ss_succeeds_when_already_at_max(): void
    {
        [$player, $rig] = $this->makePlayer([], ['os_level' => 2, 'current_ss' => 20]);

        $this->service->repairSS($player, 0);

        $this->assertSame(20, $rig->fresh()->current_ss);
    }

    public function test_repair_ss_throws_when_no_rig(): void
    {
        $player = Player::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->service->repairSS($player, 0);
    }

    // =========================================================================
    // installEncrypt
    // =========================================================================

    public function test_install_encrypt_creates_player_peripheral(): void
    {
        [$player] = $this->makePlayer();
        [$encrypt, $peripheral] = $this->makeEncrypt($player);

        $playerPeripheral = $this->service->installEncrypt($player, $encrypt->id);

        $this->assertSame($peripheral->id, $playerPeripheral->peripheral_id);
        $this->assertTrue($playerPeripheral->is_installed);
    }

    public function test_install_encrypt_marks_encrypt_as_installed(): void
    {
        [$player] = $this->makePlayer();
        [$encrypt] = $this->makeEncrypt($player);

        $this->service->installEncrypt($player, $encrypt->id);

        $this->assertTrue($encrypt->fresh()->is_installed);
    }

    public function test_install_encrypt_throws_when_encrypt_not_found(): void
    {
        [$player] = $this->makePlayer();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->installEncrypt($player, '00000000-0000-0000-0000-000000000000');
    }

    public function test_install_encrypt_throws_when_encrypt_belongs_to_different_player(): void
    {
        [$player] = $this->makePlayer();
        [$otherPlayer] = $this->makePlayer();
        [$encrypt] = $this->makeEncrypt($otherPlayer);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->installEncrypt($player, $encrypt->id);
    }

    public function test_install_encrypt_throws_when_already_installed(): void
    {
        [$player] = $this->makePlayer();
        [$encrypt] = $this->makeEncrypt($player, [], alreadyInstalled: true);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->installEncrypt($player, $encrypt->id);
    }

    public function test_install_encrypt_throws_when_port_slots_exceeded(): void
    {
        // chassis has 4 slots, fill them up first
        [$player, $rig, $chassis] = $this->makePlayer([], []);

        // Install 4 peripherals (port_cost 1 each) to fill all slots
        for ($i = 0; $i < 4; $i++) {
            $peripheral = Peripheral::factory()->create(['port_cost' => 1]);
            PlayerPeripheral::create([
                'player_id'     => $player->id,
                'peripheral_id' => $peripheral->id,
                'is_installed'  => true,
                'is_damaged'    => false,
            ]);
        }

        [$encrypt] = $this->makeEncrypt($player);

        $this->expectException(\RuntimeException::class);
        $this->service->installEncrypt($player, $encrypt->id);
    }

    // =========================================================================
    // setLoadout
    // =========================================================================

    public function test_set_loadout_activates_specified_commands(): void
    {
        [$player] = $this->makePlayer([], ['ram_level' => 2]);
        $cmd1 = $this->assignCommand($player);
        $cmd2 = $this->assignCommand($player);

        $this->service->setLoadout($player, [$cmd1->id, $cmd2->id]);

        $active = DB::table('player_commands')
            ->where('player_id', $player->id)
            ->where('is_active', true)
            ->pluck('command_id')
            ->toArray();

        $this->assertContains($cmd1->id, $active);
        $this->assertContains($cmd2->id, $active);
    }

    public function test_set_loadout_deactivates_commands_not_in_list(): void
    {
        [$player] = $this->makePlayer([], ['ram_level' => 2]);
        $cmd1 = $this->assignCommand($player, isActive: true);
        $cmd2 = $this->assignCommand($player, isActive: true);

        // Only activate cmd1
        $this->service->setLoadout($player, [$cmd1->id]);

        $isCmd2Active = DB::table('player_commands')
            ->where('player_id', $player->id)
            ->where('command_id', $cmd2->id)
            ->value('is_active');

        $this->assertFalse((bool) $isCmd2Active);
    }

    public function test_set_loadout_accepts_empty_array(): void
    {
        [$player] = $this->makePlayer([], ['ram_level' => 2]);
        $cmd1 = $this->assignCommand($player, isActive: true);

        $this->service->setLoadout($player, []);

        $isActive = DB::table('player_commands')
            ->where('player_id', $player->id)
            ->where('command_id', $cmd1->id)
            ->value('is_active');

        $this->assertFalse((bool) $isActive);
    }

    public function test_set_loadout_throws_when_count_exceeds_ram_slots(): void
    {
        // base_ram=0 so effective RAM = ram_level only → 1 slot max
        [$player] = $this->makePlayer([], ['ram_level' => 1], ['base_ram' => 0]);
        $cmd1 = $this->assignCommand($player);
        $cmd2 = $this->assignCommand($player);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->setLoadout($player, [$cmd1->id, $cmd2->id]);
    }

    public function test_set_loadout_throws_when_command_not_owned(): void
    {
        [$player] = $this->makePlayer([], ['ram_level' => 2]);
        $unownedCommand = Command::create([
            'name'        => 'Unowned',
            'type'        => 'offensive',
            'description' => 'Not in player inventory',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->setLoadout($player, [$unownedCommand->id]);
    }

    public function test_set_loadout_throws_when_no_rig(): void
    {
        $player = Player::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->service->setLoadout($player, []);
    }

    // =========================================================================
    // reallocateStats
    // =========================================================================

    public function test_reallocate_decrements_from_stat_and_increments_to_stat(): void
    {
        [$player, $rig] = $this->makePlayer([], ['cpu_level' => 3, 'ram_level' => 1]);

        $result = $this->service->reallocateStats($player, 'cpu', 'ram');

        $fresh = $rig->fresh();
        $this->assertSame(2, $fresh->cpu_level);
        $this->assertSame(2, $fresh->ram_level);
        $this->assertSame('cpu', $result['from']['stat']);
        $this->assertSame(2, $result['from']['new_level']);
        $this->assertSame('ram', $result['to']['stat']);
        $this->assertSame(2, $result['to']['new_level']);
    }

    public function test_reallocate_caps_ss_when_os_is_reduced(): void
    {
        // os_level=2 → max SS=20. After moving OS→CPU, os_level=1 → max SS=10.
        // current_ss=20 must be capped to 10.
        [$player, $rig] = $this->makePlayer([], ['os_level' => 2, 'cpu_level' => 1, 'current_ss' => 20]);

        $this->service->reallocateStats($player, 'os', 'cpu');

        $fresh = $rig->fresh();
        $this->assertSame(1, $fresh->os_level);
        $this->assertSame(10, $fresh->current_ss);
    }

    public function test_reallocate_does_not_cap_ss_when_os_not_involved(): void
    {
        [$player, $rig] = $this->makePlayer([], ['cpu_level' => 3, 'ram_level' => 1, 'current_ss' => 20]);

        $this->service->reallocateStats($player, 'cpu', 'ram');

        // SS unchanged — OS was not touched
        $this->assertSame(20, $rig->fresh()->current_ss);
    }

    public function test_reallocate_throws_when_from_stat_at_minimum(): void
    {
        [$player] = $this->makePlayer([], ['cpu_level' => 1]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->reallocateStats($player, 'cpu', 'ram');
    }

    public function test_reallocate_throws_when_from_and_to_are_same(): void
    {
        [$player] = $this->makePlayer([], ['cpu_level' => 3]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->reallocateStats($player, 'cpu', 'cpu');
    }

    public function test_reallocate_throws_for_invalid_stat_name(): void
    {
        [$player] = $this->makePlayer();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->reallocateStats($player, 'hacking', 'ram');
    }

    public function test_reallocate_throws_when_no_rig(): void
    {
        $player = Player::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->service->reallocateStats($player, 'cpu', 'ram');
    }
}
