<?php

namespace Database\Seeders;

use App\Models\Character;
use App\Models\CharacterPreset;
use App\Models\GrowthPreset;
use App\Models\House;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * 本番 (Railway) で繰り返し流しても安全なように、
     * 各 seeder は「対応マスタが既に存在するならスキップ」ガードを通す。
     * (各 seeder 自体も updateOrCreate で冪等だが、不要な再スキャンを避ける)
     */
    public function run(): void
    {
        $plan = [
            SystemAccountSeeder::class => fn () => User::where('id', (int) config('arena.system_user_id'))->exists(),
            GuestHouseSeeder::class => fn () => House::where('id', (int) config('arena.guest_house_id'))->exists(),
            GrowthPresetSeeder::class => fn () => GrowthPreset::query()->exists(),
            CharacterPresetSeeder::class => fn () => CharacterPreset::query()->exists(),
            JobSeekerSeeder::class => fn () => Character::query()->exists(),
        ];

        foreach ($plan as $seeder => $existsCheck) {
            if ($existsCheck()) {
                $this->command?->info("[skip] {$seeder}: already seeded");

                continue;
            }
            $this->call($seeder);
        }
    }
}
