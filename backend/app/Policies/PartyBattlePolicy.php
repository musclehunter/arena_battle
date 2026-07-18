<?php

namespace App\Policies;

use App\Models\PartyBattle;
use App\Models\User;

class PartyBattlePolicy
{
    public function view(User $user, PartyBattle $battle): bool
    {
        return (int) $battle->house->user_id === (int) $user->id;
    }

    public function update(User $user, PartyBattle $battle): bool
    {
        return $this->view($user, $battle);
    }
}
