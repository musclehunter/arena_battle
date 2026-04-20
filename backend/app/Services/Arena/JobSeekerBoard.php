<?php

namespace App\Services\Arena;

use App\Enums\BattleStatus;
use App\Models\Battle;
use App\Models\Character;
use Illuminate\Support\Collection;

/**
 * 求職者ボード。
 *
 * セッションに「現在表示中の 3 名の ID」を保持し、
 * アリーナバトル完了時に破棄されるまで同じメンバーを出し続ける。
 *
 * 候補プールは characters.house_id IS NULL かつ
 * 進行中バトルに関与していないキャラ。
 */
class JobSeekerBoard
{
    public function __construct(private GuestContext $guest)
    {
    }

    /**
     * 現在表示中の求職者コレクション(最大 visible_count)。
     *
     * セッションに未保存なら抽選してセッションに書き込む。
     * 既に保存されている ID のうち「雇用済みになったもの」は自動的に除外。
     *
     * @return Collection<int, Character>
     */
    public function visible(): Collection
    {
        $ids = $this->guest->jobSeekerIds();

        // null = 未抽選、空配列 = 候補ゼロ時に保存された残骸(DB がまだ空だったケース等)
        // どちらも再抽選トリガーにしておく
        if ($ids === null || $ids === []) {
            $picked = $this->pickNew();
            $this->guest->setJobSeekerIds($picked->pluck('id')->map(fn ($id) => (int) $id)->all());

            return $picked;
        }

        // 既存リストから、現在も求職中のキャラだけを取得(順序維持)
        $characters = Character::query()
            ->whereIn('id', $ids)
            ->whereNull('house_id')
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $characters->get($id))
            ->filter()
            ->values();
    }

    /**
     * アリーナバトル完了時に呼ぶ。次回訪問で新しい 3 名が抽選される。
     */
    public function invalidate(): void
    {
        $this->guest->forgetJobSeekerIds();
    }

    /**
     * 候補プールからランダムに visible_count 名を選ぶ。
     *
     * @return Collection<int, Character>
     */
    public function pickNew(): Collection
    {
        $size = (int) config('arena.job_seeker.visible_count', 3);

        // 進行中バトルに関与しているキャラ ID
        $lockedIds = Battle::query()
            ->where('status', BattleStatus::InProgress->value)
            ->pluck('player_character_id');

        return Character::query()
            ->whereNull('house_id')
            ->whereNotIn('id', $lockedIds)
            ->inRandomOrder()
            ->limit($size)
            ->get();
    }
}
