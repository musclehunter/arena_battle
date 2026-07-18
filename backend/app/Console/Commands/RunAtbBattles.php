<?php

namespace App\Console\Commands;

use App\Events\PartyBattleStateUpdated;
use App\Models\PartyBattle;
use App\Services\Battle\AtbBattleState;
use Illuminate\Console\Command;

class RunAtbBattles extends Command
{
    protected $signature = 'arena:atb-worker';
    protected $description = 'Run active arena battles using 100ms ATB ticks.';

    public function handle(AtbBattleState $states): int
    {
        $tick = (int) config('atb.tick_ms');
        $this->info('ATB worker started.');
        $log = fopen(storage_path('logs/atb-worker.log'), 'a');
        fwrite($log, "[".now()."] worker started tick={$tick}ms\n");
        while (true) {
            $started = hrtime(true);
            $battles = PartyBattle::query()->where('status', 'in_progress')->get();
            if ($battles->isEmpty()) {
                // idle
            } else {
                foreach ($battles as $battle) {
                    try {
                        $state = $states->tick($battle);
                        PartyBattleStateUpdated::dispatch($battle->id, $state);
                        fwrite($log, "[".now()."] tick battle={$battle->id} enemies[0].phase={$state['enemies'][0]['phase']} gauge={$state['enemies'][0]['gauge']} events=".count($state['events'])."\n");
                    } catch (\Throwable $e) {
                        fwrite($log, "[".now()."] ERROR battle={$battle->id}: {$e->getMessage()}\n");
                    }
                }
            }
            $elapsed = (int) ((hrtime(true) - $started) / 1_000_000);
            usleep(max(0, $tick - $elapsed) * 1000);
        }
    }
}
