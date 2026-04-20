<?php

namespace App\Http\Controllers;

use App\Enums\BattleStatus;
use App\Models\Battle;
use App\Services\Arena\GuestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * トップ (/) の振り分け。
 *
 * - 認証 & 家門あり → /houses/mine
 * - それ以外(ゲスト / 認証ユーザーだが家門未作成) → ゲストランディング
 *   (所持ゴールド、現在進行中バトル、求職者ボード入口)
 */
class HomeController extends Controller
{
    public function index(Request $request, GuestContext $guest): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->house !== null) {
            return redirect()->route('houses.mine');
        }

        $activeBattle = Battle::query()
            ->where('status', BattleStatus::InProgress->value)
            ->where('guest_session_id', $guest->sessionId())
            ->first();

        return Inertia::render('Home/Guest', [
            'guest' => [
                'gold' => $guest->gold(),
                'hired_character_id' => $guest->hiredCharacterId(),
            ],
            'active_battle_id' => $activeBattle?->id,
            'is_authenticated' => $user !== null,
        ]);
    }
}
