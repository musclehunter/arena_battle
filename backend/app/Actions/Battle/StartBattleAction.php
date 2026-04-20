<?php

namespace App\Actions\Battle;

use App\Enums\BattleStatus;
use App\Exceptions\Arena\ConcurrentBattleException;
use App\Models\Battle;
use App\Models\BattleLog;
use App\Models\Character;
use App\Models\CharacterPreset;
use App\Services\Battle\BattleContext;
use App\Services\Character\CharacterStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 新しいバトルを開始する (v1.1)。
 *
 * 責務:
 *  - 所有者 (house / guest) の同時進行バトル重複チェック
 *  - プレイヤーキャラ(Character) と敵 CharacterPreset の決定
 *  - battles レコード作成(HP は preset からコピー)
 *  - 初期ログ作成
 */
final class StartBattleAction
{
    public function __construct() {}

    /**
     * @param  Character               $character      プレイヤーキャラ(家門/ゲスト雇用中)
     * @param  BattleContext           $context        所有者コンテキスト
     * @param  CharacterPreset|null    $enemyPreset    敵プリセット (null ならランダム選択)
     */
    public function execute(
        Character $character,
        BattleContext $context,
        ?CharacterPreset $enemyPreset = null,
    ): Battle {
        return DB::transaction(function () use ($character, $context, $enemyPreset): Battle {
            $this->guardNoConcurrentBattle($context);

            $character->loadMissing('preset');
            $playerPreset = $character->preset;
            if ($playerPreset === null) {
                throw new RuntimeException("Character id={$character->id} has no preset.");
            }

            $enemyPreset ??= $this->pickRandomEnemyPreset();

            // v1.2: HP は STR/VIT 等から派生的に算出する。
            // プレイヤーは character の現在ステ、敵はプリセットの base_* を使用。
            $playerDerived = CharacterStats::forEntity($character);
            $enemyDerived = CharacterStats::forPreset($enemyPreset);

            $battle = Battle::create([
                'user_id' => $context->userId,
                'house_id' => $context->houseId,
                'guest_session_id' => $context->guestSessionId,
                'player_character_id' => $character->id,
                'enemy_preset_id' => $enemyPreset->id,
                'status' => BattleStatus::InProgress,
                'winner' => null,
                'turn_number' => 1,
                'player_hp' => $playerDerived['hp'],
                'enemy_hp' => $enemyDerived['hp'],
                'action_token' => Str::random(24),
                'started_at' => now(),
            ]);

            BattleLog::create([
                'battle_id' => $battle->id,
                'turn_number' => 0,
                'player_action' => null,
                'enemy_action' => null,
                'player_damage_to_enemy' => 0,
                'enemy_damage_to_player' => 0,
                'player_hp_after' => $battle->player_hp,
                'enemy_hp_after' => $battle->enemy_hp,
                'summary_text' => "対戦を開始しました。\n行動を選択してください。",
            ]);

            return $battle->load(['playerCharacter.preset', 'enemyPreset', 'logs']);
        });
    }

    /**
     * 同一 house / guest_session_id で in_progress のバトルがないこと。
     */
    private function guardNoConcurrentBattle(BattleContext $context): void
    {
        $query = Battle::query()->where('status', BattleStatus::InProgress->value);

        if ($context->houseId !== null) {
            $query->where('house_id', $context->houseId);
        } else {
            $query->where('guest_session_id', $context->guestSessionId);
        }

        $existingId = (int) $query->value('id');
        if ($existingId > 0) {
            throw ConcurrentBattleException::forBattle($existingId);
        }
    }

    private function pickRandomEnemyPreset(): CharacterPreset
    {
        $preset = CharacterPreset::query()
            ->where('is_enemy', true)
            ->inRandomOrder()
            ->first();

        if ($preset === null) {
            throw new RuntimeException('敵 CharacterPreset が存在しません。seeder を確認してください。');
        }

        return $preset;
    }
}
