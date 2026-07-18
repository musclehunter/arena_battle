<?php

namespace App\Services;

use App\Models\Character;
use App\Models\House;
use App\Models\HouseInventory;
use App\Models\Item;
use App\Models\ProductionJob;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionService
{
    public function syncItems(): void
    {
        foreach (config('production.items') as $key => $item) {
            Item::query()->updateOrCreate(['key' => $key], ['name' => $item['name'], 'category' => $item['category']]);
        }
    }

    public function completeDueJobs(House $house): void
    {
        ProductionJob::query()
            ->where('house_id', $house->id)
            ->where('status', 'in_progress')
            ->where('completes_at', '<=', now())
            ->update(['status' => 'completed']);
    }

    public function start(House $house, int $characterId, string $activityKey): ProductionJob
    {
        $this->syncItems();
        $activity = config("production.activities.{$activityKey}");
        if (! $activity) throw new RuntimeException('不明な生産作業です。');

        return DB::transaction(function () use ($house, $characterId, $activityKey, $activity): ProductionJob {
            $lockedHouse = House::query()->lockForUpdate()->findOrFail($house->id);
            $character = Character::query()->where('house_id', $lockedHouse->id)->find($characterId);
            if (! $character) throw new RuntimeException('家門に所属するキャラを選択してください。');
            if (ProductionJob::query()->where('character_id', $character->id)->where('status', 'in_progress')->exists()) {
                throw new RuntimeException('このキャラはすでに生産中です。');
            }
            if ($lockedHouse->gold < $activity['gold_cost']) throw new RuntimeException('家門Goldが不足しています。');

            foreach ($activity['inputs'] as $itemKey => $quantity) {
                $this->consume($lockedHouse, $itemKey, $quantity);
            }
            $lockedHouse->decrement('gold', $activity['gold_cost']);

            return ProductionJob::create([
                'house_id' => $lockedHouse->id,
                'character_id' => $character->id,
                'activity_key' => $activityKey,
                'status' => 'in_progress',
                'output' => ['item_key' => $activity['output'], 'quantity' => $activity['output_quantity']],
                'gold_cost' => $activity['gold_cost'],
                'started_at' => now(),
                'completes_at' => now()->addSeconds((int) config('production.duration_seconds')),
            ]);
        });
    }

    public function collect(House $house, int $jobId): ProductionJob
    {
        $this->syncItems();
        $this->completeDueJobs($house);

        return DB::transaction(function () use ($house, $jobId): ProductionJob {
            $job = ProductionJob::query()->where('house_id', $house->id)->lockForUpdate()->find($jobId);
            if (! $job) throw new RuntimeException('生産ジョブが見つかりません。');
            if ($job->status !== 'completed') throw new RuntimeException('まだ生産が完了していません。');

            $this->add($house, $job->output['item_key'], $job->output['quantity']);
            $job->update(['status' => 'collected', 'collected_at' => now()]);

            return $job->fresh('character');
        });
    }

    public function inventory(House $house): array
    {
        $this->syncItems();

        return HouseInventory::query()->where('house_id', $house->id)->with('item')->get()
            ->map(fn (HouseInventory $inventory) => ['key' => $inventory->item->key, 'name' => $inventory->item->name, 'category' => $inventory->item->category, 'quantity' => $inventory->quantity])
            ->values()->all();
    }

    private function consume(House $house, string $itemKey, int $quantity): void
    {
        $item = Item::query()->where('key', $itemKey)->firstOrFail();
        $inventory = HouseInventory::query()->where('house_id', $house->id)->where('item_id', $item->id)->lockForUpdate()->first();
        if (! $inventory || $inventory->quantity < $quantity) throw new RuntimeException("{$item->name}が不足しています。");
        $inventory->decrement('quantity', $quantity);
    }

    private function add(House $house, string $itemKey, int $quantity): void
    {
        $item = Item::query()->where('key', $itemKey)->firstOrFail();
        $inventory = HouseInventory::query()->firstOrCreate(['house_id' => $house->id, 'item_id' => $item->id]);
        $inventory->increment('quantity', $quantity);
    }
}
