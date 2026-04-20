<?php

namespace App\Services\Battle;

/**
 * 1ターンの解決後、片側(自分視点)がどういう結末だったかを表す。
 */
enum TurnOutcome: string
{
    /** 通常攻撃が相手に通った(弱攻撃、または非カウンター相手への強攻撃) */
    case Attacked = 'attacked';

    /** 強攻撃を仕掛けたが相手のカウンターで無効化された */
    case AttackNullified = 'attack_nullified';

    /** カウンターを仕掛け、相手の強攻撃を弾いてダメージを与えた */
    case CounterSucceeded = 'counter_succeeded';

    /** カウンターを仕掛けたが相手が弱攻撃だったため失敗し、弱攻撃を受けた */
    case CounterFailed = 'counter_failed';

    /** 両者カウンターでかち合って双方不発 */
    case CounterNullified = 'counter_nullified';
}
