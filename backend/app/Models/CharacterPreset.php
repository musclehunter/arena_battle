<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterPreset extends Model
{
    protected $fillable = [
        'key',
        'name',
        'base_str',
        'base_vit',
        'base_dex',
        'base_int',
        'base_level',
        'growth_preset_key',
        'ai_type',
        'is_enemy',
    ];

    protected function casts(): array
    {
        return [
            'base_str' => 'integer',
            'base_vit' => 'integer',
            'base_dex' => 'integer',
            'base_int' => 'integer',
            'base_level' => 'integer',
            'is_enemy' => 'boolean',
        ];
    }
}
