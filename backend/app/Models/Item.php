<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = ['key', 'name', 'category'];

    public function inventories(): HasMany
    {
        return $this->hasMany(HouseInventory::class);
    }
}
