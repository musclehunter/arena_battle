<?php

namespace App\Http\Controllers;

use App\Actions\Battle\ResolveTurnAction;
use App\Actions\Battle\RestartBattleAction;
use App\Actions\Battle\StartBattleAction;
use App\Enums\BattleStatus;
use App\Http\Requests\Battle\StartBattleRequest;
use App\Http\Requests\Battle\SubmitBattleActionRequest;
use App\Http\Resources\BattleViewModel;
use App\Models\Battle;
use App\Models\Character;
use App\Services\Battle\BattleContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BattleController extends Controller
{
    /**
     * 家門プレイヤーが自家門のキャラでバトル開始。
     */
    public function store(StartBattleRequest $request, StartBattleAction $action): RedirectResponse
    {
        $user = $request->user();
        $house = $user->house;

        /** @var Character $character */
        $character = Character::findOrFail($request->characterId());
        if ((int) $character->house_id !== (int) $house->id) {
            throw ValidationException::withMessages([
                'character_id' => 'このキャラクターは自家門に所属していません。',
            ]);
        }

        $battle = $action->execute(
            character: $character,
            context: BattleContext::forHouse($house, $user),
        );

        return redirect()->route('battles.show', $battle);
    }

    /**
     * バトル画面表示。
     */
    public function show(Request $request, Battle $battle): Response
    {
        $this->authorize('view', $battle);

        return Inertia::render('Battle/Show', [
            'battle' => BattleViewModel::from($battle),
        ]);
    }

    /**
     * 1ターン解決。
     */
    public function resolveTurn(
        SubmitBattleActionRequest $request,
        Battle $battle,
        ResolveTurnAction $action,
    ): RedirectResponse {
        $this->authorize('update', $battle);

        if ($battle->status !== BattleStatus::InProgress) {
            throw ValidationException::withMessages([
                'status' => 'この対戦は既に終了しています。',
            ]);
        }

        if ($battle->action_token === null || ! hash_equals($battle->action_token, $request->token())) {
            throw ValidationException::withMessages([
                'token' => '行動トークンが無効です。画面を再読み込みしてください。',
            ]);
        }

        $action->execute($battle, $request->playerAction());

        return redirect()->route('battles.show', $battle);
    }

    /**
     * 再戦。元バトルの player_character と enemy_preset を引き継ぐ。
     */
    public function restart(
        Request $request,
        Battle $battle,
        RestartBattleAction $action,
    ): RedirectResponse {
        $this->authorize('update', $battle);

        // ゲストバトルの再戦はゲスト雇用からやり直す必要があるため拒否。
        // (キャラは自動解雇で求職者に戻っている)
        if ($battle->isGuestBattle()) {
            throw ValidationException::withMessages([
                'restart' => 'ゲスト雇用のバトルは再戦できません。求職者から再度雇用してください。',
            ]);
        }

        // 家門バトル: 同じキャラがまだ自家門に所属していれば再戦可
        $character = $battle->playerCharacter;
        if ($character === null
            || (int) $character->house_id !== (int) $battle->house_id) {
            throw ValidationException::withMessages([
                'restart' => 'このキャラクターは現在自家門に所属していません。',
            ]);
        }

        $newBattle = $action->execute($battle);

        return redirect()->route('battles.show', $newBattle);
    }
}
