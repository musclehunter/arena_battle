<?php

namespace App\Http\Resources;

use App\Models\Battle;
use App\Services\Character\CharacterStats;
use App\Services\Character\LevelUpService;

/**
 * Battle を Inertia ページ向けのプレーン配列にプレゼンテーションする。
 * v1.1: player は Character + Preset を合成、enemy は Preset のみ。
 */
final class BattleViewModel
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Battle $battle): array
    {
        $battle->loadMissing(['playerCharacter.preset', 'enemyPreset', 'logs']);

        $character = $battle->playerCharacter;
        $preset = $character?->preset;
        $enemyPreset = $battle->enemyPreset;

        $playerDerived = $character ? CharacterStats::forEntity($character) : ['hp' => 0, 'atk' => 0, 'def' => 0];
        $enemyDerived = $enemyPreset ? CharacterStats::forPreset($enemyPreset) : ['hp' => 0, 'atk' => 0, 'def' => 0];

        return [
            'id' => $battle->id,
            'status' => $battle->status->value,
            'winner' => $battle->winner?->value,
            'turn_number' => $battle->turn_number,
            'action_token' => $battle->action_token,
            'is_guest_battle' => $battle->isGuestBattle(),
            'player' => [
                'character_id' => $character?->id,
                'name' => $character?->name,
                'hp' => $battle->player_hp,
                'max_hp' => $playerDerived['hp'],
                'level' => $character?->level,
                'exp' => $character?->exp,
                'next_exp' => $character ? LevelUpService::requiredExpToNext($character) : null,
                'stats' => $character ? [
                    'str' => $character->str,
                    'vit' => $character->vit,
                    'dex' => $character->dex,
                    'int_stat' => $character->int_stat,
                    'atk' => $playerDerived['atk'],
                    'def' => $playerDerived['def'],
                ] : null,
            ],
            'enemy' => [
                'name' => $enemyPreset?->name,
                'hp' => $battle->enemy_hp,
                'max_hp' => $enemyDerived['hp'],
                'level' => $enemyPreset?->base_level,
                'stats' => $enemyPreset ? [
                    'atk' => $enemyDerived['atk'],
                    'def' => $enemyDerived['def'],
                ] : null,
            ],
            'reward' => [
                'total' => $battle->reward_gold_total,
                'to_character' => $battle->reward_gold_to_character,
                'to_house' => $battle->reward_gold_to_house,
            ],
            'logs' => $battle->logs->map(fn ($log) => [
                'turn_number' => $log->turn_number,
                'summary_text' => $log->summary_text,
            ])->values(),
        ];
    }
}
