<?php

namespace App\Services\Battle;

use App\Models\Character;
use App\Models\House;
use App\Models\PartyBattle;
use App\Models\Skill;
use App\Services\Character\LevelUpService;
use Illuminate\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Closure;
use RuntimeException;

final class AtbBattleState
{
    public function key(PartyBattle $battle): string
    {
        return "arena:atb:battle:{$battle->id}";
    }

    private function synchronized(PartyBattle $battle, Closure $callback): mixed
    {
        try {
            return Cache::lock("arena:atb:battle:{$battle->id}:lock", 5)->block(1, $callback);
        } catch (LockTimeoutException) {
            throw new RuntimeException('戦闘状態を更新できません。もう一度お試しください。');
        }
    }

    public function initialize(PartyBattle $battle): array
    {
        $previous = PartyBattle::query()
            ->where('house_id', $battle->house_id)
            ->where('id', '!=', $battle->id)
            ->orderByDesc('id')
            ->first();
        $auto = $previous?->player_state[0]['auto'] ?? false;

        $state = [
            'id' => $battle->id,
            'status' => 'in_progress',
            'auto' => $auto,
            'players' => $this->members($battle->player_state, 'players'),
            'enemies' => $this->members($battle->enemy_state, 'enemies'),
            'events' => [],
            'updated_at' => now()->getTimestampMs(),
        ];
        $this->put($battle, $state);

        return $state;
    }

    public function get(PartyBattle $battle): array
    {
        $raw = Redis::get($this->key($battle));
        if ($raw === null) return $this->initialize($battle);
        $state = json_decode($raw, true);
        if (! is_array($state)) throw new RuntimeException('ATB戦闘状態を読み込めません。');

        return $state;
    }

    public function put(PartyBattle $battle, array $state): void
    {
        Redis::setex($this->key($battle), 3600, json_encode($state, JSON_THROW_ON_ERROR));
    }

    public function reserve(PartyBattle $battle, int $characterId, string $skill): array
    {
        return $this->synchronized($battle, fn () => $this->reserveState($battle, $characterId, $skill));
    }

    private function reserveState(PartyBattle $battle, int $characterId, string $skill): array
    {
        if ($battle->status !== 'in_progress') throw new RuntimeException('終了済みの戦闘では行動を選べません。');

        $state = $this->get($battle);
        $isBasic = in_array($skill, ['normal', 'guard'], true);
        if (! $isBasic && ! Skill::query()->where('key', $skill)->exists()) throw new RuntimeException('不明な行動です。');
        foreach ($state['players'] as &$member) {
            if ((int) $member['character_id'] !== $characterId) continue;
            if ($member['phase'] !== 'input') throw new RuntimeException('このキャラは現在行動を選べません。');
            if (! $isBasic) {
                $learned = $member['learned_skills'] ?? [];
                if (! in_array($skill, $learned, true)) throw new RuntimeException('このスキルは習得していません。');
            }
            $member['reserved_skill'] = $skill;
            $member['phase'] = 'casting';
            $member['gauge'] = 0;
            $member['guard'] = false;
            $this->put($battle, $state);
            return $state;
        }
        throw new RuntimeException('編成にいないキャラです。');
    }

    public function setAuto(PartyBattle $battle, bool $enabled): array
    {
        return $this->synchronized($battle, fn () => $this->setAutoState($battle, $enabled));
    }

    private function setAutoState(PartyBattle $battle, bool $enabled): array
    {
        $state = $this->get($battle);
        $state['auto'] = $enabled;
        $this->put($battle, $state);
        return $state;
    }

    public function tick(PartyBattle $battle): array
    {
        return $this->synchronized($battle, fn () => $this->tickState($battle));
    }

