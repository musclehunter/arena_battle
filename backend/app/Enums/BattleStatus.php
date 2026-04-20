<?php

namespace App\Enums;

enum BattleStatus: string
{
    case InProgress = 'in_progress';
    case Finished = 'finished';
}
