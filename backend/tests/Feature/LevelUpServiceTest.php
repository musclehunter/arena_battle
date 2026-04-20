<?php

namespace Tests\Feature;

use App\Models\GrowthPreset;
use App\Services\Character\GrowthRank;
use App\Services\Character\LevelUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesArenaFixtures;
use Tests\TestCase;

final class LevelUpServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesArenaFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedArenaBasics();
    }

    #[Test]
    public function 必要EXPは成長プリセットの上昇値合計から算出される(): void
    {
        $character = $this->createJobSeeker();
        $preset = GrowthPreset::where('key', $character->growth_preset_key)->firstOrFail();
        $inc = $preset->incrementAt(0);
        $expected = ($inc['str'] + $inc['vit'] + $inc['dex'] + $inc['int_stat']) * 10;

        $this->assertSame($expected, LevelUpService::requiredExpToNext($character->fresh()));
    }

    #[Test]
    public function EXPが足りるとレベルアップして基本ステが増える(): void
    {
        $character = $this->createJobSeeker();
        $preset = GrowthPreset::where('key', $character->growth_preset_key)->firstOrFail();
        $inc = $preset->incrementAt(0);

        $beforeStr = $character->str;
        $beforeVit = $character->vit;
        $required = LevelUpService::requiredExpToNext($character);

        $result = LevelUpService::grantExp($character, $required);

        $character->refresh();
        $this->assertSame(1, $result['levels_gained']);
        $this->assertSame(2, $character->level);
        $this->assertSame($beforeStr + $inc['str'], $character->str);
        $this->assertSame($beforeVit + $inc['vit'], $character->vit);
        $this->assertSame(1, $character->growth_index);
        $this->assertSame(0, $character->exp, '消費後は 0');
    }

    #[Test]
    public function 足りないEXPではレベルアップしない(): void
    {
        $character = $this->createJobSeeker();
        $result = LevelUpService::grantExp($character, 1);

        $character->refresh();
        $this->assertSame(0, $result['levels_gained']);
        $this->assertSame(1, $character->level);
        $this->assertSame(1, $character->exp);
    }

    #[Test]
    public function rewardExpFromEnemyLevelはLvに比例する(): void
    {
        $this->assertSame(10, LevelUpService::rewardExpFromEnemyLevel(1));
        $this->assertSame(30, LevelUpService::rewardExpFromEnemyLevel(3));
        $this->assertSame(0, LevelUpService::rewardExpFromEnemyLevel(0));
    }

    // ---- ランク抽選ロジック -----------------------------------------------

    #[Test]
    public function 初期抽選箱は現在ランク2個と次ランク1個になる(): void
    {
        $character = $this->createJobSeeker(); // warrior_normal
        $this->assertSame(['normal', 'normal', 'hard'], $character->growth_rank_box);
    }

    #[Test]
    public function 現在ランクを引くとランク維持され箱から1つ減る(): void
    {
        $character = $this->createJobSeeker();
        $character->growth_index = 10;
        $character->growth_rank_box = ['normal', 'normal', 'hard'];
        $character->save();

        $newKey = LevelUpService::advanceRank($character, forcedPick: 'normal');

        $this->assertSame('warrior_normal', $newKey);
        $this->assertSame(['normal', 'hard'], $character->growth_rank_box);
        $this->assertSame(0, $character->growth_index);
    }

    #[Test]
    public function 次ランクを引くとランクアップし下位と次が追加される(): void
    {
        $character = $this->createJobSeeker();
        $character->growth_index = 10;
        $character->growth_rank_box = ['normal', 'normal', 'hard'];
        $character->save();

        $newKey = LevelUpService::advanceRank($character, forcedPick: 'hard');

        // ランクアップ: 残り[normal,normal]をシフトアップ→[hard,hard]、+旧current(normal)+新next(expert)。
        $this->assertSame('warrior_hard', $newKey);
        $this->assertSame(['hard', 'hard', 'normal', 'expert'], $character->growth_rank_box);
    }

    #[Test]
    public function easyからランクアップするとnormalの下位easyが追加される(): void
    {
        $character = $this->createJobSeeker(['growth_preset_key' => 'warrior_easy']);
        $character->growth_index = 10;
        $character->growth_rank_box = ['easy', 'easy', 'normal'];
        $character->save();

        $newKey = LevelUpService::advanceRank($character, forcedPick: 'normal');

        // ランクアップ: 残り[easy,easy]をシフトアップ→[normal,normal]、+旧current(easy)+新next(hard)。
        $this->assertSame('warrior_normal', $newKey);
        $this->assertSame(['normal', 'normal', 'easy', 'hard'], $character->growth_rank_box);
    }

    #[Test]
    public function masterからeasyへのループではシフトでmasterがeasyに換わる(): void
    {
        // master の箱には next(master)=easy が入っている。
        // easy を引いたらランクアップ扱いだが、easy の下位は null → 追加は next(easy)=normal のみ
        $character = $this->createJobSeeker(['growth_preset_key' => 'warrior_master']);
        $character->growth_index = 10;
        $character->growth_rank_box = ['master', 'master', 'easy'];
        $character->save();

        LevelUpService::advanceRank($character, forcedPick: 'easy');
        // 残り[master,master] → シフトアップ(master→easy ループ) → [easy,easy]、+旧current(master)+新next(normal) = [easy, easy, master, normal]
        $this->assertSame(['easy', 'easy', 'master', 'normal'], $character->growth_rank_box);
    }

    #[Test]
    public function masterからランクアップするとeasyへループする(): void
    {
        $character = $this->createJobSeeker(['growth_preset_key' => 'warrior_master']);
        $character->growth_index = 10;
        // master の初期箱 = [master, master, easy] (next(master) = easy)
        $character->growth_rank_box = ['master', 'master', 'easy'];
        $character->save();

        $newKey = LevelUpService::advanceRank($character, forcedPick: 'easy');

        // ランクアップ (master→easy ループ): 残り[master,master]をシフトアップするとmasterの次=easyになるので[easy,easy]。
        // + 旧current(master)×1 + 新next(normal)×1 = [easy, easy, master, normal]
        $this->assertSame('warrior_easy', $newKey);
        $this->assertSame(['easy', 'easy', 'master', 'normal'], $character->growth_rank_box);
    }

    #[Test]
    public function 下位ランクを引くとランクダウンし箱はシフトダウンされる(): void
    {
        $character = $this->createJobSeeker(['growth_preset_key' => 'warrior_hard']);
        $character->growth_index = 10;
        // ランクアップ直後の想定: hard 現在、箱 = [hard,hard,normal,expert]
        $character->growth_rank_box = ['hard', 'hard', 'normal', 'expert'];
        $character->save();

        $newKey = LevelUpService::advanceRank($character, forcedPick: 'normal');

        // ランクダウン: 残り[hard,hard,expert]をシフトダウン→[normal,normal,hard]のみ(追加なし)。
        $this->assertSame('warrior_normal', $newKey);
        $this->assertSame(['normal', 'normal', 'hard'], $character->growth_rank_box);
    }

    #[Test]
    public function 十レベル消化で抽選が走り別プリセットに切り替わりうる(): void
    {
        $character = $this->createJobSeeker();
        // 毎回必要 EXP ぴったりを投入して 10 回 Lvup
        for ($i = 0; $i < 10; $i++) {
            $fresh = $character->fresh();
            $required = LevelUpService::requiredExpToNext($fresh);
            LevelUpService::grantExp($fresh, $required);
        }

        $fresh = $character->fresh();
        $this->assertSame(11, $fresh->level);
        $this->assertSame(0, $fresh->growth_index);

        // 抽選結果はランダムだが、warrior 系のランクに切り替わっている
        $rank = GrowthRank::rankFromKey($fresh->growth_preset_key);
        $job = GrowthRank::jobFromKey($fresh->growth_preset_key);
        $this->assertSame('warrior', $job);
        $this->assertTrue(GrowthRank::exists($rank ?? ''), "rank={$rank} is invalid");
    }
}
