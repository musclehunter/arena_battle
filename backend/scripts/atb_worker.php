<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Events\PartyBattleStateUpdated;
use App\Models\PartyBattle;
use App\Services\Battle\AtbBattleState;

$states = app(AtbBattleState::class);
$tick = (int) config('atb.tick_ms');
$log = fopen('/var/www/html/storage/logs/atb-worker.log', 'a');
function wlog($msg) {
    global $log;
    $line = "[" . date('Y-m-d H:i:s') . "] $msg\n";
    fwrite($log, $line);
    echo $line;
    @ob_flush();
    @flush();
}
wlog("ATB worker started tick={$tick}ms");

while (true) {
    $started = hrtime(true);
    $battles = PartyBattle::query()->where('status', 'in_progress')->get();
    foreach ($battles as $battle) {
        try {
            $before = $states->get($battle);
            $beforeEventCount = count($before['events'] ?? []);
            $beforeStatus = $before['status'] ?? 'in_progress';
            $state = $states->tick($battle);
            $afterEventCount = count($state['events'] ?? []);
            $afterStatus = $state['status'] ?? 'in_progress';
            if ($afterEventCount > $beforeEventCount || $afterStatus !== $beforeStatus || $before['updated_at'] !== $state['updated_at']) {
                PartyBattleStateUpdated::dispatch($battle->id, $state);
            }
            if ($afterEventCount > $beforeEventCount || $afterStatus !== $beforeStatus) {
                wlog("battle={$battle->id} status={$afterStatus} events={$afterEventCount}");
            }
        } catch (\Throwable $e) {
            wlog("ERROR battle={$battle->id}: {$e->getMessage()}");
        }
    }
    $elapsed = (int) ((hrtime(true) - $started) / 1_000_000);
    usleep(max(0, $tick - $elapsed) * 1000);
}
