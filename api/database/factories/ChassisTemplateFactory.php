<?php

namespace Database\Factories;

use App\Models\ChassisTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChassisTemplate> */
class ChassisTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'            => fake()->words(2, true) . ' Chassis',
            'base_cpu'        => 2,
            'base_ram'        => 2,
            'base_firewall'   => 2,
            'base_storage'    => 2,
            'base_os'         => 'Ubuntu',
            'peripheral_slots' => 4,
            'total_point_cap' => 10,
        ];
    }
}
