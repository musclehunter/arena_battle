<?php

namespace App\Services\Character;

use App\Models\Character;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SkillService
{
    /**
     * キャラクターの職業に応じた習得可能スキル一覧を返す。
     */
    public function learnableSkills(Character $character): array
    {
        $job = $this->jobFromCharacter($character);

        // 封印中の職業はスキル習得不可
        $availableJobs = array_keys(config('skills.jobs', []));
        if (! in_array($job, $availableJobs, true)) {
            return [];
        }

        $allSkills = Skill::where('job', $job)->orderBy('unlock_level')->get();
        $learnedIds = $character->skills()->pluck('skills.id')->all();

        return $allSkills->map(function (Skill $skill) use ($character, $learnedIds) {
            return [
                'id' => $skill->id,
                'key' => $skill->key,
                'name' => $skill->name,
                'description' => $skill->description,
                'sp_cost' => $skill->sp_cost,
                'unlock_level' => $skill->unlock_level,
                'power' => $skill->power,
                'element' => $skill->element,
                'target_type' => $skill->target_type,
                'effect_type' => $skill->effect_type,
                'is_passive' => $skill->is_passive,
                'learned' => in_array($skill->id, $learnedIds),
                'can_learn' => ! in_array($skill->id, $learnedIds)
                    && $character->level >= $skill->unlock_level
                    && $character->skill_points >= $skill->sp_cost,
            ];
        })->toArray();
    }

    /**
     * スキルを習得する。
     */
    public function learn(Character $character, int $skillId): array
    {
        $skill = Skill::find($skillId);
        if (! $skill) throw new RuntimeException('スキルが見つかりません。');

        $job = $this->jobFromCharacter($character);
        $availableJobs = array_keys(config('skills.jobs', []));
        if (! in_array($job, $availableJobs, true)) {
            throw new RuntimeException('この職業のスキルはまだ解放されていません。');
        }
        if ($skill->job !== $job) throw new RuntimeException('この職業では習得できません。');

        if ($character->level < $skill->unlock_level) {
            throw new RuntimeException("レベル{$skill->unlock_level}以上でないと習得できません。");
        }

        if ($character->skill_points < $skill->sp_cost) {
            throw new RuntimeException('スキルポイントが不足しています。');
        }

        if ($character->skills()->where('skill_id', $skillId)->exists()) {
            throw new RuntimeException('既に習得済みです。');
        }

        return DB::transaction(function () use ($character, $skill) {
            $character->decrement('skill_points', $skill->sp_cost);
            $character->skills()->attach($skill->id, ['learned_at' => now()]);
            $character->refresh();
            return [
                'character_id' => $character->id,
                'skill_points' => $character->skill_points,
                'learned_skill' => ['id' => $skill->id, 'key' => $skill->key, 'name' => $skill->name],
            ];
        });
    }

    /**
     * キャラクターの職業キーを推定する。
     */
    private function jobFromCharacter(Character $character): string
    {
        $preset = $character->preset;
        if (! $preset) return 'warrior';
        return match ($preset->icon_key) {
            'human_warrior' => 'warrior',
            'human_rogue' => 'rogue',
            'human_priest' => 'priest',
            'human_mage' => 'mage',
            default => 'warrior',
        };
    }
}
