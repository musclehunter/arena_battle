<?php

namespace Tests\Feature;

use App\Actions\Battle\StartBattleAction;
use App\Enums\BattleWinner;
use App\Services\Arena\GuestContext;
use App\Services\Arena\RewardDistributor;
use App\Services\Battle\BattleContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesArenaFixtures;
use Tests\TestCase;

/**
 * 勝利時の報酬分配(200G 固定、share_bp で分配)。
 */
final class RewardDistributionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesArenaFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedArenaBasics();
    }

    #[Test]
    public function 家門バトル勝利時は家門とキャラにshareBpで分配される(): void
    {
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user, ['gold' => 1000]);
        // share 30% → キャラ60G / 家門140G
        $character = $this->createHiredCharacter($house, [
            'reward_share_bp' => 3000,
            'gold' => 50,
        ]);

        $battle = app(StartBattleAction::class)->execute(
            character: $character,
            context: BattleContext::forHouse($house, $user),
            enemyPreset: $this->enemyPreset('enemy_slime'),
        );
        $battle->winner = BattleWinner::Player;

        app(RewardDistributor::class)->distribute($battle);

        $this->assertSame(200, $battle->reward_gold_total);
        $this->assertSame(60, $battle->reward_gold_to_character);
        $this->assertSame(140, $battle->reward_gold_to_house);
        $this->assertSame(1000 + 140, $house->fresh()->gold);
        $this->assertSame(50 + 60, $character->fresh()->gold);
    }

    #[Test]
    public function ゲスト雇用バトル勝利時は家門取り分がゲストゴールドに加算される(): void
    {
        // セッションを先に開始してゲスト gold を初期化
        $guest = app(GuestContext::class);
        $initialGuestGold = $guest->gold(); // 1000

        $character = $this->createJobSeeker([
            'reward_share_bp' => 5000, // 50%
            'gold' => 0,
            'house_id' => (int) config('arena.guest_house_id'),
            'hired_at' => now(),
        ]);

        // guest_session_id は GuestContext の session id と一致させる
        $battle = app(StartBattleAction::class)->execute(
            character: $character,
            context: BattleContext::forGuest($guest->sessionId()),
            enemyPreset: $this->enemyPreset('enemy_slime'),
        );
        $battle->winner = BattleWinner::Player;

        app(RewardDistributor::class)->distribute($battle);

        // total=200, share 50%/50%
        $this->assertSame(200, $battle->reward_gold_total);
        $this->assertSame(100, $battle->reward_gold_to_character);
        $this->assertSame(100, $battle->reward_gold_to_house);
        $this->assertSame($initialGuestGold + 100, $guest->gold(), 'ゲストに家門取り分が入る');
        $this->assertSame(100, $character->fresh()->gold, 'キャラ本人にも入る');
    }

    #[Test]
    public function 敗北時は報酬は0で記録される(): void
    {
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user, ['gold' => 1000]);
        $character = $this->createHiredCharacter($house, ['gold' => 50]);

        $battle = app(StartBattleAction::class)->execute(
            character: $character,
            context: BattleContext::forHouse($house, $user),
            enemyPreset: $this->enemyPreset('enemy_slime'),
        );
        $battle->winner = BattleWinner::Enemy;

        app(RewardDistributor::class)->distribute($battle);

        $this->assertSame(0, $battle->reward_gold_total);
        $this->assertSame(0, $battle->reward_gold_to_character);
        $this->assertSame(0, $battle->reward_gold_to_house);
        $this->assertSame(1000, $house->fresh()->gold);
        $this->assertSame(50, $character->fresh()->gold);
    }
}
