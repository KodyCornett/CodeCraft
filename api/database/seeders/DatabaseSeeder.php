<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->create([
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
        ]);

        $this->call(PeripheralSeeder::class);
        $this->call(ConsumableSeeder::class);
        $this->call(PlayerSeeder::class);     // must run after User factory above
        $this->call(NodeSeeder::class);
        $this->call(SpawnNodeSeeder::class);  // must run after NodeSeeder
        $this->call(CommandSeeder::class);
        $this->call(StreetDocSeeder::class);
    }
}
