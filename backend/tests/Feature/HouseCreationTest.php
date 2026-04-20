<?php

namespace Tests\Feature;

use App\Services\Arena\GuestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesArenaFixtures;
use Tests\TestCase;

final class HouseCreationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesArenaFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedArenaBasics();
    }

    #[Test]
    public function 認証ユーザーは家門を作成できる(): void
    {
        $user = $this->createPlayerUser();

        $response = $this->actingAs($user)->post(route('houses.store'), [
            'name' => 'ファルコン家',
        ]);

        $response->assertRedirect(route('houses.mine'));
        $this->assertDatabaseHas('houses', [
            'user_id' => $user->id,
            'name' => 'ファルコン家',
            'gold' => 1000,
            'level' => 1,
        ]);
    }

    #[Test]
    public function 家門作成時にゲストセッションのゴールドと雇用キャラは破棄される(): void
    {
        $user = $this->createPlayerUser();

        // ゲストセッション状態を模擬的に作る
        $guest = app(GuestContext::class);
        $guest->addGold(500); // 1000 base → 1500
        $guest->setHiredCharacter(999);

        $this->actingAs($user)->post(route('houses.store'), ['name' => 'X']);

        // 家門作成後、再度アクセス時は gold が初期値に戻っていること
        // (resetOnHouseCreation は gold と hired_character_id を forget する)
        $this->assertSame(1000, $guest->gold(), 'gold は初期値 1000 にリセット');
        $this->assertNull($guest->hiredCharacterId(), 'hired_character_id はクリア');
    }

    #[Test]
    public function すでに家門を持つユーザーは再作成できない(): void
    {
        $user = $this->createPlayerUser();
        $this->createHouseFor($user);

        $response = $this->actingAs($user)->post(route('houses.store'), [
            'name' => 'SecondHouse',
        ]);

        // FormRequest::authorize() が false を返すので 403
        $response->assertForbidden();
        $this->assertSame(1, $user->fresh()->house()->count());
    }

    #[Test]
    public function 家門名が空または長すぎるとバリデーションエラー(): void
    {
        $user = $this->createPlayerUser();

        $this->actingAs($user)->post(route('houses.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->actingAs($user)->post(route('houses.store'), ['name' => str_repeat('あ', 25)])
            ->assertSessionHasErrors('name');
    }

    #[Test]
    public function 未認証は家門作成ページにアクセスできない(): void
    {
        $this->get(route('houses.create'))->assertRedirect(route('login'));
        $this->post(route('houses.store'), ['name' => 'X'])->assertRedirect(route('login'));
    }
}
