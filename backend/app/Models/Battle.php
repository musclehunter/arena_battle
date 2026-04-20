<?php

namespace App\Models;

use App\Enums\BattleStatus;
use App\Enums\BattleWinner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Battle extends Model
{
    protected $fillable = [
        'user_id',
        'house_id',
        'guest_session_id',
        'player_character_id',
        'enemy_preset_id',
        'status',
        'winner',
        'turn_number',
        'player_hp',
        'enemy_hp',
        'action_token',
        'reward_gold_total',
        'reward_gold_to_character',
        'reward_gold_to_house',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BattleStatus::class,
            'winner' => BattleWinner::class,
            'turn_number' => 'integer',
            'player_hp' => 'integer',
            'enemy_hp' => 'integer',
            'reward_gold_total' => 'integer',
            'reward_gold_to_character' => 'integer',
            'reward_gold_to_house' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function playerCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'player_character_id');
    }

    public function enemyPreset(): BelongsTo
    {
        return $this->belongsTo(CharacterPreset::class, 'enemy_preset_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BattleLog::class)->orderBy('turn_number');
    }

    public function isGuestBattle(): bool
    {
        return $this->user_id === null && $this->guest_session_id !== null;
    }
}
