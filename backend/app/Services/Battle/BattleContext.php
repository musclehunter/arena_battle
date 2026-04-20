<?php

namespace App\Services\Battle;

use App\Models\House;
use App\Models\User;
use InvalidArgumentException;

/**
 * バトル所有者コンテキスト (不変)。
 *
 * - house: 家門プレイヤーのバトル (user_id も同時に入る)
 * - guest: 未ログインゲストのバトル (guest_session_id のみ)
 *
 * 1 バトルに対して「家門 or ゲスト」の排他。
 */
final readonly class BattleContext
{
    private function __construct(
        public ?int $userId,
        public ?int $houseId,
        public ?string $guestSessionId,
    ) {
        if ($houseId === null && $guestSessionId === null) {
            throw new InvalidArgumentException('BattleContext requires either house or guest session.');
        }
        if ($houseId !== null && $guestSessionId !== null) {
            throw new InvalidArgumentException('BattleContext cannot be both house and guest.');
        }
    }

    public static function forHouse(House $house, ?User $user = null): self
    {
        return new self(
            userId: $user?->id ?? $house->user_id,
            houseId: (int) $house->id,
            guestSessionId: null,
        );
    }

    public static function forGuest(string $sessionId): self
    {
        return new self(userId: null, houseId: null, guestSessionId: $sessionId);
    }

    public function isGuest(): bool
    {
        return $this->guestSessionId !== null;
    }
}
