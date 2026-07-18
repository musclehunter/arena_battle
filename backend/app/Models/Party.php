<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    protected $fillable = [
        'house_id',
        'name',
        'strategy',
        'risk',
    ];

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(PartyMember::class)->orderBy('slot');
    }
}
