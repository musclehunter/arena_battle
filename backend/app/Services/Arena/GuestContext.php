<?php

namespace App\Services\Arena;

use Illuminate\Contracts\Session\Session;

/**
 * ゲストプレイヤー (未ログイン) の状態をセッションで扱う。
 *
 * - 所持ゴールド
 * - 現在ゲスト雇用中のキャラID
 * - 求職者ボード(3名) の可視 ID リスト(家門プレイヤーも同キーを共用)
 *
 * 家門プレイヤーであっても、求職者ボードと「ゲスト雇用スロット」は
 * このクラスを介してセッションに持つ。
 */
class GuestContext
{
    private const KEY_GOLD = 'arena.guest.gold';
    private const KEY_HIRED_CHARACTER_ID = 'arena.guest.hired_character_id';
    private const KEY_JOB_SEEKER_IDS = 'arena.job_seeker_ids';

    public function __construct(private Session $session)
    {
    }

    // ---- session id ----------------------------------------------------

    public function sessionId(): string
    {
        return $this->session->getId();
    }

    // ---- gold ----------------------------------------------------------

    public function gold(): int
    {
        if (! $this->session->has(self::KEY_GOLD)) {
            $this->session->put(self::KEY_GOLD, (int) config('arena.initial_gold.guest'));
        }

        return (int) $this->session->get(self::KEY_GOLD);
    }

    public function addGold(int $amount): void
    {
        $this->session->put(self::KEY_GOLD, $this->gold() + $amount);
    }

    public function spendGold(int $amount): void
    {
        $this->session->put(self::KEY_GOLD, $this->gold() - $amount);
    }

    /**
     * 家門作成時にゲスト資産を破棄する。
     */
    public function resetOnHouseCreation(): void
    {
        $this->session->forget([
            self::KEY_GOLD,
            self::KEY_HIRED_CHARACTER_ID,
        ]);
    }

    // ---- hired character (ゲスト雇用枠) ---------------------------------

    public function hiredCharacterId(): ?int
    {
        $value = $this->session->get(self::KEY_HIRED_CHARACTER_ID);

        return $value === null ? null : (int) $value;
    }

    public function setHiredCharacter(?int $characterId): void
    {
        if ($characterId === null) {
            $this->session->forget(self::KEY_HIRED_CHARACTER_ID);

            return;
        }

        $this->session->put(self::KEY_HIRED_CHARACTER_ID, $characterId);
    }

    // ---- job seeker board (3名の可視 id) -------------------------------

    /**
     * @return list<int>|null
     */
    public function jobSeekerIds(): ?array
    {
        $ids = $this->session->get(self::KEY_JOB_SEEKER_IDS);

        if (! is_array($ids)) {
            return null;
        }

        return array_values(array_map('intval', $ids));
    }

    /**
     * @param  list<int>  $ids
     */
    public function setJobSeekerIds(array $ids): void
    {
        $this->session->put(self::KEY_JOB_SEEKER_IDS, array_values(array_map('intval', $ids)));
    }

    public function forgetJobSeekerIds(): void
    {
        $this->session->forget(self::KEY_JOB_SEEKER_IDS);
    }
}
