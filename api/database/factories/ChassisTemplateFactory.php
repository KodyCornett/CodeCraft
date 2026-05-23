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
            'name'             => fake()->words(2, true) . ' Chassis',
            'tier'             => 1,
            // Stat bases — OS base is deliberately higher than CPU/RAM/FW/STG so
            // tests exercising the cap/parasite mechanics are not interfered with
            // by the OS gate (which blocks any stat whose effective equals or exceeds OS).
            'base_cpu'         => 2,
            'base_ram'         => 2,
            'base_firewall'    => 2,
            'base_storage'     => 2,
            'base_os'          => 'Test OS',
            'base_os_level'    => 10,   // high enough that the OS gate never fires in generic tests
            // Per-stat effective caps (base + max investable) — generous for test rigs
            'cap_cpu'          => 20,
            'cap_ram'          => 20,
            'cap_firewall'     => 20,
            'cap_storage'      => 20,
            'cap_os'           => 20,
            'peripheral_slots' => 4,
            'base_uplink'      => 3,
            'total_point_cap'  => 10,
        ];
    }
}
