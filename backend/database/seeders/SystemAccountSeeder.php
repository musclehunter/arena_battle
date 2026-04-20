<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * システム用ダミーユーザーを作成する。
 *
 * - id は config('arena.system_user_id') と一致する必要がある。
 * - Guest House の owner として利用されるだけで、ログイン等では使用しない。
 */
class SystemAccountSeeder extends Seeder
{
    public function run(): void
    {
        $systemId = (int) config('arena.system_user_id');

        $user = User::updateOrCreate(
            ['id' => $systemId],
            [
                'name' => 'System',
                'email' => 'system@arena.local',
                'password' => Hash::make(bin2hex(random_bytes(16))),
                'email_verified_at' => now(),
            ],
        );

        if ($user->id !== $systemId) {
            throw new RuntimeException(sprintf(
                'SystemAccountSeeder: expected user id %d but got %d. DB が clean な状態で migrate:fresh --seed を実行してください。',
                $systemId,
                $user->id,
            ));
        }
    }
}
