<?php

namespace App\Exceptions\Arena;

class CharacterNotHireableException extends ArenaDomainException
{
    public static function alreadyEmployed(): self
    {
        return new self('この戦士はすでに他の家門に所属しています。');
    }

    public static function guestAlreadyHiring(): self
    {
        return new self('すでに一時契約中の戦士がいます。');
    }

    public static function notOwnedByHouse(): self
    {
        return new self('この戦士はあなたの家門に所属していません。');
    }
}
