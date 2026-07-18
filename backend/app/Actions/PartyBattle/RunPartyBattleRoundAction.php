<?php

namespace App\Actions\PartyBattle;

use App\Models\Character;
use App\Models\PartyBattle;
use App\Services\Character\LevelUpService;
use Illuminate\Support\Facades\DB;

final class RunPartyBattleRoundAction
{
    public function execute(PartyBattle $battle): PartyBattle
    {
        if ($battle->status !== 'in_progress') {
            return $battle;
        }

        return DB::transaction(function () use ($battle): PartyBattle {
            $battle->refresh();
            if ($battle->status !== 'in_progress') {
                return $battle;
            }

            $players = $battle->player_state;
            $enemies = $battle->enemy_state;
            $events = [];
            $strategyMultiplier = match ($battle->strategy) {
                'aggressive' => 1.2,
                'defensive' => 0.85,
                default => 1.0,
            };

            $turnOrder = collect($players)->keys()->map(fn (int $index) => ['side' => 'players', 'index' => $index, 'speed' => $players[$index]['speed'] ?? 10])
                ->merge(collect($enemies)->keys()->map(fn (int $index) => ['side' => 'enemies', 'index' => $index, 'speed' => $enemies[$index]['speed'] ?? 10]))
                ->sortByDesc('speed')
                ->values();

            foreach ($turnOrder as $turn) {
                $isPlayer = $turn['side'] === 'players';
                $attackers = $isPlayer ? $players : $enemies;
                $defenders = $isPlayer ? $enemies : $players;
                $attacker = $attackers[$turn['index']];
                if ($attacker['hp'] <= 0 || ! $this->alive($defenders)) continue;

                $target = $this->target($defenders);
                $damage = $this->damage($attacker['atk'], $defenders[$target]['def'], $isPlayer ? $strategyMultiplier : 1.0);
                $defenders[$target]['hp'] = max(0, $defenders[$target]['hp'] - $damage);
                if ($isPlayer) $enemies = $defenders;
                else $players = $defenders;
                $events[] = [
                    'attacker_side' => $turn['side'],
                    'attacker_index' => $turn['index'],
                    'target_side' => $isPlayer ? 'enemies' : 'players',
                    'target_index' => $target,
                    'damage' => $damage,
                    'text' => "{$attacker['name']}が{$defenders[$target]['name']}に{$damage}のダメージ。",
                ];
            }

            $battle->round++;
            $logs = $battle->logs;
            $logs[] = ['round' => $battle->round, 'events' => $events];
            $battle->player_state = $players;
            $battle->enemy_state = $enemies;
            $battle->logs = array_slice($logs, -12);

            if (! $this->alive($players) || ! $this->alive($enemies) || $battle->round >= 30) {
                $playerWon = $this->alive($players) && (! $this->alive($enemies) || collect($players)->sum('hp') >= collect($enemies)->sum('hp'));
                $battle->status = 'finished';
                $battle->winner = $playerWon ? 'player' : 'enemy';
                $battle->ended_at = now();
                $battle->reward_gold = $playerWon ? $this->rewardGold($battle->risk) : 0;
                if ($playerWon) {
                    $battle->house()->increment('gold', $battle->reward_gold);
                    $exp = LevelUpService::rewardExpFromEnemyLevel((int) round(collect($enemies)->avg('level')));
                    Character::query()->whereIn('id', collect($players)->pluck('character_id'))->get()->each(fn (Character $character) => LevelUpService::grantExp($character, $exp));
                }
            }

            $battle->save();

            return $battle;
        });
    }

    private function alive(array $team): bool
    {
        return collect($team)->contains(fn (array $member) => $member['hp'] > 0);
    }

    private function target(array $team): int
    {
        return collect($team)->filter(fn (array $member) => $member['hp'] > 0)->sortBy('hp')->keys()->first();
    }

    private function damage(int $attack, int $defense, float $multiplier = 1.0): int
    {
        return max(1, (int) floor($attack * $multiplier) - $defense);
    }

    private function rewardGold(string $risk): int
    {
        $multiplier = match ($risk) { 'safe' => 0.75, 'high' => 1.5, default => 1.0 };

        return (int) floor((int) config('arena.party.reward_gold') * $multiplier);
    }
}
