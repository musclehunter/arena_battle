<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Character;
$chars = Character::whereIn('id', [1, 2, 3, 4, 5])->get(['id', 'name', 'reward_share_bp', 'gold']);
foreach ($chars as $c) {
    echo "{$c->id} {$c->name} bp={$c->reward_share_bp} gold={$c->gold}\n";
}

echo "\n--- Simulation (totalGold=200, 3 alive) ---\n";
$totalGold = 200;
$aliveCount = 3;
$shareBpMin = 100;
$shareBpMax = 9000;
$totalToChar = 0;
foreach ($chars as $c) {
    $bp = max($shareBpMin, min((int) $c->reward_share_bp, $shareBpMax));
    $soloShare = (int) floor($totalGold * $bp / 10000);
    $ptShare = (int) floor($soloShare / $aliveCount);
    echo "{$c->name}: bp={$bp} solo={$soloShare}G pt_share={$ptShare}G\n";
    $totalToChar += $ptShare;
}
$goldToHouse = max(0, $totalGold - $totalToChar);
echo "House: {$goldToHouse}G\n";
echo "Total to chars: {$totalToChar}G\n";
