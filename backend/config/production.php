<?php

return [
    'duration_seconds' => 5,
    'activities' => [
        'mining' => ['name' => '採掘', 'category' => '採取', 'gold_cost' => 50, 'output' => 'iron_ore', 'output_quantity' => 3, 'inputs' => [], 'success_rate' => 100],
        'logging' => ['name' => '伐採', 'category' => '採取', 'gold_cost' => 40, 'output' => 'wood', 'output_quantity' => 3, 'inputs' => [], 'success_rate' => 100],
        'skinning' => ['name' => '皮革採取', 'category' => '採取', 'gold_cost' => 45, 'output' => 'leather', 'output_quantity' => 3, 'inputs' => [], 'success_rate' => 100],
        'gathering' => ['name' => '採集', 'category' => '採取', 'gold_cost' => 35, 'output' => 'herb', 'output_quantity' => 3, 'inputs' => [], 'success_rate' => 100],
        'weapon_forging' => ['name' => '武器鍛造', 'category' => '製作', 'gold_cost' => 120, 'output' => 'iron_sword', 'output_quantity' => 1, 'inputs' => ['iron_ore' => 3, 'wood' => 1], 'success_rate' => 100],
        'armor_forging' => ['name' => '防具鍛造', 'category' => '製作', 'gold_cost' => 120, 'output' => 'leather_armor', 'output_quantity' => 1, 'inputs' => ['leather' => 4, 'iron_ore' => 1], 'success_rate' => 100],
        'potion_brewing' => ['name' => '薬調合', 'category' => '製作', 'gold_cost' => 60, 'output' => 'healing_potion', 'output_quantity' => 1, 'inputs' => ['herb' => 2], 'success_rate' => 100],
    ],
    'items' => [
        'iron_ore' => ['name' => '鉄鉱石', 'category' => 'material'],
        'wood' => ['name' => '木材', 'category' => 'material'],
        'leather' => ['name' => '皮革', 'category' => 'material'],
        'herb' => ['name' => '薬草', 'category' => 'material'],
        'iron_sword' => ['name' => '鉄の剣', 'category' => 'weapon'],
        'leather_armor' => ['name' => '革の鎧', 'category' => 'armor'],
        'healing_potion' => ['name' => '回復薬', 'category' => 'consumable'],
    ],
];
