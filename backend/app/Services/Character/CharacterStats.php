<?php

namespace App\Services\Character;

/**
 * 基本ステ (STR/VIT/DEX/INT) から派生ステ (HP/ATK/DEF) を算出する純関数。
 *
 * 係数は config('arena.derived_stats') から取得。
 */
final class CharacterStats
{
    /**
     * @param  array{str:int, vit:int, dex:int, int_stat:int}  $base
     * @return array{hp:int, atk:int, def:int}
     */
    public static function derive(array $base): array
    {
        $c = config('arena.derived_stats');

        $hp = (int) floor($base['str'] * $c['str_hp'] + $base['vit'] * $c['vit_hp']) + (int) $c['hp_const'];
        $atk = (int) floor($base['str'] * $c['str_atk'] + $base['dex'] * $c['dex_atk']);
        $def = (int) floor($base['vit'] * $c['vit_def'] + $base['dex'] * $c['dex_def']);

        return [
            'hp' => max(1, $hp),
            'atk' => max(0, $atk),
            'def' => max(0, $def),
        ];
    }

    /**
     * キャラモデルから派生ステを取得するためのヘルパ。
     *
     * @param  object{str:int, vit:int, dex:int, int_stat:int}  $entity
     * @return array{hp:int, atk:int, def:int}
     */
    public static function forEntity(object $entity): array
    {
        return self::derive([
            'str' => (int) ($entity->str ?? 0),
            'vit' => (int) ($entity->vit ?? 0),
            'dex' => (int) ($entity->dex ?? 0),
            'int_stat' => (int) ($entity->int_stat ?? 0),
        ]);
    }

    /**
     * CharacterPreset の base_* から派生ステを算出。
     */
    public static function forPreset(object $preset): array
    {
        return self::derive([
            'str' => (int) ($preset->base_str ?? 0),
            'vit' => (int) ($preset->base_vit ?? 0),
            'dex' => (int) ($preset->base_dex ?? 0),
            'int_stat' => (int) ($preset->base_int ?? 0),
        ]);
    }
}
