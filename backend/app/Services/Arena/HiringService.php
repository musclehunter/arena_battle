<?php

namespace App\Services\Arena;

use App\Exceptions\Arena\CharacterNotHireableException;
use App\Exceptions\Arena\HireSlotFullException;
use App\Exceptions\Arena\InsufficientGoldException;
use App\Models\Battle;
use App\Models\Character;
use App\Models\House;
use Illuminate\Support\Facades\DB;

/**
 * 雇用 / 解雇のドメインロジック。
 */
class HiringService
{
    /**
     * 家門が求職者を正規雇用する。
     *
     * @throws InsufficientGoldException
     * @throws HireSlotFullException
     * @throws CharacterNotHireableException
     */
    public function hireByHouse(House $house, Character $character): Character
    {
        return DB::transaction(function () use ($house, $character) {
            $house->refresh()->lockForUpdate();
            $character->refresh()->lockForUpdate();

            if (! $character->isAvailable()) {
                throw CharacterNotHireableException::alreadyEmployed();
            }

            if ($house->gold < $character->hire_cost) {
                throw new InsufficientGoldException((int) $character->hire_cost, (int) $house->gold);
            }

            $slots = $house->hireSlots();
            $employed = $house->characters()->count();
            if ($employed >= $slots) {
                throw new HireSlotFullException($slots);
            }

            $house->decrement('gold', $character->hire_cost);
            $character->house_id = $house->id;
            $character->hired_at = now();
            $character->save();

            return $character;
        });
    }

    /**
     * ゲスト雇用。ゲスト家門 (= config('arena.guest_house_id')) に一時所属させる。
     *
     * @throws InsufficientGoldException
     * @throws CharacterNotHireableException
     */
    public function hireAsGuest(GuestContext $guest, Character $character): Character
    {
        if ($guest->hiredCharacterId() !== null) {
            throw CharacterNotHireableException::guestAlreadyHiring();
        }

        $cost = $this->guestHireCost($character);

        return DB::transaction(function () use ($guest, $character, $cost) {
            $character->refresh()->lockForUpdate();

            if (! $character->isAvailable()) {
                throw CharacterNotHireableException::alreadyEmployed();
            }

            if ($guest->gold() < $cost) {
                throw new InsufficientGoldException($cost, $guest->gold());
            }

            $guest->spendGold($cost);
            $character->house_id = (int) config('arena.guest_house_id');
            $character->hired_at = now();
            $character->save();

            $guest->setHiredCharacter((int) $character->id);

            return $character;
        });
    }

    /**
     * 家門からキャラを解雇する(求職者プールに戻す)。
     * キャラの gold は保持される(スコープ §4.2)。
     *
     * @throws CharacterNotHireableException
     */
    public function release(House $house, Character $character): Character
    {
        if ((int) $character->house_id !== (int) $house->id) {
            throw CharacterNotHireableException::notOwnedByHouse();
        }

        $character->house_id = null;
        $character->hired_at = null;
        $character->save();

        return $character;
    }

    /**
     * ゲスト雇用されたキャラをバトル完了後に自動解雇する。
     *
     * 冪等: 既に求職者に戻っていても例外を投げずに no-op。
     * また、セッション側の hired_character_id もクリアしたい場合は
     * 呼び出し元が GuestContext::setHiredCharacter(null) を行うこと。
     * (このメソッド自体はセッションに依存しないように設計)
     */
    public function autoReleaseAfterGuestBattle(Battle $battle): void
    {
        $character = $battle->playerCharacter;
        if ($character === null) {
            return;
        }

        $guestHouseId = (int) config('arena.guest_house_id');
        if ((int) $character->house_id !== $guestHouseId) {
            return;
        }

        $character->house_id = null;
        $character->hired_at = null;
        $character->save();
    }

    /**
     * ゲスト雇用時の契約金(割増後、切り上げ整数)。
     */
    public function guestHireCost(Character $character): int
    {
        $multiplier = (float) config('arena.guest_hire_multiplier', 1.5);

        return (int) ceil($character->hire_cost * $multiplier);
    }
}
