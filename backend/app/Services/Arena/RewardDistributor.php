<?php

namespace App\Services\Arena;

use App\Enums\BattleWinner;
use App\Models\Battle;
use Illuminate\Support\Facades\DB;

/**
 * バトル勝利時のゴールド報酬分配。
 *
 * - 総額は config('arena.reward.win_total') (v1.1 は 200 固定)
 * - reward_share_bp(basis points)に従ってキャラ/家門で分配
 * - ゲスト雇用バトルの場合、家門取り分は GuestContext (ゲストの所持gold)に入る
 *
 * 呼び出し側は勝者確定後、かつ battles 更新前後どちらでも構わないが、
 * battle に報酬カラムを書き込むため 1 回だけ呼ぶこと。
 */
class RewardDistributor
{
    public function __construct(private GuestContext $guest)
    {
    }

    /**
     * 勝敗に応じて報酬を分配し、battle に記録する。
     *
     * @return array{total:int, to_character:int, to_house:int}
     */
    public function distribute(Battle $battle): array
    {
        if ($battle->winner !== BattleWinner::Player) {
            $battle->reward_gold_total = (int) config('arena.reward.lose_total', 0);
            $battle->reward_gold_to_character = 0;
            $battle->reward_gold_to_house = 0;

            return [
                'total' => (int) $battle->reward_gold_total,
                'to_character' => 0,
                'to_house' => 0,
            ];
        }

        $total = (int) config('arena.reward.win_total', 200);
        $character = $battle->playerCharacter;
        $shareBp = (int) $character->reward_share_bp;
        $shareBp = max(
            (int) config('arena.job_seeker.share_bp_min', 100),
            min($shareBp, (int) config('arena.job_seeker.share_bp_max', 9000))
        );

        $toCharacter = (int) floor($total * $shareBp / 10000);
        $toHouse = $total - $toCharacter;

        DB::transaction(function () use ($battle, $character, $toCharacter, $toHouse) {
            // キャラクターの蓄財
            $character->increment('gold', $toCharacter);

            // 家門 or ゲストに付与
            $guestHouseId = (int) config('arena.guest_house_id');
            if ((int) $character->house_id === $guestHouseId) {
                // ゲスト雇用中 → ゲストセッションへ
                $this->guest->addGold($toHouse);
            } elseif ($battle->house_id !== null && (int) $battle->house_id !== $guestHouseId) {
                // 家門バトル
                $battle->house()->getRelated()
                    ->newQuery()
                    ->whereKey($battle->house_id)
                    ->increment('gold', $toHouse);
            } else {
                // 家門もゲストもない状態(未ログイン + キャラが既に求職者に戻ったなど)
                // → ゲストに付与してフォールバック
                $this->guest->addGold($toHouse);
            }
        });

        $battle->reward_gold_total = $total;
        $battle->reward_gold_to_character = $toCharacter;
        $battle->reward_gold_to_house = $toHouse;

        return [
            'total' => $total,
            'to_character' => $toCharacter,
            'to_house' => $toHouse,
        ];
    }
}
