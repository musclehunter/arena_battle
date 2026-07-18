<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Services\Character\CharacterStats;
use App\Services\Character\LevelUpService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CharacterController extends Controller
{
    /**
     * 家門の契約者一覧。
     */
    public function index(Request $request): Response
    {
        $house = $request->user()->house;
        $characters = $house->characters()->with('preset', 'skills')->orderBy('name')->get();

        return Inertia::render('Character/Index', [
            'characters' => $characters->map(function ($c) {
                $derived = CharacterStats::forEntity($c);

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'level' => $c->level,
                    'exp' => $c->exp,
                    'next_exp' => LevelUpService::requiredExpToNext($c),
                    'gold' => $c->gold,
                    'reward_share_bp' => $c->reward_share_bp,
                    'skill_points' => $c->skill_points,
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
                        'key' => $c->preset->key,
                        'name' => $c->preset->name,
                        'icon_key' => $c->preset->icon_key,
                    ],
                    'icon_index' => $c->icon_index,
                    'gender' => $c->gender ? strtolower($c->gender->name) : 'unknown',
                    'learned_skills' => $c->skills->map(fn ($s) => [
                        'id' => $s->id,
                        'key' => $s->key,
                        'name' => $s->name,
                        'job' => $s->job,
                        'unlock_level' => $s->unlock_level,
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    /**
     * 契約者詳細。
     */
    public function show(Request $request, Character $character): Response
    {
        $house = $request->user()->house;

        if ($character->house_id !== $house->id) {
            abort(403);
        }

        $character->load('preset', 'skills');
        $derived = CharacterStats::forEntity($character);

        $battles = $character->battleLogs?->take(10) ?? collect();

        return Inertia::render('Character/Show', [
            'character' => [
                'id' => $character->id,
                'name' => $character->name,
                'level' => $character->level,
                'exp' => $character->exp,
                'next_exp' => LevelUpService::requiredExpToNext($character),
                'gold' => $character->gold,
                'reward_share_bp' => $character->reward_share_bp,
                'skill_points' => $character->skill_points,
                'gender' => $character->gender ? strtolower($character->gender->name) : 'unknown',
                'stats' => [
                    'str' => $character->str,
                    'vit' => $character->vit,
                    'dex' => $character->dex,
                    'int_stat' => $character->int_stat,
                    'hp_max' => $derived['hp'],
                    'atk' => $derived['atk'],
                    'def' => $derived['def'],
                ],
                'preset' => [
                    'key' => $character->preset->key,
                    'name' => $character->preset->name,
                    'icon_key' => $character->preset->icon_key,
                ],
                'icon_index' => $character->icon_index,
                'growth' => [
                    'preset_key' => $character->growth_preset_key,
                    'index' => $character->growth_index,
                ],
                'contract' => [
                    'hired_at' => $character->hired_at?->toISOString(),
                    'salary' => (int) round($character->reward_share_bp / 100),
                ],
                'skills' => $character->skills->map(fn ($s) => [
                    'id' => $s->id,
                    'key' => $s->key,
                    'name' => $s->name,
                    'job' => $s->job,
                    'description' => $s->description,
                    'unlock_level' => $s->unlock_level,
                    'sp_cost' => $s->sp_cost,
                    'power' => $s->power,
                    'element' => $s->element,
                    'target_type' => $s->target_type,
                ])->values(),
                'battle_history' => $battles->map(fn ($log) => [
                    'id' => $log->id,
                    'turn_number' => $log->turn_number,
                    'summary' => $log->summary_text,
                    'created_at' => $log->created_at->toISOString(),
                ])->values(),
            ],
        ]);
    }
}
