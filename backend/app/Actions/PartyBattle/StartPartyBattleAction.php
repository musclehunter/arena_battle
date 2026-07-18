<?php

namespace App\Actions\PartyBattle;

use App\Models\CharacterPreset;
use App\Models\Party;
use App\Models\PartyBattle;
use App\Services\Character\CharacterStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StartPartyBattleAction
{
    public function execute(Party $party): PartyBattle
    {
        return DB::transaction(function () use ($party): PartyBattle {
            $party->load('members.character.preset');
            $size = (int) config('arena.party.size', 5);
            if ($party->members->count() !== $size) {
                throw ValidationException::withMessages(['party' => "遠征には{$size}名の編成が必要です。"]);
            }
            if (PartyBattle::query()->where('house_id', $party->house_id)->where('status', 'in_progress')->exists()) {
                throw ValidationException::withMessages(['party' => 'すでに出撃中の遠征隊があります。']);
            }

            $players = $party->members->map(function ($member) {
                $character = $member->character;
                $stats = CharacterStats::forEntity($character);
                $skillKeys = $character->skills()->pluck('skills.key')->all();
                return ['character_id' => $character->id, 'name' => $character->name, 'level' => $character->level, 'hp' => $stats['hp'], 'max_hp' => $stats['hp'], 'atk' => $stats['atk'], 'def' => $stats['def'], 'int' => $character->int_stat, 'speed' => $character->dex, 'icon_key' => $character->preset->icon_key, 'icon_index' => $character->icon_index, 'gender' => $character->gender ? strtolower($character->gender->name) : 'unknown', 'learned_skills' => $skillKeys];
            })->values()->all();
            $presets = CharacterPreset::query()->where('is_enemy', true)->get();
            if ($presets->isEmpty()) throw new \RuntimeException('敵の部隊データがありません。');
            $enemies = collect(range(1, $size))->map(function () use ($presets) {
                $preset = $presets->random();
                $stats = CharacterStats::forPreset($preset);
                return ['preset_id' => $preset->id, 'name' => $preset->name, 'level' => $preset->base_level, 'hp' => $stats['hp'], 'max_hp' => $stats['hp'], 'atk' => $stats['atk'], 'def' => $stats['def'], 'int' => $preset->base_int, 'speed' => $preset->base_dex, 'icon_key' => $preset->icon_key, 'icon_index' => 0, 'gender' => 'unknown'];
            })->all();

            return PartyBattle::create(['house_id' => $party->house_id, 'party_id' => $party->id, 'strategy' => $party->strategy ?? 'balanced', 'risk' => $party->risk ?? 'normal', 'status' => 'in_progress', 'round' => 0, 'player_state' => $players, 'enemy_state' => $enemies, 'logs' => [['round' => 0, 'events' => ['遠征隊は魔境へ足を踏み入れた。']]], 'started_at' => now()]);
        });
    }
}
