<?php

namespace Database\Seeders;

use App\Models\GrowthPreset;
use App\Services\Character\GrowthRank;
use Illuminate\Database\Seeder;

/**
 * 成長プリセットのシード(v1.2.1)。
 *
 * 4 職(warrior/rogue/mage/priest) + 敵(enemy) の各々に、
 * 5 ランク(easy/normal/hard/expert/master) のプリセットを用意する。
 * 合計 25 プリセット。
 *
 * 各プリセットは 10 要素の増分配列を持ち、
 *   increments[0..8] = 通常 step (職特性×ランク倍率)
 *   increments[9]    = 壁 step    (通常合計の約 3 倍)
 * とする。ランクが高いほど 1 step の増分合計が大きく、必要 EXP も増える。
 *
 * 10 Lv 消化時は LevelUpService::advanceRank() が抽選箱からランクを引き、
 *   次プリセット key = "{job}_{引いたランク}" に切替える。
 */
class GrowthPresetSeeder extends Seeder
{
    /**
     * ランク倍率。通常 step の合計が「ベース合計 × 倍率」になるよう調整する。
     */
    private const RANK_SCALES = [
        'easy'   => 0.7,
        'normal' => 1.0,
        'hard'   => 1.4,
        'expert' => 1.8,
        'master' => 2.3,
    ];

    /**
     * 職ごとの「1 step ベース配分」(合計 ≒ 5 を想定)。
     * ランク倍率をかけて 10 step の増分を生成する。
     *
     * @var array<string, array<string, float>>
     */
    private const JOB_WEIGHTS = [
        'warrior' => ['str' => 2.2, 'vit' => 1.6, 'dex' => 1.0, 'int_stat' => 0.2],
        'rogue'   => ['str' => 1.2, 'vit' => 1.0, 'dex' => 2.4, 'int_stat' => 0.4],
        'mage'    => ['str' => 0.4, 'vit' => 1.0, 'dex' => 1.0, 'int_stat' => 2.6],
        'priest'  => ['str' => 1.0, 'vit' => 2.0, 'dex' => 0.8, 'int_stat' => 1.2],
        // 敵は均等 + STR/VIT 寄り
        'enemy'   => ['str' => 1.8, 'vit' => 1.8, 'dex' => 1.0, 'int_stat' => 0.4],
    ];

    /**
     * 職の表示名。
     */
    private const JOB_LABEL = [
        'warrior' => '武人',
        'rogue'   => '俊足',
        'mage'    => '魔導',
        'priest'  => '聖職',
        'enemy'   => '魔物',
    ];

    /**
     * ランクの表示名。
     */
    private const RANK_LABEL = [
        'easy'   => '初等',
        'normal' => '中等',
        'hard'   => '上等',
        'expert' => '熟達',
        'master' => '達人',
    ];

    public function run(): void
    {
        foreach (self::JOB_WEIGHTS as $job => $weights) {
            foreach (GrowthRank::RANKS as $rankOrder => $rank) {
                $scale = self::RANK_SCALES[$rank];
                $increments = $this->buildIncrements($weights, $scale, $job, $rank);

                GrowthPreset::updateOrCreate(
                    ['key' => GrowthRank::composeKey($job, $rank)],
                    [
                        'name' => self::JOB_LABEL[$job].'の'.self::RANK_LABEL[$rank],
                        'job' => $job,
                        'rank' => $rank,
                        'rank_order' => $rankOrder + 1, // 1..5
                        'increments' => $increments,
                    ],
                );
            }
        }
    }

    /**
     * 10 要素の increments を生成。
     * index 0..8 は通常 step(合計 ≒ base_total × scale)。
     * index 9 は壁 step (通常合計の約 3 倍)。
     *
     * @param  array<string, float>  $weights
     * @return list<array{str:int,vit:int,dex:int,int_stat:int}>
     */
    private function buildIncrements(array $weights, float $scale, string $job, string $rank): array
    {
        // step ごとに微妙に配分を変えて変化を持たせるため、
        // 決定論的なシード(job+rank ベース)で小さな揺らぎを付加する。
        $seed = crc32($job.'_'.$rank);

        $incs = [];
        for ($i = 0; $i < 9; $i++) {
            $incs[] = $this->stepIncrement($weights, $scale, $seed + $i, wallMultiplier: 1.0);
        }
        // 壁 step
        $incs[] = $this->stepIncrement($weights, $scale, $seed + 999, wallMultiplier: 3.0);

        return $incs;
    }

    /**
     * 1 step 分の増分を算出。
     *
     * @param  array<string, float>  $weights
     * @return array{str:int,vit:int,dex:int,int_stat:int}
     */
    private function stepIncrement(array $weights, float $scale, int $seed, float $wallMultiplier): array
    {
        // 決定論的な小揺らぎ (-0.15..+0.15)
        $jitter = static function (int $s, string $key) {
            $v = ((int) sprintf('%u', crc32($key.$s)) % 1000) / 1000.0; // 0..1
            return ($v - 0.5) * 0.3; // -0.15..+0.15
        };

        $result = [
            'str' => 0,
            'vit' => 0,
            'dex' => 0,
            'int_stat' => 0,
        ];
        foreach (['str', 'vit', 'dex', 'int_stat'] as $k) {
            $raw = ($weights[$k] ?? 0.0) * $scale * $wallMultiplier;
            $raw = $raw * (1.0 + $jitter($seed, $k));
            $result[$k] = (int) max(0, round($raw));
        }

        // 壁 step は最低でも「増分合計 >= 8」を保証(必要 EXP の跳ね上がりを確実に)
        if ($wallMultiplier > 1.0) {
            $sum = $result['str'] + $result['vit'] + $result['dex'] + $result['int_stat'];
            if ($sum < 8) {
                $result['vit'] += (8 - $sum);
            }
        }

        // 通常 step は合計 1 以上を保証(必要 EXP が 0 にならないように)
        $sum = $result['str'] + $result['vit'] + $result['dex'] + $result['int_stat'];
        if ($sum < 1) {
            $result['vit'] = 1;
        }

        return $result;
    }
}
