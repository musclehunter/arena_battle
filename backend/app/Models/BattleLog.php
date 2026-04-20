<?php

namespace App\Models;

use App\Enums\BattleActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BattleLog extends Model
{
    protected $fillable = [
        'battle_id',
        'turn_number',
        'player_action',
        'enemy_action',
        'player_damage_to_enemy',
        'enemy_damage_to_player',
        'player_hp_after',
        'enemy_hp_after',
        'summary_text',
    ];

    protected function casts(): array
    {
        return [
            'turn_number' => 'integer',
            'player_action' => BattleActionType::class,
            'enemy_action' => BattleActionType::class,
            'player_damage_to_enemy' => 'integer',
            'enemy_damage_to_player' => 'integer',
            'player_hp_after' => 'integer',
            'enemy_hp_after' => 'integer',
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(Battle::class);
    }
}
