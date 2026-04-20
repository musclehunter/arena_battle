<?php

namespace App\Actions\Battle;

use App\Models\Battle;
use App\Models\Character;
use App\Services\Arena\GuestContext;
use App\Services\Arena\HiringService;
use App\Services\Battle\BattleContext;
use Illuminate\Support\Facades\DB;

/**
 * ゲスト雇用 → 即時バトル開始 の複合アクション。
 *
 * - ゲスト gold から割増契約金を引き、キャラをゲスト家門に一時所属
 * - 同時進行バトル制約は StartBattleAction 側でチェックされる
 * - トランザクション境界: HiringService 側と StartBattleAction 側がそれぞれ
 *   DB::transaction を張るので、ここでは最外をラップして一貫性を取る。
 */
final class GuestHireAndStartBattleAction
{
    public function __construct(
        private readonly HiringService $hiringService,
        private readonly StartBattleAction $startBattleAction,
        private readonly GuestContext $guestContext,
    ) {
    }

    public function execute(Character $character): Battle
    {
        return DB::transaction(function () use ($character): Battle {
            $this->hiringService->hireAsGuest($this->guestContext, $character);
            $character->refresh();

            return $this->startBattleAction->execute(
                character: $character,
                context: BattleContext::forGuest($this->guestContext->sessionId()),
            );
        });
    }
}
