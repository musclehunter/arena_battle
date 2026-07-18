<?php

namespace App\Http\Controllers;

use App\Http\Requests\Party\UpdatePartyRequest;
use App\Models\Party;
use App\Services\Character\CharacterStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public function edit(Request $request): Response
    {
        $house = $request->user()->house;
        $party = Party::firstOrCreate(['house_id' => $house->id]);
        $party->load('members.character.preset');
        $characters = $house->characters()->with('preset')->orderBy('name')->get();

        return Inertia::render('Party/Edit', [
            'party' => [
                'name' => $party->name,
                'strategy' => $party->strategy,
                'risk' => $party->risk,
                'character_ids' => $party->members->pluck('character_id')->values(),
            ],
            'characters' => $characters->map(fn ($character) => [
                'id' => $character->id,
                'name' => $character->name,
                'level' => $character->level,
                'preset' => ['name' => $character->preset->name, 'icon_key' => $character->preset->icon_key],
                'icon_index' => $character->icon_index,
                'gender' => $character->gender ? strtolower($character->gender->name) : 'unknown',
                'stats' => CharacterStats::forEntity($character),
            ])->values(),
        ]);
    }

    public function update(UpdatePartyRequest $request): RedirectResponse
    {
        $house = $request->user()->house;
        $characterIds = $request->validated('character_ids');
        $ownedCount = $house->characters()->whereIn('id', $characterIds)->count();

        if ($ownedCount !== count($characterIds)) {
            throw ValidationException::withMessages(['character_ids' => '家門に所属する者だけを遠征隊へ編成できます。']);
        }

        DB::transaction(function () use ($house, $request, $characterIds) {
            $party = Party::firstOrCreate(['house_id' => $house->id]);
            $party->update($request->safe()->only(['name', 'strategy', 'risk']));
            $party->members()->delete();
            foreach ($characterIds as $slot => $characterId) {
                $party->members()->create(['character_id' => $characterId, 'slot' => $slot]);
            }
        });

        return redirect()->route('parties.edit');
    }
}
