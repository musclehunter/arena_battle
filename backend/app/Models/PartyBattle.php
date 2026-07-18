<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyBattle extends Model
{
    protected $fillable = ['house_id', 'party_id', 'strategy', 'risk', 'status', 'winner', 'round', 'player_state', 'enemy_state', 'logs', 'reward_gold', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return ['round' => 'integer', 'player_state' => 'array', 'enemy_state' => 'array', 'logs' => 'array', 'reward_gold' => 'integer', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