    private function tickState(PartyBattle $battle): array
    {
        $state = $this->get($battle);
        foreach (['players', 'enemies'] as $side) {
            foreach ($state[$side] as $index => &$member) {
                if ($member['hp'] <= 0) continue;
                $isAuto = $side === 'enemies' || $state['auto'];
                $fillRate = $this->dexFillRate((int) ($member['speed'] ?? 10));
                if ($member['phase'] === 'input' && $isAuto) {
                    $member['reserved_skill'] = 'normal';
                    $member['phase'] = 'casting';
                    $member['gauge'] = 0;
                    $member['guard'] = false;
                }
                if ($member['phase'] === 'cooldown') {
                    $member['cooldown'] = max(0, $member['cooldown'] - $fillRate);
                    if ($member['cooldown'] === 0) $member['phase'] = 'input';
                    continue;
                }
                if ($member['phase'] !== 'casting') continue;
                $skillKey = $member['reserved_skill'];
                $skill = $this->resolveSkill($skillKey);
                $member['gauge'] = min((int) config('atb.max_gauge'), $member['gauge'] + $fillRate);
                if ($member['gauge'] < $skill['cast_gauge']) continue;

                $this->executeSkill($state, $side, $index, $member, $skill, $isAuto);
            }
        }
        $state['events'] = array_slice($state['events'], -50);
        $alivePlayers = array_filter($state['players'], fn ($unit) => $unit['hp'] > 0);
        $aliveEnemies = array_filter($state['enemies'], fn ($unit) => $unit['hp'] > 0);
        if (! $alivePlayers || ! $aliveEnemies) {
            $state['status'] = 'finished';
            $state['winner'] = $alivePlayers ? 'player' : 'enemy';
            $rewardGold = $state['winner'] === 'player' ? (int) config('arena.reward.win_total', 200) : 0;
            $state['reward_gold'] = $rewardGold;
            $levelUps = $state['winner'] === 'player' ? $this->grantRewards($state) : [];
            $state['level_ups'] = $levelUps;
            $goldGainedByCharacter = collect($levelUps)->pluck('gold_gained', 'character_id')->all();
            $characterReward = array_sum($goldGainedByCharacter);
            $state['reward_gold_to_characters'] = $characterReward;
            $state['reward_gold_to_house'] = max(0, $rewardGold - $characterReward);
            $state['players'] = array_map(fn ($member) => [...$member, 'gold_gained' => (int) ($goldGainedByCharacter[$member['character_id']] ?? 0)], $state['players']);
            $battle->update(['status' => 'finished', 'winner' => $state['winner'], 'reward_gold' => $rewardGold, 'ended_at' => now(), 'player_state' => array_map(fn ($member) => [...$member, 'auto' => $state['auto']], $state['players'])]);
        }
        $state['updated_at'] = now()->getTimestampMs();
        $this->put($battle, $state);
        return $state;
    }

    private function resolveSkill(string $key): array
    {
        $basic = config('atb.skills.'.$key);
        if ($basic) return $basic;

        $skill = Skill::query()->where('key', $key)->first();
        if ($skill) return [
            'name' => $skill->name,
            'power' => $skill->power,
            'cast_gauge' => $skill->cast_gauge,
            'cooldown_gauge' => $skill->cooldown_gauge,
            'scales_with' => $skill->scales_with,
            'element' => $skill->element,
            'target_type' => $skill->target_type,
            'target_count' => $skill->target_count,
            'effect_type' => $skill->effect_type,
            'effect_power' => $skill->effect_power,
            'effect_duration' => $skill->effect_duration,
        ];

        return config('atb.skills.normal');
    }

