<?php

namespace App\Enums;

enum BattleActionType: string
{
    case Weak = 'weak';
    case Strong = 'strong';
    case Counter = 'counter';
}
