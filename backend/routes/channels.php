<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('party-battle.{battleId}', function ($user, $battleId) {
    return \App\Models\PartyBattle::query()->whereKey($battleId)->whereHas('house', fn ($query) => $query->where('user_id', $user->id))->exists();
});
