<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Player> */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'                  => User::factory(),
            'handle'                   => fake()->unique()->userName(),
            'current_node_id'          => null,
            'current_district'         => null,
            'bounty_level'             => 0,
            'nodes_hacked_this_run'    => 0,
            'pvp_wins_this_run'        => 0,
            'bounty_multiplier'        => 1.00,
            'bounty_district_snapshot' => null,
            'is_open_season'           => false,
            'open_season_wins'         => 0,
            'open_season_current_wins' => 0,
            'open_season_best_wins'    => 0,
            'is_limping'               => false,
            'post_combat_silent_moves' => 0,
            'last_street_doc_id'       => null,
        ];
    }
}
