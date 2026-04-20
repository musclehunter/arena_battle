<?php

namespace App\Services\Battle;

use App\Enums\BattleActionType;
use App\Models\Battle;

/**
 * 敵の行動を決めるクラス。
 *
 * v1 方針(設計書 5.3 に沿いつつ助言で少し賢くする):
 *  - 前ターンでプレイヤーが強攻撃を打ってきたら、次はカウンター寄りに
 *  - 前ターンでプレイヤーがカウンターを打ってきたら、次は強攻撃しない(弱攻撃 or カウンター)
 *  - 初手 / それ以外は等確率の 3 択
 *
 * 乱数は battle_id + turn_number で決定論的にするため mt_srand で種を固定。
 * こうするとリプレイが再現でき、テストも書きやすい。
 */
final class EnemyActionDecider
{
    public function decide(Battle $battle): BattleActionType
    {
        $lastPlayerAction = $this->lastPlayerAction($battle);

        // 乱数シードを battle_id + turn_number から決定
        mt_srand($battle->id * 1000 + $battle->turn_number);

        $candidates = match ($lastPlayerAction) {
            BattleActionType::Strong => [
                // 強攻撃の後はカウンター警戒 → カウンター多め
                BattleActionType::Counter,
                BattleActionType::Counter,
                BattleActionType::Weak,
            ],
            BattleActionType::Counter => [
                // カウンター読みされやすいので強攻撃は避ける
                BattleActionType::Weak,
                BattleActionType::Counter,
            ],
            default => [
                // 初手 or 弱攻撃の後は等確率
                BattleActionType::Weak,
                BattleActionType::Strong,
                BattleActionType::Counter,
            ],
        };

        $choice = $candidates[mt_rand(0, count($candidates) - 1)];

        // srand の影響を後続処理に波及させない
        mt_srand();

        return $choice;
    }

    private function lastPlayerAction(Battle $battle): ?BattleActionType
    {
        $lastLog = $battle->logs()
            ->whereNotNull('player_action')
            ->orderByDesc('turn_number')
            ->first();

        return $lastLog?->player_action;
    }
}
