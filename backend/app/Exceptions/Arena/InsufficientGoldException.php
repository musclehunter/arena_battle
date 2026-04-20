<?php

namespace App\Exceptions\Arena;

class InsufficientGoldException extends ArenaDomainException
{
    public function __construct(int $required, int $available)
    {
        parent::__construct(sprintf(
            'ゴールドが不足しています。必要: %d / 所持: %d',
            $required,
            $available
        ));
    }
}
