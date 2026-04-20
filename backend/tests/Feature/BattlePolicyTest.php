<?php

namespace Tests\Feature;

use App\Actions\Battle\StartBattleAction;
use App\Services\Battle\BattleContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesArenaFixtures;
use Tests\TestCase;

/**
 * BattlePolicy: 所有者以外のバトル操作拒否。
 */
final class BattlePolicyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesArenaFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedArenaBasics();
    }

    #[Test]
    public function 他ユーザーの家門バトルは閲覧も操作もできない(): void
    {
        $owner = $this->createPlayerUser();
        $house = $this->createHouseFor($owner);
        $character = $this->createHiredCharacter($house);
        $battle = app(StartBattleAction::class)->execute(
            character: $character,
            context: BattleContext::forHouse($house, $owner),
            enemyPreset: $this->enemyPreset(),
        );

        $intruder = $this->createPlayerUser();

        $this->actingAs($intruder)
            ->get(route('battles.show', $battle))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->post(route('battles.turn', $battle), [
                'action' => 'weak',
                'token' => $battle->action_token,
            ])->assertForbidden();
    }

    #[Test]
    public function 家門バトルの所有者本人はアクセスできる(): void
    {
        $owner = $this->createPlayerUser();
        $house = $this->createHouseFor($owner);
        $character = $this->createHiredCharacter($house);
        $battle = app(StartBattleAction::class)->execute(
            character: $character,
            context: BattleContext::forHouse($house, $owner),
            enemyPreset: $this->enemyPreset(),
        );

        $this->actingAs($owner)
            ->get(route('battles.show', $battle))
            ->assertOk();
    }

    #[Test]
    public function 未認証ユーザーは他人のゲストセッションのバトルを見られない(): void
    {
        $character = $this->createJobSeeker();

        // 「別のゲスト」のバトルを手動作成 (異なる guest_session_id)
        $battle = \App\Models\Battle::create([
            'user_id' => null,
            'house_id' => null,
            'guest_session_id' => 'other_guest_session_xxx',
            'player_character_id' => $character->id,
            'enemy_preset_id' => $this->enemyPreset()->id,
            'status' => 'in_progress',
            'player_hp' => 10,
            'enemy_hp' => 10,
            'turn_number' => 1,
            'action_token' => 'tok',
            'started_at' => now(),
        ]);

        // 現在のテストセッション id はこのバトルの guest_session_id とは一致しないので 403
        $this->get(route('battles.show', $battle))->assertForbidden();
    }
}
