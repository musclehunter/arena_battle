<?php

namespace App\Policies;

use App\Models\Character;
use App\Models\User;

/**
 * キャラクターへの操作認可。
 *
 * - release: キャラの house_id が自分の家門と一致するときのみ許可
 *   (ゲスト雇用中のキャラは BattleController 側で自動解雇されるのでここでは対象外)
 */
class CharacterPolicy
{
    public function release(User $user, Character $character): bool
    {
        $house = $user->house;
        if ($house === null) {
            return false;
        }

        return (int) $character->house_id === (int) $house->id;
    }

    public function hireByHouse(User $user, Character $character): bool
    {
        $house = $user->house;
        if ($house === null) {
            return false;
        }

        return $character->isAvailable();
    }
}
