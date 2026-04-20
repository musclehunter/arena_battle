<?php

namespace Tests\Unit\Services\Battle;

use App\Enums\BattleActionType;
use App\Enums\BattleWinner;
use App\Services\Battle\BattleLogFactory;
use App\Services\Battle\DamageCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 設計書 10章「ログ表示例」が再現できることを固定化するテスト。
 */
final class BattleLogFactoryTest extends TestCase
{
    private BattleLogFactory $factory;
    private DamageCalculator $calculator;

    // atk=4, def=0 + デフォルト係数 で 弱=4 / 強=8 / カウンター=6 になるよう調整
    private const ATK = 4;
    private const DEF = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new BattleLogFactory();
        $this->calculator = new DamageCalculator(
            weakMultiplier: 1.0,
            strongMultiplier: 2.0,
            counterMultiplier: 1.5,
            minDamage: 1,
        );
    }

    private function resolve(BattleActionType $player, BattleActionType $enemy)
    {
        return $this->calculator->resolve(
            player: $player,
            enemy: $enemy,
            playerAtk: self::ATK,
            playerDef: self::DEF,
            enemyAtk: self::ATK,
            enemyDef: self::DEF,
        );
    }

    #[Test]
    public function 弱攻撃がカウンター失敗を誘発する場面のログ文言(): void
    {
        $resolution = $this->resolve(BattleActionType::Weak, BattleActionType::Counter);

        $summary = $this->factory->buildSummary(
            turnNumber: 1,
            playerAction: BattleActionType::Weak,
            enemyAction: BattleActionType::Counter,
            resolution: $resolution,
            playerHpAfter: 30,
            enemyHpAfter: 26,
            winner: null,
        );

        $this->assertSame(
            "1ターン目: あなたは弱攻撃した。\n"
            . "1ターン目: 敵はカウンターした。\n"
            . "1ターン目: 敵のカウンターは失敗した。\n"
            . "1ターン目: 敵に4ダメージ。\n"
            . "現在HP: あなた 30 / 敵 26",
            $summary,
        );
    }

    #[Test]
    public function 強攻撃と弱攻撃が相打ちになる場面のログ文言(): void
    {
        $resolution = $this->resolve(BattleActionType::Strong, BattleActionType::Weak);

        $summary = $this->factory->buildSummary(
            turnNumber: 2,
            playerAction: BattleActionType::Strong,
            enemyAction: BattleActionType::Weak,
            resolution: $resolution,
            playerHpAfter: 26,
            enemyHpAfter: 18,
            winner: null,
        );

        $this->assertSame(
            "2ターン目: あなたは強攻撃した。\n"
            . "2ターン目: 敵は弱攻撃した。\n"
            . "2ターン目: 敵に8ダメージ。\n"
            . "2ターン目: あなたは4ダメージを受けた。\n"
            . "現在HP: あなた 26 / 敵 18",
            $summary,
        );
    }

    #[Test]
    public function 決着時のログに勝利メッセージが付与される(): void
    {
        $resolution = $this->resolve(BattleActionType::Weak, BattleActionType::Weak);

        $summary = $this->factory->buildSummary(
            turnNumber: 6,
            playerAction: BattleActionType::Weak,
            enemyAction: BattleActionType::Weak,
            resolution: $resolution,
            playerHpAfter: 2,
            enemyHpAfter: 0,
            winner: BattleWinner::Player,
        );

        $this->assertStringContainsString('敵に4ダメージ。', $summary);
        $this->assertStringContainsString('敵を倒した。あなたの勝利。', $summary);
        $this->assertStringContainsString('現在HP: あなた 2 / 敵 0', $summary);
    }
}
