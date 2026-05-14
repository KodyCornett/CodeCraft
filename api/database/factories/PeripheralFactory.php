<?php

namespace Database\Factories;

use App\Models\Peripheral;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Peripheral> */
class PeripheralFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'         => fake()->words(3, true),
            'stat_boosted' => fake()->randomElement(['cpu', 'ram', 'firewall', 'storage', 'os']),
            'boost_amount' => 1,
            'rarity'       => 'common',
            'port_cost'    => 1,
        ];
    }
}
