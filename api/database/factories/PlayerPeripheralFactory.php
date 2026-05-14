<?php

namespace Database\Factories;

use App\Models\Peripheral;
use App\Models\Player;
use App\Models\PlayerPeripheral;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlayerPeripheral> */
class PlayerPeripheralFactory extends Factory
{
    public function definition(): array
    {
        return [
            'player_id'     => Player::factory(),
            'peripheral_id' => Peripheral::factory(),
            'is_installed'  => false,
            'is_damaged'    => false,
        ];
    }
}
