<?php

namespace App\Exceptions\Arena;

class HireSlotFullException extends ArenaDomainException
{
    public function __construct(int $slots)
    {
        parent::__construct(sprintf('契約枠が上限(%d)に達しています。', $slots));
    }
}