    private function executeSkill(array &$state, string $side, int $index, array &$member, array $skill, bool $isAuto): void
    {
        $skillKey = $member['reserved_skill'];
        $targetType = $skill['target_type'] ?? 'enemy_single';
        $effectType = $skill['effect_type'] ?? null;

        // 防御
        if ($skillKey === 'guard') {
            $state['event_seq'] = ($state['event_seq'] ?? 0) + 1;
            $state['events'][] = ['id' => $state['event_seq'], 'side' => $side, 'index' => $index, 'target_side' => null, 'target_index' => null, 'skill' => 'guard', 'damage' => 0];
            $member['guard'] = true;
            $this->resetAfterSkill($member, $skill, $isAuto);
            return;
        }

        // 自己バフ（気合など）
        if ($targetType === 'self') {
            if ($effectType === 'buff_atk') {
                $member['atk_buff'] = ($member['atk_buff'] ?? 0) + (int) $skill['effect_power'];
                $member['atk_buff_turns'] = $skill['effect_duration'];
            }
            $state['event_seq'] = ($state['event_seq'] ?? 0) + 1;
            $state['events'][] = ['id' => $state['event_seq'], 'side' => $side, 'index' => $index, 'target_side' => null, 'target_index' => null, 'skill' => $skillKey, 'damage' => 0];
            $this->resetAfterSkill($member, $skill, $isAuto);
            return;
        }

        // 味方対象（回復・バフ）
        if (str_starts_with($targetType, 'ally')) {
            $allySide = $side;
            $allies = collect($state[$allySide])->filter(fn ($u) => $u['hp'] > 0)->values();
            if ($allies->isEmpty()) { $this->resetAfterSkill($member, $skill, $isAuto); return; }

            $targets = $targetType === 'ally_single'
                ? [$allies->sortBy('hp')->first()]
                : $allies->take($skill['target_count'] ?? 1)->all();

            foreach ($targets as $allyIdx => $ally) {
                $realIdx = $ally['index'];
                if ($effectType === 'heal') {
                    $healStat = $skill['scales_with'] === 'int' ? (int) ($member['int'] ?? 0) : (int) ($member['atk'] ?? 0);
                    $heal = (int) floor($healStat * $skill['power']);
                    $state[$allySide][$realIdx]['hp'] = min($state[$allySide][$realIdx]['max_hp'], $state[$allySide][$realIdx]['hp'] + $heal);
                    $state['event_seq'] = ($state['event_seq'] ?? 0) + 1;
                    $state['events'][] = ['id' => $state['event_seq'], 'side' => $side, 'index' => $index, 'target_side' => $allySide, 'target_index' => $realIdx, 'skill' => $skillKey, 'damage' => -$heal];
                } elseif ($effectType === 'buff_atk') {
                    $state[$allySide][$realIdx]['atk_buff'] = ($state[$allySide][$realIdx]['atk_buff'] ?? 0) + (int) $skill['effect_power'];
                    $state[$allySide][$realIdx]['atk_buff_turns'] = $skill['effect_duration'];
                    $state['event_seq'] = ($state['event_seq'] ?? 0) + 1;
                    $state['events'][] = ['id' => $state['event_seq'], 'side' => $side, 'index' => $index, 'target_side' => $allySide, 'target_index' => $realIdx, 'skill' => $skillKey, 'damage' => 0];
                } elseif ($effectType === 'cleanse') {
                    $state[$allySide][$realIdx]['debuffs'] = [];
                    $state['event_seq'] = ($state['event_seq'] ?? 0) + 1;
                    $state['events'][] = ['id' => $state['event_seq'], 'side' => $side, 'index' => $index, 'target_side' => $allySide, 'target_index' => $realIdx, 'skill' => $skillKey, 'damage' => 0];
                }
            }
            $this->resetAfterSkill($member, $skill, $isAuto);
            return;
        }

        // 敵対象（単体・範囲）
        $targetSide = $side === 'players' ? 'enemies' : 'players';
        $enemies = collect($state[$targetSide])->filter(fn ($u) => $u['hp'] > 0)->values();
        if ($enemies->isEmpty()) { $this->resetAfterSkill($member, $skill, $isAuto); return; }

        $targets = $targetType === 'enemy_area'
            ? $enemies->take($skill['target_count'] ?? 3)->all()
            : [$enemies->sortBy('hp')->first()];

        $scalesWith = $skill['scales_with'] ?? 'atk';
        $attackStat = $scalesWith === 'int' ? (int) ($member['int'] ?? 0) : (int) ($member['atk'] ?? 0);
        $attackStat += (int) ($member['atk_buff'] ?? 0);

        foreach ($targets as $enemy) {
            $realIdx = $enemy['index'];
            $targetDef = $state[$targetSide][$realIdx]['def'];
            if ($state[$targetSide][$realIdx]['guard']) {
                $targetDef = (int) floor($targetDef * (float) config('atb.guard_def_multiplier', 1.8));
            }
            $damage = max(1, (int) floor($attackStat * $skill['power']) - $targetDef);
            $state[$targetSide][$realIdx]['hp'] = max(0, $state[$targetSide][$realIdx]['hp'] - $damage);
            $state['event_seq'] = ($state['event_seq'] ?? 0) + 1;
            $state['events'][] = ['id' => $state['event_seq'], 'side' => $side, 'index' => $index, 'target_side' => $targetSide, 'target_index' => $realIdx, 'skill' => $skillKey, 'damage' => $damage];

            // 追加効果
            if ($effectType === 'dot_poison') {
                $state[$targetSide][$realIdx]['debuffs'][] = ['type' => 'poison', 'power' => $skill['effect_power'], 'duration' => $skill['effect_duration']];
            } elseif ($effectType === 'debuff_speed') {
                $state[$targetSide][$realIdx]['debuffs'][] = ['type' => 'slow', 'power' => $skill['effect_power'], 'duration' => $skill['effect_duration']];
            } elseif ($effectType === 'heal_self') {
                $heal = (int) floor($attackStat * $skill['effect_power'] / 100);
                $member['hp'] = min($member['max_hp'], $member['hp'] + $heal);
            }
        }

        $this->resetAfterSkill($member, $skill, $isAuto);
    }

