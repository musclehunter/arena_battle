<?php

namespace App\Services\Character;

/**
 * 成長ランク(easy/normal/hard/expert/master) のヘルパ。
 *
 * - RANKS: 昇順並び
 * - easy の下 = なし (下位なし)
 * - master の次 = easy (ループ)
 *
 * プリセットキーは "{job}_{rank}" 形式 (例: "warrior_easy")。
 */
final class GrowthRank
{
    public const RANKS = ['easy', 'normal', 'hard', 'expert', 'master'];

    public static function order(string $rank): int
    {
        $i = array_search($rank, self::RANKS, true);
        if ($i === false) {
            return -1;
        }

        return (int) $i;
    }

    public static function exists(string $rank): bool
    {
        return in_array($rank, self::RANKS, true);
    }

    /**
     * 下位ランク。easy の下は null (下位なし)。
     */
    public static function lower(string $rank): ?string
    {
        $i = self::order($rank);

        return $i > 0 ? self::RANKS[$i - 1] : null;
    }

    /**
     * 次ランク。master の次は easy (ループ)。
     */
    public static function next(string $rank): string
    {
        $i = self::order($rank);
        $next = ($i + 1) % count(self::RANKS);

        return self::RANKS[$next];
    }

    /**
     * 1 つ前のランクをループ込みで返す。easy の前は master。
     * (lower() は easy の前 = null なのに対し、こちらは常に存在するランクを返す)
     */
    public static function prev(string $rank): string
    {
        $i = self::order($rank);
        $prev = ($i - 1 + count(self::RANKS)) % count(self::RANKS);

        return self::RANKS[$prev];
    }

    /**
     * プリセット key から job を取り出す。
     */
    public static function jobFromKey(?string $key): ?string
    {
        if (! $key || ! str_contains($key, '_')) {
            return null;
        }

        return substr($key, 0, (int) strrpos($key, '_'));
    }

    /**
     * プリセット key から rank を取り出す。
     */
    public static function rankFromKey(?string $key): ?string
    {
        if (! $key || ! str_contains($key, '_')) {
            return null;
        }

        return substr($key, (int) strrpos($key, '_') + 1);
    }

    /**
     * job + rank からプリセット key を組み立てる。
     */
    public static function composeKey(string $job, string $rank): string
    {
        return $job.'_'.$rank;
    }

    /**
     * 指定ランク current の初期抽選箱を生成。
     * config の rank_box.initial_current / initial_next に従う。
     *
     * @return list<string>
     */
    public static function initialBox(string $currentRank): array
    {
        $initCurrent = (int) config('arena.rank_box.initial_current', 2);
        $initNext = (int) config('arena.rank_box.initial_next', 1);

        $box = array_fill(0, max(0, $initCurrent), $currentRank);

        // 次ランクを追加 (master 以外では next、master は easy ループ)
        $next = self::next($currentRank);
        for ($i = 0; $i < $initNext; $i++) {
            $box[] = $next;
        }

        return array_values($box);
    }
}
