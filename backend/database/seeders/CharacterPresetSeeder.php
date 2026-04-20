<?php

namespace Database\Seeders;

use App\Models\CharacterPreset;
use Illuminate\Database\Seeder;

/**
 * キャラクタープリセット(テンプレート)。
 *
 * v1.2: 基本ステ (STR/VIT/DEX/INT) + 初期 Lv + growth_preset_key を保持。
 * HP/ATK/DEF は派生値なので持たない。
 */
class CharacterPresetSeeder extends Seeder
{
    public function run(): void
    {
        $presets = [
            // -- プレイヤー素体 --
            [
                'key' => 'hero_warrior', 'name' => '戦士',
                'base_str' => 14, 'base_vit' => 14, 'base_dex' => 8, 'base_int' => 4,
                'base_level' => 1, 'growth_preset_key' => 'warrior_normal',
                'ai_type' => null, 'is_enemy' => false,
            ],
            [
                'key' => 'hero_mage', 'name' => '魔導士',
                'base_str' => 6, 'base_vit' => 7, 'base_dex' => 9, 'base_int' => 16,
                'base_level' => 1, 'growth_preset_key' => 'mage_normal',
                'ai_type' => null, 'is_enemy' => false,
            ],
            [
                'key' => 'hero_rogue', 'name' => '盗賊',
                'base_str' => 9, 'base_vit' => 8, 'base_dex' => 16, 'base_int' => 6,
                'base_level' => 1, 'growth_preset_key' => 'rogue_normal',
                'ai_type' => null, 'is_enemy' => false,
            ],
            [
                'key' => 'hero_priest', 'name' => '僧侶',
                'base_str' => 8, 'base_vit' => 13, 'base_dex' => 8, 'base_int' => 12,
                'base_level' => 1, 'growth_preset_key' => 'priest_normal',
                'ai_type' => null, 'is_enemy' => false,
            ],

            // -- 敵プリセット --
            [
                'key' => 'enemy_goblin', 'name' => 'ゴブリン',
                'base_str' => 10, 'base_vit' => 8, 'base_dex' => 6, 'base_int' => 2,
                'base_level' => 1, 'growth_preset_key' => 'enemy_easy',
                'ai_type' => 'basic', 'is_enemy' => true,
            ],
            [
                'key' => 'enemy_ogre', 'name' => 'オーガ',
                'base_str' => 16, 'base_vit' => 14, 'base_dex' => 4, 'base_int' => 1,
                'base_level' => 3, 'growth_preset_key' => 'enemy_hard',
                'ai_type' => 'basic', 'is_enemy' => true,
            ],
            [
                'key' => 'enemy_slime', 'name' => 'スライム',
                'base_str' => 4, 'base_vit' => 10, 'base_dex' => 4, 'base_int' => 2,
                'base_level' => 1, 'growth_preset_key' => 'enemy_easy',
                'ai_type' => 'basic', 'is_enemy' => true,
            ],
        ];

        foreach ($presets as $preset) {
            CharacterPreset::updateOrCreate(['key' => $preset['key']], $preset);
        }
    }
}
