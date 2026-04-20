<?php

namespace App\Actions\Battle;

use App\Enums\BattleActionType;
use App\Enums\BattleStatus;
use App\Enums\BattleWinner;
use App\Models\Battle;
use App\Models\BattleLog;
use App\Services\Arena\GuestContext;
use App\Services\Arena\HiringService;
use App\Services\Arena\JobSeekerBoard;
use App\Services\Arena\RewardDistributor;
use App\Services\Battle\BattleLogFactory;
use App\Services\Battle\DamageCalculator;
use App\Services\Battle\EnemyActionDecider;
use App\Services\Character\CharacterStats;
use App\Services\Character\LevelUpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 1ターン解決 (v1.1)。
 *
 * 責務:
 *   - 敵行動の決定
 *   - DamageCalculator によるターン解決
 *   - HP / ターン数 / ステータス更新
 *   - BattleLog 保存
 *   - 勝敗判定
 *   - action_token 再発行 / 決着時は無効化
 *   - 決着時: 報酬分配 / 求職者ボード破棄 / ゲスト自動解雇
 */
final class ResolveTurnAction
{
    public function __construct(
        private readonly EnemyActionDecider $enemyActionDecider,
        private readonly DamageCalculator $damageCalculator,
        private readonly BattleLogFactory $logFactory,
        private readonly RewardDistributor $rewardDistributor,
        private readonly HiringService $hiringService,
        private readonly GuestContext $guestContext,
        private readonly JobSeekerBoard $jobSeekerBoard,
    ) {
    }

    public function execute(Battle $battle, BattleActionType $playerAction): Battle
    {
        $battle = DB::transaction(function () use ($battle, $playerAction): Battle {
            // 行ロックで二重解決を防ぐ
            $battle = Battle::query()->lockForUpdate()->findOrFail($battle->id);

            $enemyAction = $this->enemyActionDecider->decide($battle);

            // ATK/DEF は都度派生計算(Lvup はバトル終了後のみ発生するので battle 中不変)。
            $battle->loadMissing(['playerCharacter', 'enemyPreset']);
            $playerCharacter = $battle->playerCharacter;
            $enemyPreset = $battle->enemyPreset;
            $playerStats = $playerCharacter
                ? CharacterStats::forEntity($playerCharacter)
                : ['atk' => 0, 'def' => 0];
            $enemyStats = $enemyPreset
                ? CharacterStats::forPreset($enemyPreset)
                : ['atk' => 0, 'def' => 0];

            $resolution = $this->damageCalculator->resolve(
                player: $playerAction,
                enemy: $enemyAction,
                playerAtk: (int) $playerStats['atk'],
                playerDef: (int) $playerStats['def'],
                enemyAtk: (int) $enemyStats['atk'],
                enemyDef: (int) $enemyStats['def'],
            );

            $playerHpAfter = max(0, $battle->player_hp - $resolution->enemyDamageToPlayer);
            $enemyHpAfter = max(0, $battle->enemy_hp - $resolution->playerDamageToEnemy);

            $winner = $this->judgeWinner($playerHpAfter, $enemyHpAfter);
            $isFinished = $winner !== null;

            $summary = $this->logFactory->buildSummary(
                turnNumber: $battle->turn_number,
                playerAction: $playerAction,
                enemyAction: $enemyAction,
                resolution: $resolution,
                playerHpAfter: $playerHpAfter,
                enemyHpAfter: $enemyHpAfter,
                winner: $winner,
            );

            BattleLog::create([
                'battle_id' => $battle->id,
                'turn_number' => $battle->turn_number,
                'player_action' => $playerAction,
                'enemy_action' => $enemyAction,
                'player_damage_to_enemy' => $resolution->playerDamageToEnemy,
                'enemy_damage_to_player' => $resolution->enemyDamageToPlayer,
                'player_hp_after' => $playerHpAfter,
                'enemy_hp_after' => $enemyHpAfter,
                'summary_text' => $summary,
            ]);

            $battle->fill([
                'player_hp' => $playerHpAfter,
                'enemy_hp' => $enemyHpAfter,
                'turn_number' => $isFinished ? $battle->turn_number : $battle->turn_number + 1,
                'status' => $isFinished ? BattleStatus::Finished : BattleStatus::InProgress,
                'winner' => $winner,
                'ended_at' => $isFinished ? now() : null,
                'action_token' => $isFinished ? null : Str::random(24),
            ]);

            // 決着時の副作用は commit 前にここで処理(報酬の更新も同一トランザクション内)
            if ($isFinished) {
                $this->rewardDistributor->distribute($battle);
                $this->grantExpIfVictory($battle);
            }

            $battle->save();

            return $battle;
        });

        // トランザクション外で実行すべきゲスト/セッション連携
        if ($battle->status === BattleStatus::Finished) {
            $this->finalizePostBattle($battle);
        }

        return $battle->load(['playerCharacter.preset', 'enemyPreset', 'logs']);
    }

    /**
     * 決着後のセッション / 求職者ボード連携。
     *   - ゲスト雇用バトルならキャラを求職者プールへ戻し、セッションの hired_character_id をクリア
     *   - 求職者ボードを破棄(次回表示で再抽選)
     */
    private function finalizePostBattle(Battle $battle): void
    {
        if ($battle->isGuestBattle()) {
            $this->hiringService->autoReleaseAfterGuestBattle($battle);
            $this->guestContext->setHiredCharacter(null);
        }

        $this->jobSeekerBoard->invalidate();
    }

    /**
     * v1.2: プレイヤー勝利時、敵の base_level に応じて EXP を付与し Lvup 処理。
     * 引き分け・敗北では EXP 付与なし。
     */
    private function grantExpIfVictory(Battle $battle): void
    {
        if ($battle->winner !== BattleWinner::Player) {
            return;
        }

        $battle->loadMissing(['playerCharacter', 'enemyPreset']);
        $character = $battle->playerCharacter;
        $enemyPreset = $battle->enemyPreset;
        if ($character === null || $enemyPreset === null) {
            return;
        }

        $enemyLevel = (int) ($enemyPreset->base_level ?? 1);
        $reward = LevelUpService::rewardExpFromEnemyLevel($enemyLevel);
        LevelUpService::grantExp($character, $reward);
    }

    private function judgeWinner(int $playerHp, int $enemyHp): ?BattleWinner
    {
        $playerDown = $playerHp <= 0;
        $enemyDown = $enemyHp <= 0;

        return match (true) {
            $playerDown && $enemyDown => BattleWinner::Draw,
            $enemyDown => BattleWinner::Player,
            $playerDown => BattleWinner::Enemy,
            default => null,
        };
    }
}
