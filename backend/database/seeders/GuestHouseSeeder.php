<?php

namespace Database\Seeders;

use App\Models\House;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * ゲスト雇用用のダミー家門を作成する。
 *
 * - id は config('arena.guest_house_id') と一致する必要がある。
 * - owner は SystemAccountSeeder で作成したシステムユーザー。
 * - ゲスト雇用中のキャラクターはこの house に紐付くが、プレイ画面では
 *   通常の家門としては扱わない(House::scopePlayerOwned で除外)。
 */
class GuestHouseSeeder extends Seeder
{
    public function run(): void
    {
        $guestHouseId = (int) config('arena.guest_house_id');
        $systemUserId = (int) config('arena.system_user_id');

        $house = House::updateOrCreate(
            ['id' => $guestHouseId],
            [
                'user_id' => $systemUserId,
                'name' => 'Guest',
                'level' => 1,
                'gold' => 0, // ゲストの所持金はセッション側で管理するので DB は 0 固定
            ],
        );

        if ($house->id !== $guestHouseId) {
            throw new RuntimeException(sprintf(
                'GuestHouseSeeder: expected house id %d but got %d. DB が clean な状態で migrate:fresh --seed を実行してください。',
                $guestHouseId,
                $house->id,
            ));
        }
    }
}
