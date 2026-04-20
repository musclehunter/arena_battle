<?php

namespace Tests\Feature;

use App\Actions\Battle\ResolveTurnAction;
use App\Enums\BattleActionType;
use App\Enums\BattleStatus;
use App\Models\Battle;
use App\Services\Arena\GuestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesArenaFixtures;
use Tests\TestCase;

final class GuestHireFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesArenaFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedArenaBasics();
    }

    #[Test]
    public function ゲスト雇用エンドポイントはバトルを自動開始する(): void
    {
        $character = $this->createJobSeeker(['hire_cost' => 100]);

        $response = $this->post(route('guest-hires.store'), [
            'character_id' => $character->id,
        ]);

        $battle = Battle::query()->latest('id')->first();
        $this->assertNotNull($battle);
        $response->assertRedirect(route('battles.show', $battle));

        $this->assertSame($character->id, $battle->player_character_id);
        $this->assertNull($battle->user_id);
        $this->assertNull($battle->house_id);
        $this->assertNotNull($battle->guest_session_id);
        $this->assertSame(BattleStatus::InProgress, $battle->status);

        // キャラはゲスト家門に所属している
        $this->assertSame((int) config('arena.guest_house_id'), $character->fresh()->house_id);
    }

    #[Test]
    public function ゲスト雇用は15倍の契約金でゲストゴールドから支払われる(): void
    {
        $character = $this->createJobSeeker(['hire_cost' => 100]);

        $this->post(route('guest-hires.store'), [
            'character_id' => $character->id,
        ]);

        // GuestContext は Request ライフサイクル内のセッションに依存するため、
        // 同じテストセッションで取り出す。
        $guest = app(GuestContext::class);
        // 初期 1000 - ceil(100 * 1.5) = 850
        $this->assertSame(850, $guest->gold());
    }

    #[Test]
    public function ゲスト雇用中に再度ゲスト雇用しようとすると拒否される(): void
    {
        $charA = $this->createJobSeeker();
        $charB = $this->createJobSeeker();

        // 1回目:成功 → バトル進行中
        $this->post(route('guest-hires.store'), ['character_id' => $charA->id])
            ->assertRedirect();

        // 2回目:拒否(ConcurrentBattleException 相当の validation error)
        $response = $this->post(route('guest-hires.store'), ['character_id' => $charB->id]);
        $response->assertSessionHasErrors();
    }

    #[Test]
    public function ゲストバトル終了時にキャラは求職者プールに自動復帰する(): void
    {
        $character = $this->createJobSeeker(['hire_cost' => 100]);

        $this->post(route('guest-hires.store'), ['character_id' => $character->id]);
        $battle = Battle::latest('id')->first();

        // 強制的に決着までターンを回す
        $resolver = app(ResolveTurnAction::class);
        $safety = 60;
        while ($battle->status === BattleStatus::InProgress && $safety-- > 0) {
            $battle = $resolver->execute($battle->fresh(), BattleActionType::Weak);
        }

        $this->assertSame(BattleStatus::Finished, $battle->status);

        $fresh = $character->fresh();
        $this->assertNull($fresh->house_id, 'キャラは求職者プールに戻る');
        $this->assertNull($fresh->hired_at);
    }
}
