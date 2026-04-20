<?php

namespace App\Policies;

use App\Models\Battle;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * バトルの所有者認可。
 *
 * - 認証ユーザー: battles.user_id が自分と一致
 * - ゲスト(未認証): battles.guest_session_id が現セッション ID と一致
 *
 * どちらの条件も満たさなければ 403。
 */
class BattlePolicy
{
    public function __construct(private readonly Session $session)
    {
    }

    public function view(?User $user, Battle $battle): bool
    {
        return $this->isOwner($user, $battle);
    }

    public function update(?User $user, Battle $battle): bool
    {
        return $this->isOwner($user, $battle);
    }

    private function isOwner(?User $user, Battle $battle): bool
    {
        if ($user !== null && $battle->user_id !== null && (int) $battle->user_id === (int) $user->id) {
            return true;
        }

        if ($battle->guest_session_id !== null
            && hash_equals((string) $battle->guest_session_id, $this->session->getId())) {
            return true;
        }

        return false;
    }
}
