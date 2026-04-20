<?php

namespace Tests\Feature;

use App\Actions\Battle\ResolveTurnAction;
use App\Actions\Battle\StartBattleAction;
use App\Enums\BattleActionType;
use App\Enums\BattleStatus;
use App\Models\Battle;
use App\Services\Battle\BattleContext;
use App\Services\Character\CharacterStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesArenaFixtures;
use Tests\TestCase;

/**
 * バトル開始 → ターン解決の結合テスト(E2E 寄り)。v1.1 対応版。
 */
final class BattleTurnResolutionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesArenaFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedArenaBasics();
    }

    private function startHouseBattle(): Battle
    {
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user);
        $character = $this->createHiredCharacter($house);

        return app(StartBattleAction::class)->execute(
            character: $character,
            context: BattleContext::forHouse($house, $user),
            enemyPreset: $this->enemyPreset('enemy_slime'),
        );
    }

    #[Test]
    public function バトル開始で初期HPと初期ログと初回actionTokenが作成される(): void
    {
        $battle = $this->startHouseBattle();

        $this->assertSame(BattleStatus::InProgress, $battle->status);
        // v1.2: HP は STR/VIT からの派生値
        $expectedPlayerHp = CharacterStats::forEntity($battle->playerCharacter)['hp'];
        $expectedEnemyHp = CharacterStats::forPreset($battle->enemyPreset)['hp'];
        $this->assertSame($expectedPlayerHp, $battle->player_hp);
        $this->assertSame($expectedEnemyHp, $battle->enemy_hp);
        $this->assertSame(1, $battle->turn_number);
        $this->assertNotEmpty($battle->action_token);
        $this->assertCount(1, $battle->logs);
        $this->assertSame(0, $battle->logs->first()->turn_number);
    }

    #[Test]
    public function ターンを1回解決するとHPとログとtokenが更新される(): void
    {
        $battle = $this->startHouseBattle();
        $previousToken = $battle->action_token;
        $beforePlayerHp = $battle->player_hp;
        $beforeEnemyHp = $battle->enemy_hp;

        $battle = app(ResolveTurnAction::class)->execute($battle, BattleActionType::Weak);

        $this->assertSame(2, $battle->turn_number, 'turn incremented');
        $this->assertLessThanOrEqual($beforeEnemyHp, $battle->enemy_hp);
        $this->assertLessThanOrEqual($beforePlayerHp, $battle->player_hp);
        $this->assertNotSame($previousToken, $battle->action_token, 'token rotated');
        $this->assertCount(2, $battle->logs);
    }

    #[Test]
    public function プレイヤーHPが0になるとステータスがfinishedになりtokenが無効化される(): void
    {
        $battle = $this->startHouseBattle();
        $battle->update(['player_hp' => 1]);

        $battle = app(ResolveTurnAction::class)->execute($battle->fresh(), BattleActionType::Weak);

        $this->assertSame(BattleStatus::Finished, $battle->status);
        $this->assertNotNull($battle->winner);
        $this->assertNull($battle->action_token);
        $this->assertNotNull($battle->ended_at);
    }

    #[Test]
    public function 不正なトークンで行動を送信するとバリデーションエラーになる(): void
    {
        $battle = $this->startHouseBattle();

        $response = $this->actingAs($battle->user)
            ->from(route('battles.show', $battle))
            ->post(route('battles.turn', $battle), [
                'action' => 'weak',
                'token' => 'invalid_token',
            ]);

        $response->assertSessionHasErrors('token');
        $this->assertSame(1, Battle::find($battle->id)->turn_number);
    }

    #[Test]
    public function 終了済みのバトルに行動を送信するとバリデーションエラーになる(): void
    {
        $battle = $this->startHouseBattle();
        $battle->update([
            'status' => BattleStatus::Finished,
            'action_token' => null,
        ]);

        $response = $this->actingAs($battle->user)
            ->from(route('battles.show', $battle))
            ->post(route('battles.turn', $battle), [
                'action' => 'weak',
                'token' => 'whatever',
            ]);

        $response->assertSessionHasErrors('status');
    }
}
