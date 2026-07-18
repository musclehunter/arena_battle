<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    protected $fillable = [
        'key', 'name', 'job', 'line', 'description',
        'sp_cost', 'unlock_level',
        'scales_with', 'power', 'cast_gauge', 'cooldown_gauge',
        'element', 'target_type', 'target_count',
        'effect_type', 'effect_power', 'effect_duration',
        'is_passive',
    ];

    protected function casts(): array
    {
        return [
            'sp_cost' => 'integer',
            'unlock_level' => 'integer',
            'power' => 'float',
            'cast_gauge' => 'integer',
            'cooldown_gauge' => 'integer',
            'target_count' => 'integer',
            'effect_power' => 'integer',
            'effect_duration' => 'integer',
            'is_passive' => 'boolean',
        ];
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_skills')
            ->withPivot('learned_at')
            ->withTimestamps();
    }
}
