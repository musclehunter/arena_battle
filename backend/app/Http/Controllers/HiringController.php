<?php

namespace App\Http\Controllers;

use App\Exceptions\Arena\ArenaDomainException;
use App\Http\Requests\House\HireCharacterRequest;
use App\Models\Character;
use App\Services\Arena\HiringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 家門雇用 / 解雇。
 */
class HiringController extends Controller
{
    /**
     * 家門がキャラを雇用する。
     */
    public function store(HireCharacterRequest $request, HiringService $hiring): RedirectResponse
    {
        $user = $request->user();
        $house = $user->house;

        $character = Character::findOrFail($request->characterId());
        $this->authorize('hireByHouse', $character);

        try {
            $hiring->hireByHouse($house, $character);
        } catch (ArenaDomainException $e) {
            throw ValidationException::withMessages(['hire' => $e->getMessage()]);
        }

        return redirect()->route('houses.mine');
    }

    /**
     * 家門から解雇(求職者プールへ戻す)。
     */
    public function destroy(Request $request, Character $character, HiringService $hiring): RedirectResponse
    {
        $this->authorize('release', $character);

        $user = $request->user();
        $house = $user->house;

        try {
            $hiring->release($house, $character);
        } catch (ArenaDomainException $e) {
            throw ValidationException::withMessages(['release' => $e->getMessage()]);
        }

        return redirect()->route('houses.mine');
    }
}
