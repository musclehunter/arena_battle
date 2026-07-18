<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionJob extends Model
{
    protected $fillable = ['house_id', 'character_id', 'activity_key', 'status', 'output', 'gold_cost', 'started_at', 'completes_at', 'collected_at'];

    protected function casts(): array
    {
        return ['output' => 'array', 'gold_cost' => 'integer', 'started_at' => 'datetime', 'completes_at' => 'datetime', 'collected_at' => 'datetime'];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
