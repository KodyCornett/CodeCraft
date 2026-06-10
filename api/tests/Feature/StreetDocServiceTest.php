<?php

namespace Tests\Feature;

use App\Models\ChassisTemplate;
use App\Models\Command;
use App\Models\HardwareEncrypt;
use App\Models\Peripheral;
use App\Models\Player;
use App\Models\PlayerPeripheral;
use App\Models\PlayerRig;
use App\Services\BountyService;
use App\Services\RigService;
use App\Services\CyberDocService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StreetDocServiceTest extends TestCase
{
    use RefreshDatabase;

    private CyberDocService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CyberDocService(new RigService(), new BountyService());
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
            'ram_level'          => 2,
            'firewall_level'     => 1,
            'storage_level'      => 1,
            'os_level'           => 2,
            'current_ss'         => 100, // max SS is always 100 — flat for all rigs
            'is_limping'         => false,
        ];

        $rig = PlayerRig::factory()->create(array_merge($rigDefaults, $rigOverrides));
        $rig->load('chassis');

        return [$player, $rig, $chassis];
    }

    /** Create a Command and assign it to the player via player_commands. */
    private function assignCommand(Player $player, bool $isActive = false, string $context = 'map'): Command
    {
        $command = Command::create([
            'name'        => 'TestCmd_' . uniqid(),
            'type'        => 'offensive',
            'context'     => $context,
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
    // repairCost
    // =========================================================================

    public function test_repair_cost_uses_flat_100_max_formula(): void
    {
        // max SS = 100 (flat). current_ss=10, missing=90.
        // cost = floor(90/100 × 600) = 540
        [$player, $rig] = $this->makePlayer([], ['current_ss' => 10]);

        $cost = $this->service->repairCost($player);

        $this->assertSame(540, $cost);
    }

    public function test_repair_cost_is_zero_when_ss_is_full(): void
    {
        [$player, $rig] = $this->makePlayer([], ['current_ss' => 100]);

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
        [$player, $rig] = $this->makePlayer([], ['current_ss' => 5]);

        $this->service->repairSS($player, 0);

        $this->assertSame(100, $rig->fresh()->current_ss);
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
        [$player, $rig] = $this->makePlayer([], ['current_ss' => 100]);

        $this->service->repairSS($player, 0);

        $this->assertSame(100, $rig->fresh()->current_ss);
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
        // Chassis has 1 map + 1 open slot → 2 map commands fit (1 typed + 1 overflow to open)
        [$player] = $this->makePlayer([], [], ['base_map_slots' => 1, 'base_hack_slots' => 0, 'base_open_slots' => 1]);
        $cmd1 = $this->assignCommand($player, context: 'map');
        $cmd2 = $this->assignCommand($player, context: 'map');

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
        [$player] = $this->makePlayer([], [], ['base_map_slots' => 1, 'base_hack_slots' => 1, 'base_open_slots' => 1]);
        $cmd1 = $this->assignCommand($player, isActive: true, context: 'map');
        $cmd2 = $this->assignCommand($player, isActive: true, context: 'hack');

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
        [$player] = $this->makePlayer([], [], ['base_map_slots' => 1, 'base_hack_slots' => 1, 'base_open_slots' => 1]);
        $cmd1 = $this->assignCommand($player, isActive: true, context: 'map');

        $this->service->setLoadout($player, []);

        $isActive = DB::table('player_commands')
            ->where('player_id', $player->id)
            ->where('command_id', $cmd1->id)
            ->value('is_active');

        $this->assertFalse((bool) $isActive);
    }

    public function test_set_loadout_throws_when_count_exceeds_slot_capacity(): void
    {
        // Chassis has 1 map slot and 0 open slots → only 1 map command allowed.
        [$player] = $this->makePlayer([], [], ['base_map_slots' => 1, 'base_hack_slots' => 0, 'base_open_slots' => 0]);
        $cmd1 = $this->assignCommand($player, context: 'map');
        $cmd2 = $this->assignCommand($player, context: 'map');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->setLoadout($player, [$cmd1->id, $cmd2->id]);
    }

    public function test_set_loadout_throws_when_map_commands_exceed_map_and_open_slots(): void
    {
        // 1 map slot, 0 open slots → 2 map commands must fail typed validation.
        [$player] = $this->makePlayer([], [], ['base_map_slots' => 1, 'base_hack_slots' => 1, 'base_open_slots' => 0]);
        $cmd1 = $this->assignCommand($player, context: 'map');
        $cmd2 = $this->assignCommand($player, context: 'map');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->setLoadout($player, [$cmd1->id, $cmd2->id]);
    }

    public function test_set_loadout_allows_map_command_in_open_slot(): void
    {
        // 1 map slot + 1 open slot → 2 map commands should fit
        [$player] = $this->makePlayer([], [], ['base_map_slots' => 1, 'base_hack_slots' => 0, 'base_open_slots' => 1]);
        $cmd1 = $this->assignCommand($player, context: 'map');
        $cmd2 = $this->assignCommand($player, context: 'map');

        $this->service->setLoadout($player, [$cmd1->id, $cmd2->id]);

        $count = DB::table('player_commands')
            ->where('player_id', $player->id)
            ->where('is_active', true)
            ->count();

        $this->assertSame(2, $count);
    }

    public function test_set_loadout_throws_when_command_not_owned(): void
    {
        [$player] = $this->makePlayer([], [], ['base_map_slots' => 1, 'base_hack_slots' => 1, 'base_open_slots' => 1]);
        $unownedCommand = Command::create([
            'name'        => 'Unowned',
            'type'        => 'offensive',
            'context'     => 'map',
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

    public function test_reallocate_does_not_affect_ss_when_os_is_reduced(): void
    {
        // SS is flat 100 — reducing OS does not change current_ss.
        [$player, $rig] = $this->makePlayer([], ['os_level' => 2, 'cpu_level' => 1, 'current_ss' => 60]);

        $this->service->reallocateStats($player, 'os', 'cpu');

        $fresh = $rig->fresh();
        $this->assertSame(1, $fresh->os_level);
        $this->assertSame(60, $fresh->current_ss);
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
