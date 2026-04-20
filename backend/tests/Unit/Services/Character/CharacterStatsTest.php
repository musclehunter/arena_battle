<?php

namespace Tests\Unit\Services\Character;

use App\Services\Character\CharacterStats;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CharacterStatsTest extends TestCase
{
    #[Test]
    public function 派生ステはデフォルト係数で式通りに算出される(): void
    {
        // STR=10, VIT=10, DEX=10, INT=0
        // HP = floor(10*1 + 10*2) + 15 = 30 + 15 = 45
        // ATK = floor(10*1 + 10*0.5) = 15
        // DEF = floor(10*0.5 + 10*0.5) = 10
        $result = CharacterStats::derive([
            'str' => 10, 'vit' => 10, 'dex' => 10, 'int_stat' => 0,
        ]);

        $this->assertSame(45, $result['hp']);
        $this->assertSame(15, $result['atk']);
        $this->assertSame(10, $result['def']);
    }

    #[Test]
    public function ゼロステでもHPは1以上DEFは0以上になる(): void
    {
        $result = CharacterStats::derive([
            'str' => 0, 'vit' => 0, 'dex' => 0, 'int_stat' => 0,
        ]);

        $this->assertGreaterThanOrEqual(1, $result['hp']);
        $this->assertSame(0, $result['atk']);
        $this->assertSame(0, $result['def']);
    }

    #[Test]
    public function forEntityはオブジェクトの各ステを読み出す(): void
    {
        $entity = (object) ['str' => 14, 'vit' => 14, 'dex' => 8, 'int_stat' => 4];
        $result = CharacterStats::forEntity($entity);

        // HP = floor(14*1 + 14*2) + 15 = 42 + 15 = 57
        $this->assertSame(57, $result['hp']);
    }
}
