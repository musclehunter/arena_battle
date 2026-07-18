<?php

namespace App\Exceptions\Arena;

/**
 * 1ユーザー/1ゲストセッションにつき同時進行バトルは1件までのルール違反。
 */
class ConcurrentBattleException extends ArenaDomainException
{
    public int $existingBattleId;

    public static function forBattle(int $existingBattleId): self
    {
        $e = new self('すでに進行中の戦闘があります。先に決着させてください。');
        $e->existingBattleId = $existingBattleId;

        return $e;
    }
}
