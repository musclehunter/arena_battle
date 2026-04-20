<?php

namespace Tests\Unit\Services\Battle;

use App\Enums\BattleActionType;
use App\Services\Battle\DamageCalculator;
use App\Services\Battle\TurnOutcome;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 設計書 9.4 の解決ルール + v1.2 の ATK/DEF ダメージ式を網羅するテスト。
 *
 * テストでは atk=4, def=0 を両者に与え、デフォルト係数
 * (weak=1.0, strong=2.0, counter=1.5, min=1) で
 * 弱=4, 強=8, カウンター=6 と v1 仕様の固定値に一致するよう調整している。
 */
final class DamageCalculatorTest extends TestCase
{
    private const ATK = 4;
    private const DEF = 0;

    private function makeCalculator(): DamageCalculator
    {
        return new DamageCalculator(
            weakMultiplier: 1.0,
            strongMultiplier: 2.0,
            counterMultiplier: 1.5,
            minDamage: 1,
        );
    }

    #[Test]
    #[DataProvider('resolutionMatrixProvider')]
    public function 全9通りのコマンド組み合わせが仕様どおりに解決される(
        BattleActionType $player,
        BattleActionType $enemy,
        TurnOutcome $expectedPlayerOutcome,
        TurnOutcome $expectedEnemyOutcome,
        int $expectedPlayerDamageToEnemy,
        int $expectedEnemyDamageToPlayer,
    ): void {
        $calculator = $this->makeCalculator();

        $result = $calculator->resolve(
            player: $player,
            enemy: $enemy,
            playerAtk: self::ATK,
            playerDef: self::DEF,
            enemyAtk: self::ATK,
            enemyDef: self::DEF,
        );

        $this->assertSame($expectedPlayerOutcome, $result->playerOutcome, 'playerOutcome');
        $this->assertSame($expectedEnemyOutcome, $result->enemyOutcome, 'enemyOutcome');
        $this->assertSame($expectedPlayerDamageToEnemy, $result->playerDamageToEnemy, 'playerDamageToEnemy');
        $this->assertSame($expectedEnemyDamageToPlayer, $result->enemyDamageToPlayer, 'enemyDamageToPlayer');
    }

    public static function resolutionMatrixProvider(): array
    {
        // atk=4, def=0 + デフォルト係数 での期待値
        $weak = 4;     // floor(4 * 1.0) - 0
        $strong = 8;   // floor(4 * 2.0) - 0
        $counter = 6;  // floor(4 * 1.5) - 0

        return [
            '弱 vs 弱' => [
                BattleActionType::Weak, BattleActionType::Weak,
                TurnOutcome::Attacked, TurnOutcome::Attacked,
                $weak, $weak,
            ],
            '弱 vs 強' => [
                BattleActionType::Weak, BattleActionType::Strong,
                TurnOutcome::Attacked, TurnOutcome::Attacked,
                $weak, $strong,
            ],
            '弱 vs カウンター (カウンター失敗)' => [
                BattleActionType::Weak, BattleActionType::Counter,
                TurnOutcome::Attacked, TurnOutcome::CounterFailed,
                $weak, 0,
            ],
            '強 vs 弱' => [
                BattleActionType::Strong, BattleActionType::Weak,
                TurnOutcome::Attacked, TurnOutcome::Attacked,
                $strong, $weak,
            ],
            '強 vs 強' => [
                BattleActionType::Strong, BattleActionType::Strong,
                TurnOutcome::Attacked, TurnOutcome::Attacked,
                $strong, $strong,
            ],
            '強 vs カウンター (カウンター成功)' => [
                BattleActionType::Strong, BattleActionType::Counter,
                TurnOutcome::AttackNullified, TurnOutcome::CounterSucceeded,
                0, $counter,
            ],
            'カウンター vs 弱 (カウンター失敗)' => [
                BattleActionType::Counter, BattleActionType::Weak,
                TurnOutcome::CounterFailed, TurnOutcome::Attacked,
                0, $weak,
            ],
            'カウンター vs 強 (カウンター成功)' => [
                BattleActionType::Counter, BattleActionType::Strong,
                TurnOutcome::CounterSucceeded, TurnOutcome::AttackNullified,
                $counter, 0,
            ],
            'カウンター vs カウンター (相殺)' => [
                BattleActionType::Counter, BattleActionType::Counter,
                TurnOutcome::CounterNullified, TurnOutcome::CounterNullified,
                0, 0,
            ],
        ];
    }

    #[Test]
    public function 解決結果は左右対称である(): void
    {
        $calculator = $this->makeCalculator();

        foreach (BattleActionType::cases() as $p) {
            foreach (BattleActionType::cases() as $e) {
                $forward = $calculator->resolve(
                    player: $p,
                    enemy: $e,
                    playerAtk: self::ATK,
                    playerDef: self::DEF,
                    enemyAtk: self::ATK,
                    enemyDef: self::DEF,
                );
                $reverse = $calculator->resolve(
                    player: $e,
                    enemy: $p,
                    playerAtk: self::ATK,
                    playerDef: self::DEF,
                    enemyAtk: self::ATK,
                    enemyDef: self::DEF,
                );

                $this->assertSame(
                    $forward->playerOutcome,
                    $reverse->enemyOutcome,
                    "symmetry outcome: {$p->value} vs {$e->value}",
                );
                $this->assertSame(
                    $forward->enemyOutcome,
                    $reverse->playerOutcome,
                    "symmetry outcome rev: {$p->value} vs {$e->value}",
                );
                $this->assertSame(
                    $forward->playerDamageToEnemy,
                    $reverse->enemyDamageToPlayer,
                    "symmetry damage: {$p->value} vs {$e->value}",
                );
                $this->assertSame(
                    $forward->enemyDamageToPlayer,
                    $reverse->playerDamageToEnemy,
                    "symmetry damage rev: {$p->value} vs {$e->value}",
                );
            }
        }
    }

    #[Test]
    public function DEFが高いと下限ダメージまで軽減される(): void
    {
        // atk=4, def=10: 弱 = max(1, 4 - 10) = 1
        $calculator = $this->makeCalculator();

        $result = $calculator->resolve(
            player: BattleActionType::Weak,
            enemy: BattleActionType::Weak,
            playerAtk: 4,
            playerDef: 10,
            enemyAtk: 4,
            enemyDef: 10,
        );

        $this->assertSame(1, $result->playerDamageToEnemy);
        $this->assertSame(1, $result->enemyDamageToPlayer);
    }

    #[Test]
    public function 攻撃側のATKに応じてダメージが増える(): void
    {
        $calculator = $this->makeCalculator();

        // playerAtk=20, enemyDef=5 での強攻撃 = floor(20*2) - 5 = 35
        $result = $calculator->resolve(
            player: BattleActionType::Strong,
            enemy: BattleActionType::Weak,
            playerAtk: 20,
            playerDef: 0,
            enemyAtk: 2,
            enemyDef: 5,
        );

        $this->assertSame(35, $result->playerDamageToEnemy);
        // enemyAtk=2, playerDef=0 での弱攻撃 = 2 - 0 = 2
        $this->assertSame(2, $result->enemyDamageToPlayer);
    }
}
