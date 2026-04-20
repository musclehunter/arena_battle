<?php

namespace App\Services\Battle;

use App\Enums\BattleActionType;
use App\Enums\BattleWinner;

/**
 * summary_text 生成の責務を持つ。
 *
 * 出力順ルール(設計書 7.5):
 *   1. プレイヤーの行動宣言
 *   2. 敵の行動宣言
 *   3. 解決結果(カウンター成否・ダメージ・HP 変化)
 *   4. 決着時はそのメッセージも末尾に追加
 */
final class BattleLogFactory
{
    public function buildSummary(
        int $turnNumber,
        BattleActionType $playerAction,
        BattleActionType $enemyAction,
        TurnResolution $resolution,
        int $playerHpAfter,
        int $enemyHpAfter,
        ?BattleWinner $winner,
    ): string {
        $lines = [];

        $prefix = "{$turnNumber}ターン目:";

        // 1. 宣言
        $lines[] = "{$prefix} あなたは" . $this->actionLabel($playerAction) . "した。";
        $lines[] = "{$prefix} 敵は" . $this->actionLabel($enemyAction) . "した。";

        // 2. 結果(プレイヤー視点)
        foreach ($this->outcomeLines($prefix, $resolution) as $line) {
            $lines[] = $line;
        }

        // 3. 決着
        if ($winner !== null) {
            $lines[] = match ($winner) {
                BattleWinner::Player => '敵を倒した。あなたの勝利。',
                BattleWinner::Enemy => 'あなたは倒れた。敗北…',
                BattleWinner::Draw => '相討ち。引き分け。',
            };
        }

        // 4. 現在HP
        $lines[] = "現在HP: あなた {$playerHpAfter} / 敵 {$enemyHpAfter}";

        return implode("\n", $lines);
    }

    /**
     * 非ダメージ系(カウンター失敗 / 不発 / 相殺)を先に、ダメージ系を後に並べる。
     * 設計書10章のログ表示例に合わせた順序。
     *
     * @return list<string>
     */
    private function outcomeLines(string $prefix, TurnResolution $r): array
    {
        $nonDamage = [];
        $damage = [];

        // プレイヤー側のアウトカム
        switch ($r->playerOutcome) {
            case TurnOutcome::Attacked:
                if ($r->playerDamageToEnemy > 0) {
                    $damage[] = "{$prefix} 敵に{$r->playerDamageToEnemy}ダメージ。";
                }
                break;
            case TurnOutcome::AttackNullified:
                $nonDamage[] = "{$prefix} あなたの攻撃は敵のカウンターで不発になった。";
                break;
            case TurnOutcome::CounterSucceeded:
                $damage[] = "{$prefix} カウンター成功! 敵に{$r->playerDamageToEnemy}ダメージ。";
                break;
            case TurnOutcome::CounterFailed:
                $nonDamage[] = "{$prefix} あなたのカウンターは失敗した。";
                break;
            case TurnOutcome::CounterNullified:
                $nonDamage[] = "{$prefix} カウンターがかち合い、双方不発。";
                break;
        }

        // 敵側のアウトカム(プレイヤー側で既に言及済みのケースは重複を避ける)
        switch ($r->enemyOutcome) {
            case TurnOutcome::Attacked:
                if ($r->enemyDamageToPlayer > 0) {
                    $damage[] = "{$prefix} あなたは{$r->enemyDamageToPlayer}ダメージを受けた。";
                }
                break;
            case TurnOutcome::CounterSucceeded:
                // プレイヤーの攻撃が AttackNullified のとき発生。被ダメを明示
                $damage[] = "{$prefix} 敵のカウンターが成功。あなたは{$r->enemyDamageToPlayer}ダメージを受けた。";
                break;
            case TurnOutcome::CounterFailed:
                $nonDamage[] = "{$prefix} 敵のカウンターは失敗した。";
                break;
            // 以下はプレイヤー側の出力で十分カバーされるためスキップ
            case TurnOutcome::AttackNullified:
            case TurnOutcome::CounterNullified:
                break;
        }

        return array_merge($nonDamage, $damage);
    }

    private function actionLabel(BattleActionType $action): string
    {
        return match ($action) {
            BattleActionType::Weak => '弱攻撃',
            BattleActionType::Strong => '強攻撃',
            BattleActionType::Counter => 'カウンター',
        };
    }
}
