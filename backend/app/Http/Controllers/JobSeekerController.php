<?php

namespace App\Http\Controllers;

use App\Services\Arena\GuestContext;
use App\Services\Arena\HiringService;
use App\Services\Arena\JobSeekerBoard;
use App\Services\Character\CharacterStats;
use App\Services\Character\LevelUpService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobSeekerController extends Controller
{
    /**
     * 求職者 3 名を表示。
     * - 家門プレイヤー: 家門雇用ボタン + ゲスト雇用ボタン(両方表示)
     * - ゲスト: ゲスト雇用ボタンのみ
     */
    public function index(
        Request $request,
        JobSeekerBoard $board,
        GuestContext $guest,
        HiringService $hiring,
    ): Response {
        $user = $request->user();
        $house = $user?->house;
        $seekers = $board->visible();

        return Inertia::render('JobSeekers/Index', [
            'seekers' => $seekers->map(function ($c) use ($hiring) {
                $derived = CharacterStats::forEntity($c);

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'level' => $c->level,
                    'exp' => $c->exp,
                    'next_exp' => LevelUpService::requiredExpToNext($c),
                    'hire_cost' => $c->hire_cost,
                    'guest_hire_cost' => $hiring->guestHireCost($c),
                    'reward_share_bp' => $c->reward_share_bp,
                    'gold' => $c->gold,
                    'stats' => [
                        'str' => $c->str,
                        'vit' => $c->vit,
                        'dex' => $c->dex,
                        'int_stat' => $c->int_stat,
                        'hp_max' => $derived['hp'],
                        'atk' => $derived['atk'],
                        'def' => $derived['def'],
                    ],
                    'preset' => [
                        'name' => $c->preset->name,
                        'icon_key' => $c->preset->icon_key,
                    ],
                    'icon_index' => $c->icon_index,
                    'gender' => $c->gender ? strtolower($c->gender->name) : 'unknown',
                ];
            })->values(),
            'viewer' => [
                'is_authenticated' => $user !== null,
                'has_house' => $house !== null,
                'house' => $house === null ? null : [
                    'gold' => $house->gold,
                    'hire_slots' => $house->hireSlots(),
                    'hired_count' => $house->characters()->count(),
                ],
                'guest' => $house !== null ? null : [
                    'gold' => $guest->gold(),
                    'hired_character_id' => $guest->hiredCharacterId(),
                ],
            ],
        ]);
    }
}
