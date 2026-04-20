<?php

namespace App\Exceptions\Arena;

class HireSlotFullException extends ArenaDomainException
{
    public function __construct(int $slots)
    {
        parent::__construct(sprintf('雇用枠が上限(%d)に達しています。', $slots));
    }
}
