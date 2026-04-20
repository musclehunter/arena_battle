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
        $this->call([
            SystemAccountSeeder::class,
            GuestHouseSeeder::class,
            GrowthPresetSeeder::class,     // character_preset より前に成長プリセットを揃える
            CharacterPresetSeeder::class,
            JobSeekerSeeder::class,
        ]);
    }
}
