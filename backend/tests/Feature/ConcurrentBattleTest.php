<?php

namespace Tests\Feature;

use App\Actions\Battle\StartBattleAction;
use App\Exceptions\Arena\ConcurrentBattleException;
use App\Services\Battle\BattleContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesArenaFixtures;
use Tests\TestCase;

/**
 * 同一 house / guest_session_id で in_progress のバトルが 1 件までであることを検証。
 */
final class ConcurrentBattleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesArenaFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedArenaBasics();
    }

    #[Test]
    public function 同一家門で同時に2つのバトルを開始できない(): void
    {
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user);
        $c1 = $this->createHiredCharacter($house);
        $c2 = $this->createHiredCharacter($house);

        $start = app(StartBattleAction::class);
        $start->execute($c1, BattleContext::forHouse($house, $user), $this->enemyPreset());

        $this->expectException(ConcurrentBattleException::class);
        $start->execute($c2, BattleContext::forHouse($house, $user), $this->enemyPreset());
    }

    #[Test]
    public function 同一ゲストセッションで同時に2つのバトルを開始できない(): void
    {
        $c1 = $this->createJobSeeker([
            'house_id' => (int) config('arena.guest_house_id'),
        ]);
        $c2 = $this->createJobSeeker([
            'house_id' => (int) config('arena.guest_house_id'),
        ]);

        $start = app(StartBattleAction::class);
        $start->execute($c1, BattleContext::forGuest('sess_x'), $this->enemyPreset());

        $this->expectException(ConcurrentBattleException::class);
        $start->execute($c2, BattleContext::forGuest('sess_x'), $this->enemyPreset());
    }

    #[Test]
    public function 異なる家門間は同時進行OK(): void
    {
        $userA = $this->createPlayerUser();
        $houseA = $this->createHouseFor($userA);
        $charA = $this->createHiredCharacter($houseA);

        $userB = $this->createPlayerUser();
        $houseB = $this->createHouseFor($userB);
        $charB = $this->createHiredCharacter($houseB);

        $start = app(StartBattleAction::class);
        $battleA = $start->execute($charA, BattleContext::forHouse($houseA, $userA), $this->enemyPreset());
        $battleB = $start->execute($charB, BattleContext::forHouse($houseB, $userB), $this->enemyPreset());

        $this->assertNotSame($battleA->id, $battleB->id);
    }
}
