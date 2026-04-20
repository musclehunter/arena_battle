<?php

namespace App\Http\Controllers;

use App\Actions\Battle\GuestHireAndStartBattleAction;
use App\Exceptions\Arena\ArenaDomainException;
use App\Http\Requests\GuestHireRequest;
use App\Models\Character;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * ゲスト雇用 + バトル自動開始。
 * 認証の有無にかかわらず利用可能(家門プレイヤーも「1回だけ挑む」用途で使える)。
 */
class GuestHireController extends Controller
{
    public function store(
        GuestHireRequest $request,
        GuestHireAndStartBattleAction $action,
    ): RedirectResponse {
        /** @var Character $character */
        $character = Character::findOrFail($request->characterId());

        if (! $character->isAvailable()) {
            throw ValidationException::withMessages([
                'character_id' => 'このキャラクターは既に雇用されています。',
            ]);
        }

        try {
            $battle = $action->execute($character);
        } catch (ArenaDomainException $e) {
            throw ValidationException::withMessages(['hire' => $e->getMessage()]);
        }

        return redirect()->route('battles.show', $battle);
    }
}
