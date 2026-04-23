<?php

namespace Database\Seeders;

use App\Models\Character;
use App\Models\CharacterPreset;
use App\Services\Character\GrowthRank;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * 求職者プールを10名生成する。
 *
 * - すべて house_id = NULL (どこにも雇用されていない状態)
 * - 各キャラは preset からコピーしたベース値 + 個別の名前/コスト/取り分を持つ
 * - gold は config('arena.initial_gold.character') で初期化
 * - hire_cost / reward_share_bp は config('arena.job_seeker.*') を基準に
 *   決定論的な揺らぎを与えて生成する(テスト再現性のため random は使わない)
 */
class JobSeekerSeeder extends Seeder
{
    /**
     * 登録する求職者の定義 (preset_key, name, cost_factor, share_factor, level)。
     * cost_factor / share_factor は 1.0 を中央値とした倍率。
     */
    private const SEEKERS = [
        ['hero_warrior', 'アーサー',   1.00, 1.00, 1],
        ['hero_warrior', 'ベオウルフ', 1.20, 1.10, 1],
        ['hero_mage',    'セレス',     1.15, 1.15, 1],
        ['hero_mage',    'ダリア',     0.90, 0.90, 1],
        ['hero_rogue',   'エリス',     1.00, 1.05, 1],
        ['hero_rogue',   'フィン',     0.85, 0.85, 1],
        ['hero_priest',  'ギルバート', 1.10, 1.00, 1],
        ['hero_priest',  'ヘレナ',     0.95, 0.95, 1],
        ['hero_warrior', 'イヴァン',   0.80, 0.80, 1],
        ['hero_mage',    'ジュリア',   1.30, 1.20, 1],
    ];

    public function run(): void
    {
        $characterGold = (int) config('arena.initial_gold.character', 50);
        $costBase      = (int) config('arena.job_seeker.hire_cost_base', 100);
        $shareBase     = (int) config('arena.job_seeker.share_bp_base', 3000);
        $shareMin      = (int) config('arena.job_seeker.share_bp_min', 100);
        $shareMax      = (int) config('arena.job_seeker.share_bp_max', 9000);

        // 既存の求職者(house_id=NULL)が既に揃っているなら何もしない(冪等性)
        if (Character::query()->whereNull('house_id')->count() >= count(self::SEEKERS)) {
            return;
        }

        foreach (self::SEEKERS as $i => [$presetKey, $name, $costFactor, $shareFactor, $level]) {
            $preset = CharacterPreset::where('key', $presetKey)->first();
            if (! $preset) {
                throw new RuntimeException("JobSeekerSeeder: preset '{$presetKey}' が見つかりません。CharacterPresetSeeder を先に実行してください。");
            }

            $hireCost      = (int) round($costBase * $costFactor);
            $rewardShareBp = (int) round($shareBase * $shareFactor);
            $rewardShareBp = max($shareMin, min($shareMax, $rewardShareBp));

            Character::updateOrCreate(
                // name は一意ではないので preset+name の組で一意とみなす(シード用途のみ)
                ['character_preset_id' => $preset->id, 'name' => $name, 'house_id' => null],
                [
                    'level' => $level,
                    'exp' => 0,
                    // 基本ステは preset の base_* をそのままコピー
                    'str' => (int) $preset->base_str,
                    'vit' => (int) $preset->base_vit,
                    'dex' => (int) $preset->base_dex,
                    'int_stat' => (int) $preset->base_int,
                    'growth_preset_key' => $preset->growth_preset_key,
                    'growth_index' => 0,
                    'growth_rank_box' => self::initialBoxFor($preset->growth_preset_key),
                    'hire_cost' => $hireCost,
                    'reward_share_bp' => $rewardShareBp,
                    'gold' => $characterGold,
                    'hired_at' => null,
                    'icon_index' => $i % 9,
                ],
            );
        }
    }

    /**
     * preset key から初期抽選箱を生成。
     * 未知のキー(旧式など)は null を返し、そのキャラは抽選発動時に初期化される。
     *
     * @return list<string>|null
     */
    private static function initialBoxFor(?string $presetKey): ?array
    {
        $rank = GrowthRank::rankFromKey($presetKey);
        if (! $rank || ! GrowthRank::exists($rank)) {
            return null;
        }

        return GrowthRank::initialBox($rank);
    }
}
