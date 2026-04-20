<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class House extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'level',
        'gold',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'gold' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この家門に正規雇用されているキャラクター。
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class, 'house_id');
    }

    public function battles(): HasMany
    {
        return $this->hasMany(Battle::class);
    }

    /**
     * ゲスト家門(システム用ダミー)ではない、プレイヤーの家門のみに絞るスコープ。
     */
    public function scopePlayerOwned(Builder $query): Builder
    {
        return $query->where('id', '!=', config('arena.guest_house_id'));
    }

    public function isGuestHouse(): bool
    {
        return $this->id === (int) config('arena.guest_house_id');
    }

    /**
     * 現在の Lv における雇用上限。
     */
    public function hireSlots(): int
    {
        $slots = config('arena.house_level_slots', []);

        return $slots[$this->level] ?? 0;
    }
}
