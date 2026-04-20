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
        $e = new self('既に進行中のバトルがあります。先に終了させてください。');
        $e->existingBattleId = $existingBattleId;

        return $e;
    }
}
