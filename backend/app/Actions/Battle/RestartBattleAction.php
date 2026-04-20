<?php

namespace App\Actions\Battle;

use App\Models\Battle;
use App\Services\Battle\BattleContext;

/**
 * 既存バトルの player_character と enemy_preset を引き継いで新しいバトルを作成する。
 *
 * 元バトルはそのまま保持(finished 状態)。所有者コンテキストも引き継ぐ。
 * 同時進行バトル制約は StartBattleAction 側でチェックされる。
 */
final class RestartBattleAction
{
    public function __construct(
        private readonly StartBattleAction $startBattleAction,
    ) {
    }

    public function execute(Battle $previous): Battle
    {
        $previous->loadMissing(['playerCharacter.preset', 'enemyPreset']);

        $context = $previous->house_id !== null
            ? BattleContext::forHouse($previous->house)
            : BattleContext::forGuest((string) $previous->guest_session_id);

        return $this->startBattleAction->execute(
            character: $previous->playerCharacter,
            context: $context,
            enemyPreset: $previous->enemyPreset,
        );
    }
}
