<?php

namespace Database\Seeders;

use App\Models\Character;
use App\Models\House;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoArenaSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@arenabattle.local'],
            [
                'name' => 'Demo Commander',
                'password' => Hash::make('arena-demo-2026'),
                'email_verified_at' => now(),
            ],
        );
        $house = House::updateOrCreate(
            ['user_id' => $user->id],
            ['name' => '暁の家門', 'level' => 1, 'gold' => 3000],
        );
        $characters = Character::query()->whereNull('house_id')->orderBy('id')->limit(5)->get();
        if ($characters->count() !== 5) {
            throw new RuntimeException('確認用遠征隊に必要な契約者が足りません。');
        }
        foreach ($characters as $character) {
            $character->update(['house_id' => $house->id, 'hired_at' => now()]);
        }
        $party = Party::updateOrCreate(
            ['house_id' => $house->id],
            ['name' => '暁の遠征隊', 'strategy' => 'balanced', 'risk' => 'normal'],
        );
        $party->members()->delete();
        foreach ($characters->values() as $slot => $character) {
            $party->members()->create(['character_id' => $character->id, 'slot' => $slot]);
        }
    }
}
