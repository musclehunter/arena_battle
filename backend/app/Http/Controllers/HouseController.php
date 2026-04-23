<?php

namespace App\Http\Controllers;

use App\Actions\House\CreateHouseAction;
use App\Enums\BattleStatus;
use App\Http\Requests\House\CreateHouseRequest;
use App\Models\Battle;
use App\Services\Character\CharacterStats;
use App\Services\Character\LevelUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HouseController extends Controller
{
    /**
     * 家門作成画面。
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->house !== null) {
            return redirect()->route('houses.mine');
        }

        return Inertia::render('House/Create');
    }

    public function store(CreateHouseRequest $request, CreateHouseAction $action): RedirectResponse
    {
        $action->execute($request->user(), $request->name());

        return redirect()->route('houses.mine');
    }

    /**
     * 自家門ダッシュボード。
     * 雇用キャラ一覧、gold、Lv、進行中バトルリンクを返す。
     */
    public function mine(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $house = $user->house;

        if ($house === null) {
            return redirect()->route('houses.create');
        }

        $house->load(['characters.preset']);

        $activeBattle = Battle::query()
            ->where('status', BattleStatus::InProgress->value)
            ->where('house_id', $house->id)
            ->first();

        return Inertia::render('House/Mine', [
            'house' => [
                'id' => $house->id,
                'name' => $house->name,
                'level' => $house->level,
                'gold' => $house->gold,
                'hire_slots' => $house->hireSlots(),
                'hired_count' => $house->characters->count(),
            ],
            'characters' => $house->characters->map(function ($c) {
                $derived = CharacterStats::forEntity($c);

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'level' => $c->level,
                    'exp' => $c->exp,
                    'next_exp' => LevelUpService::requiredExpToNext($c),
                    'gold' => $c->gold,
                    'reward_share_bp' => $c->reward_share_bp,
                    'stats' => [
                        'str' => $c->str,
                        'vit' => $c->vit,
                        'dex' => $c->dex,
                        'int_stat' => $c->int_stat,
                        'hp_max' => $derived['hp'],
                        'atk' => $derived['atk'],
                        'def' => $derived['def'],
                    ],
                    'growth_preset_key' => $c->growth_preset_key,
                    'growth_index' => $c->growth_index,
                    'preset' => [
                        'key' => $c->preset->key,
                        'name' => $c->preset->name,
                        'icon_key' => $c->preset->icon_key,
                    ],
                    'icon_index' => $c->icon_index,
                    'gender' => $c->gender ? strtolower($c->gender->name) : 'unknown',
                ];
            })->values(),
            'active_battle_id' => $activeBattle?->id,
        ]);
    }
}
