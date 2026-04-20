<?php

namespace App\Services\Character;

use App\Models\Character;
use App\Models\GrowthPreset;
use Illuminate\Support\Facades\DB;

/**
 * EXP 加算と Lvup(基本ステ上昇・成長プリセット切替)を担うサービス。
 *
 * - 次 Lv 必要 EXP = 現在の成長プリセットの index=growth_index の増分合計
 *                  × config('arena.leveling.required_exp_per_stat_point')
 * - 上昇値合計が大きいほど必要 EXP も大きくなる(壁 Lv は自動的に重くなる)
 * - 10 Lv 使い切る(growth_index==10)と、ランク抽選箱から次ランクを引いて
 *   次プリセット (= {job}_{引いたランク}) に切替、growth_index=0 に戻る。
 *
 * ランク抽選仕様 (config arena.rank_box):
 * - 初期箱 = 現在×initial_current + 次×initial_next
 * - 引いたランクは箱から 1 つ除外
 * - ランクアップ時のみ「下位×add_lower + 次×add_next」を追加(easy は下位なし)
 * - easy の下位は追加しない、master の次は easy へループ
 */
final class LevelUpService
{
    /**
     * 敵の Lv から獲得 EXP を算出。
     */
    public static function rewardExpFromEnemyLevel(int $enemyLevel): int
    {
        $per = (int) config('arena.leveling.exp_per_enemy_level', 10);

        return max(0, $enemyLevel * $per);
    }

    /**
     * 現在の成長 index における Lvup 必要 EXP を返す。
     *
     * 成長プリセットが見つからない場合は保守的に 100 を返す。
     */
    public static function requiredExpToNext(Character $character): int
    {
        $preset = self::currentGrowthPreset($character);
        if (! $preset) {
            return 100;
        }

        $inc = $preset->incrementAt((int) $character->growth_index);
        $sum = $inc['str'] + $inc['vit'] + $inc['dex'] + $inc['int_stat'];
        $coef = (int) config('arena.leveling.required_exp_per_stat_point', 10);

        return max(1, $sum * $coef);
    }

    /**
     * バトル勝利などで EXP を加算し、必要量に到達していれば可能なだけ Lvup する。
     *
     * @return array{levels_gained:int, applied_increments:list<array{str:int,vit:int,dex:int,int_stat:int}>, preset_switched:bool}
     */
    public static function grantExp(Character $character, int $amount): array
    {
        if ($amount <= 0) {
            return ['levels_gained' => 0, 'applied_increments' => [], 'preset_switched' => false];
        }

        return DB::transaction(function () use ($character, $amount): array {
            $character->refresh();
            $character->exp = (int) $character->exp + $amount;

            $maxLevel = (int) config('arena.leveling.max_level', 99);
            $maxUps = (int) config('arena.leveling.max_level_ups_per_battle', 5);

            $levelsGained = 0;
            $applied = [];
            $presetSwitched = false;

            while (
                $character->level < $maxLevel
                && $levelsGained < $maxUps
            ) {
                $required = self::requiredExpToNext($character);
                if ($character->exp < $required) {
                    break;
                }

                $preset = self::currentGrowthPreset($character);
                if (! $preset) {
                    break; // 成長プリセット未設定 → Lvup できない
                }

                $inc = $preset->incrementAt((int) $character->growth_index);
                $character->str = (int) $character->str + $inc['str'];
                $character->vit = (int) $character->vit + $inc['vit'];
                $character->dex = (int) $character->dex + $inc['dex'];
                $character->int_stat = (int) $character->int_stat + $inc['int_stat'];
                $character->exp = (int) $character->exp - $required;
                $character->level = (int) $character->level + 1;
                $character->growth_index = (int) $character->growth_index + 1;
                $applied[] = $inc;
                $levelsGained++;

                // 10 Lv 使い切ったら抽選で次のプリセットへ切替
                if ($character->growth_index >= 10) {
                    $newKey = self::advanceRank($character);
                    $presetSwitched = ($newKey !== null);
                }
            }

            $character->save();

            return [
                'levels_gained' => $levelsGained,
                'applied_increments' => $applied,
                'preset_switched' => $presetSwitched,
            ];
        });
    }

