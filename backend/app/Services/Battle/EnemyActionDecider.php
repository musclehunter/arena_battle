<?php

namespace App\Services\Battle;

use App\Enums\BattleActionType;
use App\Models\Battle;

/**
 * 敵の行動を決めるクラス。
 *
 * 重みテーブル方式:
 *  - デフォルト(初手/プレイヤー弱攻撃後): 弱/強/カウンター 各10 の均等ベース。
 *    ただし直前に敵が同じ行動を使っていたら、その行動の重みを -5 して連続しにくくする。
 *  - プレイヤーが強攻撃後: カウンター多め(50%)、弱/強 各25%
 *  - プレイヤーがカウンター後: 強攻撃は避ける(弱50% / カウンター50%)
 *
 * 乱数はマイクロ秒を混ぜてランダム性を確保しつつ、固定シードの連続偏りを解消。
 */
final class EnemyActionDecider
{
    public function decide(Battle $battle): BattleActionType
    {
        $lastPlayerAction = $this->lastPlayerAction($battle);
        $lastEnemyAction  = $this->lastEnemyAction($battle);

        mt_srand((int) (microtime(true) * 1000) ^ ($battle->id * 997));

        $weights = $this->buildWeights($lastPlayerAction, $lastEnemyAction);

        $choice = $this->pickByWeight($weights);

        mt_srand();

        return $choice;
    }

    /**
     * @return array<string, int>  key = BattleActionType->value, value = 重み(正の整数)
     */
    private function buildWeights(
        ?BattleActionType $lastPlayer,
        ?BattleActionType $lastEnemy,
    ): array {
        $weights = match ($lastPlayer) {
            BattleActionType::Strong => [
                // プレイヤーが強攻撃 → カウンター多め、弱/強 均等
                BattleActionType::Weak->value    => 25,
                BattleActionType::Strong->value  => 25,
                BattleActionType::Counter->value => 50,
            ],
            BattleActionType::Counter => [
                // プレイヤーがカウンター → 強攻撃は避けめ
                BattleActionType::Weak->value    => 45,
                BattleActionType::Strong->value  => 10,
                BattleActionType::Counter->value => 45,
            ],
            default => [
                // 初手 or プレイヤー弱攻撃後 → 均等
                BattleActionType::Weak->value    => 10,
                BattleActionType::Strong->value  => 10,
                BattleActionType::Counter->value => 10,
            ],
        };

        // デフォルト時のみ: 直前に敵が使った行動は連続しにくくする補正
        if ($lastPlayer === null || $lastPlayer === BattleActionType::Weak) {
            if ($lastEnemy !== null && isset($weights[$lastEnemy->value])) {
                $weights[$lastEnemy->value] = max(0, $weights[$lastEnemy->value] - 5);
            }
        }

        return $weights;
    }

    /**
     * 重みテーブルから行動を1つ選ぶ。
     *
     * @param array<string, int> $weights
     */
    private function pickByWeight(array $weights): BattleActionType
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            return BattleActionType::Weak;
        }

        $rand = mt_rand(1, $total);
        $cumulative = 0;
        foreach ($weights as $actionValue => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return BattleActionType::from($actionValue);
            }
        }

        return BattleActionType::Weak;
    }

    private function lastPlayerAction(Battle $battle): ?BattleActionType
    {
        $lastLog = $battle->logs()
            ->whereNotNull('player_action')
            ->orderByDesc('turn_number')
            ->first();

        return $lastLog?->player_action;
    }

    private function lastEnemyAction(Battle $battle): ?BattleActionType
    {
        $lastLog = $battle->logs()
            ->whereNotNull('enemy_action')
            ->orderByDesc('turn_number')
            ->first();

        return $lastLog?->enemy_action;
    }
}
