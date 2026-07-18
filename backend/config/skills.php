<?php

// スキルマスタ定義
// job: warrior(戦士), rogue(盗賊), priest(僧侶), mage(魔導士※封印中)
// line: 系統（近接攻撃, 遠隔攻撃, 信仰支援, 魔法, 防御）
// target_type: enemy_single, enemy_area, ally_single, self
// effect_type: buff_atk, buff_def, debuff_speed, heal, dot_poison, reflect
// is_passive: パッシブスキル（戦闘中常に効果）

return [
    'jobs' => [
        'warrior' => ['name' => '戦士', 'line' => '近接攻撃系', 'icon_key' => 'human_warrior'],
        'rogue' => ['name' => '盗賊', 'line' => '遠隔攻撃系', 'icon_key' => 'human_rogue'],
        'priest' => ['name' => '僧侶', 'line' => '信仰打撃系', 'icon_key' => 'human_priest'],
        // mage は創発アンロック後に解放
    ],

    'skills' => [
        // === 戦士（近接攻撃系）===
        [
            'key' => 'warrior_slash', 'name' => '烈斬', 'job' => 'warrior', 'line' => '近接攻撃',
            'description' => '斬属性の強攻撃。威力が高いが発動が遅い。',
            'sp_cost' => 1, 'unlock_level' => 1,
            'scales_with' => 'atk', 'power' => 2.0, 'cast_gauge' => 7000, 'cooldown_gauge' => 2000,
            'element' => '斬', 'target_type' => 'enemy_single', 'target_count' => 1,
            'effect_type' => null, 'effect_power' => 0, 'effect_duration' => 0,
            'is_passive' => false,
        ],
        [
            'key' => 'warrior_focus', 'name' => '気合', 'job' => 'warrior', 'line' => '近接攻撃',
            'description' => '次の攻撃の攻撃力を上げる。',
            'sp_cost' => 1, 'unlock_level' => 3,
            'scales_with' => null, 'power' => 0.0, 'cast_gauge' => 3000, 'cooldown_gauge' => 4000,
            'element' => null, 'target_type' => 'self', 'target_count' => 1,
            'effect_type' => 'buff_atk', 'effect_power' => 50, 'effect_duration' => 1,
            'is_passive' => false,
        ],
        [
            'key' => 'warrior_thrust', 'name' => '突進斬り', 'job' => 'warrior', 'line' => '近接攻撃',
            'description' => '突属性で敵を攻撃。発動が速い。',
            'sp_cost' => 2, 'unlock_level' => 5,
            'scales_with' => 'atk', 'power' => 1.3, 'cast_gauge' => 3500, 'cooldown_gauge' => 2500,
            'element' => '突', 'target_type' => 'enemy_single', 'target_count' => 1,
            'effect_type' => null, 'effect_power' => 0, 'effect_duration' => 0,
            'is_passive' => false,
        ],
        [
            'key' => 'warrior_whirlwind', 'name' => '旋風斬', 'job' => 'warrior', 'line' => '近接攻撃',
            'description' => '斬属性の範囲攻撃。複数の敵にダメージ。',
            'sp_cost' => 3, 'unlock_level' => 10,
            'scales_with' => 'atk', 'power' => 1.2, 'cast_gauge' => 6000, 'cooldown_gauge' => 3000,
            'element' => '斬', 'target_type' => 'enemy_area', 'target_count' => 3,
            'effect_type' => null, 'effect_power' => 0, 'effect_duration' => 0,
            'is_passive' => false,
        ],

        // === 盗賊（遠隔攻撃系）===
        [
            'key' => 'rogue_snipe', 'name' => '狙撃', 'job' => 'rogue', 'line' => '遠隔攻撃',
            'description' => '突属性の単体高ダメージ。',
            'sp_cost' => 1, 'unlock_level' => 1,
            'scales_with' => 'atk', 'power' => 1.8, 'cast_gauge' => 5500, 'cooldown_gauge' => 2000,
            'element' => '突', 'target_type' => 'enemy_single', 'target_count' => 1,
            'effect_type' => null, 'effect_power' => 0, 'effect_duration' => 0,
            'is_passive' => false,
        ],
        [
            'key' => 'rogue_poison', 'name' => '毒矢', 'job' => 'rogue', 'line' => '遠隔攻撃',
            'description' => '突属性攻撃＋毒（継続ダメージ）。',
            'sp_cost' => 2, 'unlock_level' => 3,
            'scales_with' => 'atk', 'power' => 0.8, 'cast_gauge' => 4000, 'cooldown_gauge' => 3000,
            'element' => '突', 'target_type' => 'enemy_single', 'target_count' => 1,
            'effect_type' => 'dot_poison', 'effect_power' => 5, 'effect_duration' => 5,
            'is_passive' => false,
        ],
        [
            'key' => 'rogue_suppress', 'name' => '牽制射', 'job' => 'rogue', 'line' => '遠隔攻撃',
            'description' => '敵の攻撃速度を下げる。',
            'sp_cost' => 2, 'unlock_level' => 5,
            'scales_with' => 'atk', 'power' => 0.5, 'cast_gauge' => 3000, 'cooldown_gauge' => 3500,
            'element' => '突', 'target_type' => 'enemy_single', 'target_count' => 1,
            'effect_type' => 'debuff_speed', 'effect_power' => 30, 'effect_duration' => 5,
            'is_passive' => false,
        ],
        [
            'key' => 'rogue_volley', 'name' => '乱射', 'job' => 'rogue', 'line' => '遠隔攻撃',
            'description' => '複数の敵に突属性の範囲ダメージ。',
            'sp_cost' => 3, 'unlock_level' => 10,
            'scales_with' => 'atk', 'power' => 1.0, 'cast_gauge' => 5000, 'cooldown_gauge' => 3000,
            'element' => '突', 'target_type' => 'enemy_area', 'target_count' => 3,
            'effect_type' => null, 'effect_power' => 0, 'effect_duration' => 0,
            'is_passive' => false,
        ],

        // === 僧侶（信仰打撃系）===
        [
            'key' => 'priest_smash', 'name' => '聖鈍撃', 'job' => 'priest', 'line' => '信仰打撃',
            'description' => '打属性の強攻撃。鈍器で敵を叩き潰す。',
            'sp_cost' => 1, 'unlock_level' => 1,
            'scales_with' => 'atk', 'power' => 1.8, 'cast_gauge' => 6000, 'cooldown_gauge' => 2000,
            'element' => '打', 'target_type' => 'enemy_single', 'target_count' => 1,
            'effect_type' => null, 'effect_power' => 0, 'effect_duration' => 0,
            'is_passive' => false,
        ],
        [
            'key' => 'priest_shield_guard', 'name' => '盾防御', 'job' => 'priest', 'line' => '信仰打撃',
            'description' => '盾で身を守り、防御力を一時的に上げる。',
            'sp_cost' => 2, 'unlock_level' => 3,
            'scales_with' => null, 'power' => 0.0, 'cast_gauge' => 2500, 'cooldown_gauge' => 4000,
            'element' => null, 'target_type' => 'self', 'target_count' => 1,
            'effect_type' => 'buff_def_self', 'effect_power' => 50, 'effect_duration' => 3,
            'is_passive' => false,
        ],
        [
            'key' => 'priest_holy_strike', 'name' => '聖光打', 'job' => 'priest', 'line' => '信仰打撃',
            'description' => '打属性の高速攻撃。発動が速い。',
            'sp_cost' => 2, 'unlock_level' => 5,
            'scales_with' => 'atk', 'power' => 1.3, 'cast_gauge' => 3500, 'cooldown_gauge' => 2500,
            'element' => '打', 'target_type' => 'enemy_single', 'target_count' => 1,
            'effect_type' => null, 'effect_power' => 0, 'effect_duration' => 0,
            'is_passive' => false,
        ],
        [
            'key' => 'priest_judgement', 'name' => '裁きの鈍撃', 'job' => 'priest', 'line' => '信仰打撃',
            'description' => '打属性の範囲攻撃。複数の敵を叩き潰す。',
            'sp_cost' => 3, 'unlock_level' => 10,
            'scales_with' => 'atk', 'power' => 1.4, 'cast_gauge' => 7000, 'cooldown_gauge' => 3000,
            'element' => '打', 'target_type' => 'enemy_area', 'target_count' => 3,
            'effect_type' => null, 'effect_power' => 0, 'effect_duration' => 0,
            'is_passive' => false,
        ],
    ],
];
