<?php

namespace App\Services\Battle;

/**
 * 1ターンの解決結果。ダメージは「自分 → 相手」方向の値。
 */
final readonly class TurnResolution
{
    public function __construct(
        public TurnOutcome $playerOutcome,
        public TurnOutcome $enemyOutcome,
        public int $playerDamageToEnemy,
        public int $enemyDamageToPlayer,
    ) {
    }
}
