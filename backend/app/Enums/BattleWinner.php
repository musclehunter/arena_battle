<?php

namespace App\Enums;

enum BattleWinner: string
{
    case Player = 'player';
    case Enemy = 'enemy';
    case Draw = 'draw';
}
