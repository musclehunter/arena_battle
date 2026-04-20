<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 成長プリセット。
 * 10 レベル分の上昇値(STR/VIT/DEX/INT 増分)を JSON で持つ。
 *
 * v1.2.1: 各職に 5 ランク(easy/normal/hard/expert/master)のプリセットを用意し、
 *         10 Lv 消化時にランク抽選で次プリセットを決定する。
 *         key は "{job}_{rank}" 形式(例: warrior_easy)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_presets', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            // 職(warrior/rogue/mage/priest/enemy)
            $table->string('job');
            // ランク(easy/normal/hard/expert/master)
            $table->string('rank');
            // ランクの序列(1=easy .. 5=master)。抽選時に次/下位を算出するキャッシュ。
            $table->unsignedTinyInteger('rank_order');
            // list<array{str:int,vit:int,dex:int,int_stat:int}> (長さ 10)
            $table->json('increments');
            $table->timestamps();

            $table->index(['job', 'rank_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_presets');
    }
};
