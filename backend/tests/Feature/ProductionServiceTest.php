<?php

namespace Tests\Feature;

use App\Models\HouseInventory;
use App\Models\Item;
use App\Services\ProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesArenaFixtures;
use Tests\TestCase;

final class ProductionServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesArenaFixtures;

    #[Test]
    public function 採掘を完了して素材を家門在庫で受け取れる(): void
    {
        $this->seedArenaBasics();
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user, ['gold' => 100]);
        $character = $this->createHiredCharacter($house);
        $service = app(ProductionService::class);

        $job = $service->start($house, $character->id, 'mining');
        $job->update(['completes_at' => now()->subSecond()]);
        $service->collect($house, $job->id);

        $ironOre = Item::query()->where('key', 'iron_ore')->firstOrFail();
        $quantity = HouseInventory::query()->where('house_id', $house->id)->where('item_id', $ironOre->id)->value('quantity');

        $this->assertSame(50, $house->fresh()->gold);
        $this->assertSame(3, $quantity);
        $this->assertSame('collected', $job->fresh()->status);
    }

    #[Test]
    public function 素材とGoldを消費して武器を製作できる(): void
    {
        $this->seedArenaBasics();
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user, ['gold' => 200]);
        $character = $this->createHiredCharacter($house);
        $service = app(ProductionService::class);
        $service->syncItems();
        foreach (['iron_ore' => 3, 'wood' => 1] as $key => $quantity) {
            $item = Item::query()->where('key', $key)->firstOrFail();
            HouseInventory::create(['house_id' => $house->id, 'item_id' => $item->id, 'quantity' => $quantity]);
        }

        $job = $service->start($house, $character->id, 'weapon_forging');
        $job->update(['completes_at' => now()->subSecond()]);
        $service->collect($house, $job->id);

        $sword = Item::query()->where('key', 'iron_sword')->firstOrFail();
        $quantity = HouseInventory::query()->where('house_id', $house->id)->where('item_id', $sword->id)->value('quantity');

        $this->assertSame(80, $house->fresh()->gold);
        $this->assertSame(1, $quantity);
    }
}
