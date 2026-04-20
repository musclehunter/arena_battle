<?php

namespace Tests\Concerns;

use App\Models\Character;
use App\Models\CharacterPreset;
use App\Models\House;
use App\Models\User;
use App\Services\Character\GrowthRank;
use Database\Seeders\CharacterPresetSeeder;
use Database\Seeders\GrowthPresetSeeder;
use Database\Seeders\GuestHouseSeeder;
use Database\Seeders\SystemAccountSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Arena v1.1 の Feature/Unit テスト用共通ヘルパー。
 *
 * - System user / Guest house / CharacterPreset のシード
 * - House / Character / 求職者などを任意パラメータで作成
 */
trait CreatesArenaFixtures
{
    protected function seedArenaBasics(): void
    {
        $this->seed([
            SystemAccountSeeder::class,
            GuestHouseSeeder::class,
            GrowthPresetSeeder::class,
            CharacterPresetSeeder::class,
        ]);
    }

    protected function createPlayerUser(string $email = null): User
    {
        $user = User::create([
            'name' => 'Tester',
            'email' => $email ?? ('tester_'.uniqid().'@test.local'),
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    protected function createHouseFor(User $user, array $overrides = []): House
    {
        return House::create(array_merge([
            'user_id' => $user->id,
            'name' => 'TestHouse',
            'level' => 1,
            'gold' => 1000,
        ], $overrides));
    }

    /**
     * 求職者キャラ(house_id=NULL)を 1 体作成して返す。
     *
     * preset 未指定時は hero_warrior を使う(seedArenaBasics 済み前提)。
     */
    protected function createJobSeeker(array $overrides = [], string $presetKey = 'hero_warrior'): Character
    {
        $preset = CharacterPreset::where('key', $presetKey)->firstOrFail();

        return Character::create(array_merge([
            'character_preset_id' => $preset->id,
            'name' => 'Seeker_'.uniqid(),
            'level' => 1,
            'exp' => 0,
            'str' => (int) $preset->base_str,
            'vit' => (int) $preset->base_vit,
            'dex' => (int) $preset->base_dex,
            'int_stat' => (int) $preset->base_int,
            'growth_preset_key' => $preset->growth_preset_key,
            'growth_index' => 0,
            'growth_rank_box' => ($rank = GrowthRank::rankFromKey($preset->growth_preset_key))
                && GrowthRank::exists($rank)
                ? GrowthRank::initialBox($rank)
                : null,
            'hire_cost' => 100,
            'reward_share_bp' => 3000,
            'gold' => 50,
            'house_id' => null,
            'hired_at' => null,
        ], $overrides));
    }

    /**
     * 家門に雇用済みのキャラを 1 体作成して返す。
     */
    protected function createHiredCharacter(House $house, array $overrides = [], string $presetKey = 'hero_warrior'): Character
    {
        return $this->createJobSeeker(array_merge([
            'house_id' => $house->id,
            'hired_at' => now(),
        ], $overrides), $presetKey);
    }

    /**
     * 敵プリセット取得(テストで決定的に使うため)。
     */
    protected function enemyPreset(string $key = 'enemy_slime'): CharacterPreset
    {
        return CharacterPreset::where('key', $key)->firstOrFail();
    }
}
