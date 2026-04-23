<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    protected $fillable = [
        'character_preset_id',
        'name',
        'level',
        'exp',
        'str',
        'vit',
        'dex',
        'int_stat',
        'growth_preset_key',
        'growth_index',
        'growth_rank_box',
        'hire_cost',
        'reward_share_bp',
        'gold',
        'house_id',
        'hired_at',
        'icon_index',
        'gender',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'exp' => 'integer',
            'str' => 'integer',
            'vit' => 'integer',
            'dex' => 'integer',
            'int_stat' => 'integer',
            'growth_index' => 'integer',
            'growth_rank_box' => 'array',
            'hire_cost' => 'integer',
            'reward_share_bp' => 'integer',
            'gold' => 'integer',
            'hired_at' => 'datetime',
            'icon_index' => 'integer',
            'gender' => Gender::class,
        ];
    }

    public function preset(): BelongsTo
    {
        return $this->belongsTo(CharacterPreset::class, 'character_preset_id');
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function battles(): HasMany
    {
        return $this->hasMany(Battle::class, 'player_character_id');
    }

    // ---- 状態判定 ------------------------------------------------------

    public function isAvailable(): bool
    {
        return $this->house_id === null;
    }

    public function isGuestHired(): bool
    {
        return $this->house_id === (int) config('arena.guest_house_id');
    }

    public function isEmployedByPlayerHouse(): bool
    {
        return $this->house_id !== null && ! $this->isGuestHired();
    }

    // ---- スコープ ------------------------------------------------------

    /**
     * 求職者プール(誰にも雇用されていない)に絞る。
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('house_id');
    }

    /**
     * 特定の家門に雇用中のキャラ(正規雇用)。
     */
    public function scopeEmployedBy(Builder $query, int $houseId): Builder
    {
        return $query->where('house_id', $houseId);
    }

    /**
     * ゲスト雇用中のキャラ。
     */
    public function scopeGuestHired(Builder $query): Builder
    {
        return $query->where('house_id', (int) config('arena.guest_house_id'));
    }
}
