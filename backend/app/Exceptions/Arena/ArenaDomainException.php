<?php

namespace App\Exceptions\Arena;

use RuntimeException;

/**
 * v1.1 アリーナドメインロジック全般の基底例外。
 *
 * コントローラ層では 422 に変換して返す想定。
 */
abstract class ArenaDomainException extends RuntimeException
{
}
