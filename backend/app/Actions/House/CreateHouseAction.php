<?php

namespace App\Actions\House;

use App\Models\House;
use App\Models\User;
use App\Services\Arena\GuestContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ユーザーの家門を新規作成する。
 *
 * - 1 ユーザー 1 家門(既に保有していれば失敗)
 * - 初期 gold は config('arena.initial_gold.house')
 * - 家門作成と同時に **ゲストセッション側の資産(gold / hired_character_id) を破棄** する
 *   (求職者ボードはそのまま残す。特別扱いの必要がないため)
 */
final class CreateHouseAction
{
    public function __construct(private readonly GuestContext $guest)
    {
    }

    public function execute(User $user, string $name): House
    {
        return DB::transaction(function () use ($user, $name): House {
            if ($user->house()->exists()) {
                throw new RuntimeException('既にこのユーザーには家門があります。');
            }

            $house = House::create([
                'user_id' => $user->id,
                'name' => $name,
                'level' => 1,
                'gold' => (int) config('arena.initial_gold.house', 1000),
            ]);

            $this->guest->resetOnHouseCreation();

            return $house;
        });
    }
}
