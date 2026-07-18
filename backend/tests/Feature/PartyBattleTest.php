<?php

namespace Tests\Feature;

use App\Actions\PartyBattle\RunPartyBattleRoundAction;
use App\Actions\PartyBattle\StartPartyBattleAction;
use App\Models\Party;
use App\Models\Skill;
use App\Services\Battle\AtbBattleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesArenaFixtures;
use Tests\TestCase;

final class PartyBattleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesArenaFixtures;

    #[Test]
    public function 五人編成で遠征を開始し自動戦闘を完了できる(): void
    {
        $this->seedArenaBasics();
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user);
        $party = Party::create(['house_id' => $house->id, 'name' => '試験遠征隊']);
        foreach (range(0, 4) as $slot) {
            $party->members()->create(['character_id' => $this->createHiredCharacter($house)->id, 'slot' => $slot]);
        }

        $battle = app(StartPartyBattleAction::class)->execute($party);
        $this->assertCount(5, $battle->player_state);
        $this->assertCount(5, $battle->enemy_state);

        foreach (range(1, 100) as $round) {
            $battle = app(RunPartyBattleRoundAction::class)->execute($battle);
            if ($battle->status === 'finished') break;
        }

        $this->assertSame('finished', $battle->fresh()->status);
        $this->assertNotNull($battle->fresh()->winner);
    }

    #[Test]
    public function 習得済みスキルを戦闘中に予約できる(): void
    {
        $this->seedArenaBasics();
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user);
        $party = Party::create(['house_id' => $house->id, 'name' => '試験遠征隊']);
        $characters = collect(range(0, 4))->map(function (int $slot) use ($house, $party) {
            $character = $this->createHiredCharacter($house);
            $party->members()->create(['character_id' => $character->id, 'slot' => $slot]);
            return $character;
        });
        $skill = Skill::create(['key' => 'test_slash', 'name' => '試験斬り', 'job' => 'warrior', 'line' => '近接攻撃']);
        $characters->first()->skills()->attach($skill, ['learned_at' => now()]);

        $battle = app(StartPartyBattleAction::class)->execute($party);
        $state = app(AtbBattleState::class);
        $state->initialize($battle);
        $reserved = $state->reserve($battle, $characters->first()->id, $skill->key);

        $this->assertSame('casting', $reserved['players'][0]['phase']);
        $this->assertSame($skill->key, $reserved['players'][0]['reserved_skill']);
    }

    #[Test]
    public function 勝利時のキャラ取り分が戦闘結果に保存される(): void
    {
        $this->seedArenaBasics();
        $user = $this->createPlayerUser();
        $house = $this->createHouseFor($user);
        $party = Party::create(['house_id' => $house->id, 'name' => '試験遠征隊']);
        foreach (range(0, 4) as $slot) {
            $party->members()->create(['character_id' => $this->createHiredCharacter($house)->id, 'slot' => $slot]);
        }

        $battle = app(StartPartyBattleAction::class)->execute($party);
        $state = app(AtbBattleState::class);
        $current = $state->initialize($battle);
        foreach ($current['enemies'] as &$enemy) {
            $enemy['hp'] = 0;
        }
        unset($enemy);
        $state->put($battle, $current);
        $result = $state->tick($battle);

        $this->assertSame('finished', $battle->fresh()->status);
        $this->assertSame(200, $battle->fresh()->reward_gold);
        $this->assertSame(140, $result['reward_gold_to_house']);
        $this->assertSame(60, $result['reward_gold_to_characters']);
        $this->assertSame([12, 12, 12, 12, 12], array_column($battle->fresh()->player_state, 'gold_gained'));
    }
}
