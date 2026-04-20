<?php

namespace Tests\Feature;

use App\Exceptions\Arena\HireSlotFullException;
use App\Exceptions\Arena\InsufficientGoldException;
use App\Services\Arena\HiringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesArenaFixtures;
use Tests\TestCase;

final class HouseHiringTest extends TestCase
{
    use RefreshDatabase;
    use CreatesArenaFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedArenaBasics();
    }

    #[Test]
    public function 家門はhireCostを支払って求職者を雇用できる(): void
    {
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user, ['gold' => 500]);
        $character = $this->createJobSeeker(['hire_cost' => 150]);

        $response = $this->actingAs($user)->post(route('houses.hire'), [
            'character_id' => $character->id,
        ]);

        $response->assertRedirect(route('houses.mine'));
        $this->assertSame(350, $house->fresh()->gold);
        $this->assertSame($house->id, $character->fresh()->house_id);
        $this->assertNotNull($character->fresh()->hired_at);
    }

    #[Test]
    public function ゴールド不足で雇用するとドメイン例外が投げられる(): void
    {
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user, ['gold' => 50]);
        $character = $this->createJobSeeker(['hire_cost' => 100]);

        $this->expectException(InsufficientGoldException::class);

        app(HiringService::class)->hireByHouse($house, $character);
    }

    #[Test]
    public function 雇用枠が埋まっていると雇用できない(): void
    {
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user, ['gold' => 10000]);
        // Lv1 の枠 = 3
        $this->createHiredCharacter($house);
        $this->createHiredCharacter($house);
        $this->createHiredCharacter($house);

        $newSeeker = $this->createJobSeeker(['hire_cost' => 100]);

        $this->expectException(HireSlotFullException::class);
        app(HiringService::class)->hireByHouse($house->fresh(), $newSeeker);
    }

    #[Test]
    public function 解雇すると求職者プールに戻りゴールドは保持される(): void
    {
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user);
        $character = $this->createHiredCharacter($house, ['gold' => 234]);

        $response = $this->actingAs($user)
            ->post(route('houses.release', ['character' => $character->id]));

        $response->assertRedirect(route('houses.mine'));
        $fresh = $character->fresh();
        $this->assertNull($fresh->house_id);
        $this->assertNull($fresh->hired_at);
        $this->assertSame(234, $fresh->gold, 'キャラの所持金は持ち逃げ');
    }

    #[Test]
    public function 他家門のキャラは解雇できない(): void
    {
        $userA = $this->createPlayerUser();
        $houseA = $this->createHouseFor($userA);
        $userB = $this->createPlayerUser();
        $houseB = $this->createHouseFor($userB);
        $characterOfB = $this->createHiredCharacter($houseB);

        $response = $this->actingAs($userA)
            ->post(route('houses.release', ['character' => $characterOfB->id]));

        $response->assertForbidden();
        $this->assertSame($houseB->id, $characterOfB->fresh()->house_id);
    }
}
