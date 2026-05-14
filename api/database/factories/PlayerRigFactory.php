<?php

namespace Database\Factories;

use App\Models\ChassisTemplate;
use App\Models\Player;
use App\Models\PlayerRig;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlayerRig> */
class PlayerRigFactory extends Factory
{
    public function definition(): array
    {
        return [
            'player_id'           => Player::factory(),
            'chassis_template_id' => ChassisTemplate::factory(),
            'cpu_level'           => 1,
            'ram_level'           => 1,
            'firewall_level'      => 1,
            'storage_level'       => 1,
            'os_level'            => 1,
            'current_ss'          => 10,
            'is_limping'          => false,
        ];
    }
}
