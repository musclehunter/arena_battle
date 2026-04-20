<?php

namespace App\Services\Battle;

use App\Enums\BattleActionType;

/**
 * v1 のターン解決ロジックの中核 (v1.2: ATK/DEF 対応)。
 *
 * ルール(設計書 9.4 準拠):
 *  - 弱攻撃は常に通る(カウンターされない)
 *  - 強攻撃は通常は通るが、相手がカウンターのときは不発
 *  - カウンターは相手が強攻撃なら成功(カウンターダメージ)
 *  - カウンターは相手が弱攻撃なら失敗し、相手の弱攻撃を受ける
 *  - カウンター同士は双方不発
 *
 * ダメージ式:
 *  damage = max(min_damage, floor(attacker.ATK * multiplier) - defender.DEF)
 */
final class DamageCalculator
{
    public function __construct(
        private readonly float $weakMultiplier,
        private readonly float $strongMultiplier,
        private readonly float $counterMultiplier,
        private readonly int $minDamage,
    ) {
    }

    /**
     * config('arena.damage.*') から係数を読み取るファクトリ。
     */
    public static function fromConfig(): self
    {
        return new self(
            weakMultiplier: (float) config('arena.damage.weak_multiplier', 1.0),
            strongMultiplier: (float) config('arena.damage.strong_multiplier', 2.0),
            counterMultiplier: (float) config('arena.damage.counter_multiplier', 1.5),
            minDamage: (int) config('arena.damage.min_damage', 1),
        );
    }

    public function resolve(
        BattleActionType $player,
        BattleActionType $enemy,
        int $playerAtk,
        int $playerDef,
        int $enemyAtk,
        int $enemyDef,
    ): TurnResolution {
        return new TurnResolution(
            playerOutcome: $this->outcomeFor($player, $enemy),
            enemyOutcome: $this->outcomeFor($enemy, $player),
            playerDamageToEnemy: $this->damageDealtBy($player, $enemy, $playerAtk, $enemyDef),
            enemyDamageToPlayer: $this->damageDealtBy($enemy, $player, $enemyAtk, $playerDef),
        );
    }

    private function outcomeFor(BattleActionType $self, BattleActionType $opponent): TurnOutcome
    {
        return match ($self) {
            BattleActionType::Weak => TurnOutcome::Attacked,
            BattleActionType::Strong => $opponent === BattleActionType::Counter
                ? TurnOutcome::AttackNullified
                : TurnOutcome::Attacked,
            BattleActionType::Counter => match ($opponent) {
                BattleActionType::Weak => TurnOutcome::CounterFailed,
                BattleActionType::Strong => TurnOutcome::CounterSucceeded,
                BattleActionType::Counter => TurnOutcome::CounterNullified,
            },
        };
    }

    private function damageDealtBy(
        BattleActionType $self,
        BattleActionType $opponent,
        int $attackerAtk,
        int $defenderDef,
    ): int {
        return match ($self) {
            BattleActionType::Weak => $this->compute($attackerAtk, $defenderDef, $this->weakMultiplier),
            BattleActionType::Strong => $opponent === BattleActionType::Counter
                ? 0
                : $this->compute($attackerAtk, $defenderDef, $this->strongMultiplier),
            BattleActionType::Counter => $opponent === BattleActionType::Strong
                ? $this->compute($attackerAtk, $defenderDef, $this->counterMultiplier)
                : 0,
        };
    }

    private function compute(int $atk, int $def, float $multiplier): int
    {
        return max($this->minDamage, (int) floor($atk * $multiplier) - $def);
    }
}