    private function resetAfterSkill(array &$member, array $skill, bool $isAuto): void
    {
        $member['gauge'] = 0;
        $member['cooldown'] = $skill['cooldown_gauge'];
        $member['phase'] = $isAuto ? 'input' : 'cooldown';
        $member['reserved_skill'] = null;
    }

    private function grantRewards(array $state): array
    {
        $results = [];
        $enemyLevel = (int) ceil(collect($state['enemies'])->avg('level') ?: 1);
        $expPerEnemy = LevelUpService::rewardExpFromEnemyLevel($enemyLevel);
        $totalExp = $expPerEnemy * count($state['enemies']);

        $totalGold = (int) config('arena.reward.win_total', 200);
        $alivePlayers = array_filter($state['players'], fn ($m) => $m['hp'] > 0 && ! empty($m['character_id']));
        $aliveCount = max(1, count($alivePlayers));

        // reward_share_bp = ソロ時のキャラ取り分(1万分率)
        // PT時はソロ取り分を人数で分割、残りが家門取り分
        // 例: share_bp=3000, 総額200G, 3人生存
        //   ソロ時キャラ取り分 = 200 × 3000/10000 = 60G
        //   PT時キャラ取り分 = 60 / 3 = 20G/人
        //   家門取り分 = 200 - 20×3 = 140G
        $shareBpMin = (int) config('arena.job_seeker.share_bp_min', 100);
        $shareBpMax = (int) config('arena.job_seeker.share_bp_max', 9000);
        $houseId = null;
        $totalToChar = 0;
        $goldPerChar = [];

        foreach ($alivePlayers as $member) {
            $character = Character::find($member['character_id']);
            if (! $character) continue;
            $houseId = $houseId ?? $character->house_id;
            $bp = max($shareBpMin, min((int) $character->reward_share_bp, $shareBpMax));
            $soloShare = (int) floor($totalGold * $bp / 10000);
            $ptShare = (int) floor($soloShare / $aliveCount);
            $goldPerChar[$member['character_id']] = $ptShare;
            $totalToChar += $ptShare;
        }
        $goldToHouse = max(0, $totalGold - $totalToChar);

        foreach ($state['players'] as $member) {
            if (empty($member['character_id'])) continue;
            $character = Character::find($member['character_id']);
            if (! $character) continue;
            $result = LevelUpService::grantExp($character, $totalExp);
            $goldGained = $goldPerChar[$member['character_id']] ?? 0;
            if ($goldGained > 0) {
                $character->increment('gold', $goldGained);
            }
            if ($result['levels_gained'] > 0 || $goldGained > 0) {
                $results[] = [
                    'character_id' => $character->id,
                    'name' => $character->name,
                    'levels_gained' => $result['levels_gained'],
                    'new_level' => $character->level,
                    'preset_switched' => $result['preset_switched'],
                    'gold_gained' => $goldGained,
                ];
            }
        }

        if ($houseId) {
            House::whereKey($houseId)->increment('gold', $goldToHouse);
        }

        return $results;
    }

    private function dexFillRate(int $dex): int
    {
        $tiers = config('atb.dex_tiers');
        if (empty($tiers)) return 100;
        ksort($tiers);

        $dex = max(1, $dex);
        $prevThreshold = 1;
        $prevRate = $tiers[1] ?? 100;
        $rate = $prevRate;

        foreach ($tiers as $threshold => $factor) {
            if ($dex >= $threshold) {
                $prevThreshold = $threshold;
                $prevRate = $factor;
                $rate = $factor;
                continue;
            }
            // 段階間を線形補間
            $range = $threshold - $prevThreshold;
            if ($range <= 0) break;
            $delta = $factor - $prevRate;
            $pos = $dex - $prevThreshold;
            $rate = (int) round($prevRate + $delta * $pos / $range);
            break;
        }

        return max(1, $rate);
    }

    private function members(array $members, string $side): array
    {
        return array_map(fn (array $member, int $index) => $member + [
            'side' => $side,
            'index' => $index,
            'gauge' => 0,
            'phase' => $side === 'players' ? 'input' : 'casting',
            'reserved_skill' => $side === 'players' ? null : 'normal',
            'cooldown' => 0,
            'guard' => false,
        ], $members, array_keys($members));
    }
}
