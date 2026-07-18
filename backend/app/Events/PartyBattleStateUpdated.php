<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PartyBattleStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly int $battleId, public readonly array $state)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("party-battle.{$this->battleId}")];
    }

    public function broadcastAs(): string
    {
        return 'state.updated';
    }

    public function broadcastWith(): array
    {
        return ['state' => [...$this->state, 'events' => array_slice($this->state['events'] ?? [], -10)]];
    }
}
