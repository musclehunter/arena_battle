<?php

namespace App\Exceptions\Arena;

class CharacterNotHireableException extends ArenaDomainException
{
    public static function alreadyEmployed(): self
    {
        return new self('このキャラクターは既に雇用されています。');
    }

    public static function guestAlreadyHiring(): self
    {
        return new self('既にゲスト雇用中のキャラクターがいます。');
    }

    public static function notOwnedByHouse(): self
    {
        return new self('このキャラクターは自家門に所属していません。');
    }
}
