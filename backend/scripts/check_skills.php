<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Skill;
echo "Total skills: " . Skill::count() . "\n";
foreach (Skill::orderBy('job')->orderBy('unlock_level')->get() as $s) {
    echo "{$s->job} Lv{$s->unlock_level} {$s->name} ({$s->key}) sp={$s->sp_cost} power={$s->power}\n";
}
