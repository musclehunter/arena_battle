<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 成長プリセット。
 *
 * increments は 10 要素の配列で、各要素は { str, vit, dex, int_stat } を持つ。
 * index N の増分は、そのプリセットを使用中のキャラが N+1 回目の Lvup 時に適用される。
 */
class GrowthPreset extends Model
{
    protected $fillable = [
        'key',
        'name',
        'job',
        'rank',
        'rank_order',
        'increments',
    ];

    protected function casts(): array
    {
        return [
            'increments' => 'array',
            'rank_order' => 'integer',
        ];
    }

    /**
     * 指定 index (0-9) の増分を取得。
     *
     * @return array{str:int, vit:int, dex:int, int_stat:int}
     */
    public function incrementAt(int $index): array
    {
        $incs = $this->increments ?? [];
        $raw = $incs[$index] ?? ['str' => 0, 'vit' => 0, 'dex' => 0, 'int_stat' => 0];

        return [
            'str' => (int) ($raw['str'] ?? 0),
            'vit' => (int) ($raw['vit'] ?? 0),
            'dex' => (int) ($raw['dex'] ?? 0),
            'int_stat' => (int) ($raw['int_stat'] ?? 0),
        ];
    }

}
