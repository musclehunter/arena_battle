<?php

return [
    'tick_ms' => 100,
    'max_gauge' => 10000,
    'skills' => [
        'normal' => ['name' => '攻撃', 'power' => 1.0, 'cast_gauge' => 5000, 'cooldown_gauge' => 2000, 'scales_with' => 'atk'],
        'guard' => ['name' => '防御', 'power' => 0.0, 'cast_gauge' => 2500, 'cooldown_gauge' => 4000, 'scales_with' => null],
    ],
    // DEX段階補正: 階段状にジャンプアップしつつ、段階間も線形補間で徐々に上昇
    // DEX 1:   100  → 攻撃 5.0秒
    // DEX 50:  106  → 攻撃 4.7秒  (徐々に上昇)
    // DEX 100: 120  → 攻撃 4.2秒  ← ジャンプ
    // DEX 150: 126  → 攻撃 4.0秒  (徐々に上昇)
    // DEX 200: 140  → 攻撃 3.6秒  ← ジャンプ
    // DEX 500: 180  → 攻撃 2.8秒  ← ジャンプ
    // DEX 1000: 240 → 攻撃 2.1秒  ← 最終ジャンプ
    'dex_tiers' => [
        1 => 100,
        50 => 106,
        100 => 120,
        150 => 126,
        200 => 140,
        300 => 150,
        500 => 180,
        700 => 200,
        1000 => 240,
    ],
    // 基本ステータス上限
    'stat_cap' => 1000,
    // 防御のDEF補正倍率
    'guard_def_multiplier' => 1.8,
    // レベルアップ1バトル上限
    'max_level_ups_per_battle' => 5,
];