    /**
     * 抽選箱からランクを 1 つ引いて次のプリセットを決定する。
     * character モデルは save 前提(呼び出し側でまとめて save される)。
     *
     * @param  string|null  $forcedPick  テスト用: 箱の中から確定的に引くランクを指定。
     *                                   箱にそのランクがない場合は通常抽選にフォールバック。
     * @return string|null  新しいプリセット key(切替できなかった場合 null)
     */
    public static function advanceRank(Character $character, ?string $forcedPick = null): ?string
    {
        $currentKey = (string) $character->growth_preset_key;
        $job = GrowthRank::jobFromKey($currentKey);
        $currentRank = GrowthRank::rankFromKey($currentKey);

        if (! $job || ! $currentRank || ! GrowthRank::exists($currentRank)) {
            // ランク/職が解釈できない → index リセットのみ
            $character->growth_index = 0;

            return null;
        }

        $box = self::ensureBox($character, $currentRank);

        if (empty($box)) {
            $box = GrowthRank::initialBox($currentRank);
        }

        // 箱から 1 つ引く(forcedPick 指定時はそのランクを優先)
        $pickIndex = null;
        if ($forcedPick !== null) {
            $pickIndex = array_search($forcedPick, $box, true);
            if ($pickIndex === false) {
                $pickIndex = null;
            }
        }
        if ($pickIndex === null) {
            $pickIndex = random_int(0, count($box) - 1);
        }
        $picked = $box[$pickIndex];
        array_splice($box, $pickIndex, 1);

        $isRankUp = ($picked === GrowthRank::next($currentRank));
        $isMaintain = ($picked === $currentRank);
        // それ以外 = ランクダウン
        // master→easy のループもランクアップ扱いになる

        if ($isRankUp) {
            // ランクアップ: 残った箱を全員 1 ランク **シフトアップ** し、
            // 旧current × add_lower_on_rankup と 新currentの次 × add_next_on_rankup を追加。
            // (current がひとつ上がったので、箱内の全ランクを相対的に +1 する、という観点)
            $box = array_map(static fn (string $r): string => GrowthRank::next($r), $box);

            $addLower = (int) config('arena.rank_box.add_lower_on_rankup', 1);
            $addNext = (int) config('arena.rank_box.add_next_on_rankup', 1);

            // 旧current (= 新currentの下位) を add_lower 個追加。
            // easy→...の逆で、master→easy ループ時は旧current=master が新current(easy)の下位扱いになる
            for ($i = 0; $i < $addLower; $i++) {
                $box[] = $currentRank;
            }
            $nextOfPicked = GrowthRank::next($picked);
            for ($i = 0; $i < $addNext; $i++) {
                $box[] = $nextOfPicked;
            }
        } elseif (! $isMaintain) {
            // ランクダウン: 残った箱を全員 1 ランク **シフトダウン** するのみ。追加はしない。
            $box = array_map(static fn (string $r): string => GrowthRank::prev($r), $box);
        }
        // 維持: 追加処理なし。引いた分が除外されるのみ。

        $newKey = GrowthRank::composeKey($job, $picked);
        $character->growth_preset_key = $newKey;
        $character->growth_index = 0;
        $character->growth_rank_box = array_values($box);

        return $newKey;
    }

    /**
     * 抽選箱を取得。未初期化なら初期化して返す。
     *
     * @return list<string>
     */
    private static function ensureBox(Character $character, string $currentRank): array
    {
        $box = $character->growth_rank_box;
        if (! is_array($box) || empty($box)) {
            $box = GrowthRank::initialBox($currentRank);
            $character->growth_rank_box = $box;
        }

        return array_values($box);
    }

    private static function currentGrowthPreset(Character $character): ?GrowthPreset
    {
        $key = $character->growth_preset_key;
        if (! $key) {
            return null;
        }

        return GrowthPreset::where('key', $key)->first();
    }
}
