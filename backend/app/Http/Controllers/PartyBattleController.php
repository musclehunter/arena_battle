<?php

namespace App\Http\Controllers;

use App\Actions\PartyBattle\RunPartyBattleRoundAction;
use App\Actions\PartyBattle\StartPartyBattleAction;
use App\Models\Party;
use App\Models\PartyBattle;
use App\Services\Battle\AtbBattleState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PartyBattleController extends Controller
{
    public function select(Request $request): Response
    {
        $house = $request->user()->house;
        $party = Party::firstOrCreate(['house_id' => $house->id]);
        $party->load('members.character.preset');

        return Inertia::render('PartyBattle/Select', [
            'party' => [
                'id' => $party->id,
                'name' => $party->name,
                'strategy' => $party->strategy,
                'risk' => $party->risk,
                'members' => $party->members->map(fn ($m) => [
                    'id' => $m->character_id,
                    'slot' => $m->slot,
                    'name' => $m->character?->name,
                    'level' => $m->character?->level,
                    'preset' => [
                        'name' => $m->character?->preset?->name,
                        'icon_key' => $m->character?->preset?->icon_key,
                    ],
                    'icon_index' => $m->character?->icon_index,
                    'gender' => $m->character?->gender ? strtolower($m->character->gender->name) : 'unknown',
                ])->values(),
            ],
        ]);
    }

    public function store(Request $request, StartPartyBattleAction $action, AtbBattleState $state): RedirectResponse
    {
        $party = Party::firstOrCreate(['house_id' => $request->user()->house->id]);
        $validated = $request->validate([
            'risk' => ['nullable', 'in:safe,normal,high'],
        ]);
        if (! empty($validated['risk'])) {
            $party->update(['risk' => $validated['risk']]);
        }
        $battle = $action->execute($party);
        $state->initialize($battle);
        return redirect()->route('party-battles.show', $battle);
    }

    public function show(Request $request, PartyBattle $partyBattle, AtbBattleState $state): Response
    {
        $this->authorize('view', $partyBattle);
        return Inertia::render('PartyBattle/Show', ['battle' => array_merge($this->view($partyBattle), $state->get($partyBattle))]);
    }

    public function state(Request $request, PartyBattle $partyBattle, AtbBattleState $state): JsonResponse
    {
        $this->authorize('update', $partyBattle);
        return response()->json(array_merge($this->view($partyBattle), $state->get($partyBattle)));
    }

    public function reserve(Request $request, PartyBattle $partyBattle, AtbBattleState $state): JsonResponse
    {
        $this->authorize('update', $partyBattle);
        $data = $request->validate(['character_id' => ['required', 'integer'], 'skill' => ['required', 'string']]);
        try {
            return response()->json($state->reserve($partyBattle, $data['character_id'], $data['skill']));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function auto(Request $request, PartyBattle $partyBattle, AtbBattleState $state): JsonResponse
    {
        $this->authorize('update', $partyBattle);
        return response()->json($state->setAuto($partyBattle, $request->boolean('enabled')));
    }

    public function result(Request $request, PartyBattle $partyBattle, AtbBattleState $state): Response
    {
        $this->authorize('view', $partyBattle);

        if ($partyBattle->status !== 'finished') {
            return redirect()->route('party-battles.show', $partyBattle);
        }

        $stateData = $state->get($partyBattle);
        $goldGainedByCharacter = collect($stateData['level_ups'] ?? [])->pluck('gold_gained', 'character_id')->all();
        $players = array_map(fn ($player) => [...$player, 'gold_gained' => (int) ($player['gold_gained'] ?? $goldGainedByCharacter[$player['character_id']] ?? 0)], $partyBattle->player_state);
        $characterReward = array_sum(array_column($players, 'gold_gained'));
        $rewardGold = array_key_exists('reward_gold_to_house', $stateData)
            ? (int) $stateData['reward_gold']
            : ($partyBattle->winner === 'player' ? (int) config('arena.reward.win_total', 200) : 0);
        $houseReward = (int) ($stateData['reward_gold_to_house'] ?? max(0, $rewardGold - $characterReward));

        return Inertia::render('PartyBattle/Result', [
            'battle' => [
                'id' => $partyBattle->id,
                'winner' => $partyBattle->winner,
                'reward_gold' => $rewardGold,
                'reward_gold_to_characters' => $characterReward,
                'reward_gold_to_house' => $houseReward,
                'strategy' => $partyBattle->strategy,
                'risk' => $partyBattle->risk,
                'players' => $players,
                'enemies' => $partyBattle->enemy_state,
            ],
        ]);
    }

    private function view(PartyBattle $battle): array
    {
        return ['id' => $battle->id, 'status' => $battle->status, 'winner' => $battle->winner, 'round' => $battle->round, 'strategy' => $battle->strategy, 'risk' => $battle->risk, 'players' => $battle->player_state, 'enemies' => $battle->enemy_state, 'logs' => $battle->logs, 'reward_gold' => $battle->reward_gold];
    }
}
