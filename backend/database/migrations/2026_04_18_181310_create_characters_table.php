<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * キャラクター(雇用可能な個体)。
 *
 * v1.2: 基本ステ (STR/VIT/DEX/INT)、EXP、成長プリセット情報を保持。
 * HP_max / ATK / DEF は派生値で DB には保存しない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_preset_id')->constrained('character_presets')->restrictOnDelete();
            $table->string('name', 32);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('exp')->default(0);

            // 基本ステータス(Lvup するとここが増える)
            $table->unsignedInteger('str')->default(10);
            $table->unsignedInteger('vit')->default(10);
            $table->unsignedInteger('dex')->default(10);
            $table->unsignedInteger('int_stat')->default(10);

            // 成長プリセット追跡(index=9 を消化したら、抽選箱からランクを引いて次プリセットへ切替)
            $table->string('growth_preset_key')->nullable();
            $table->unsignedTinyInteger('growth_index')->default(0);
            // ランク抽選箱(ランク名の配列)。例: ["easy","easy","normal"]
            $table->json('growth_rank_box')->nullable();

            $table->unsignedInteger('hire_cost');
            $table->unsignedInteger('reward_share_bp');
            $table->unsignedInteger('gold')->default(50);

            // house_id: NULL=求職者 / guest_house_id=ゲスト雇用 / その他=家門雇用
            $table->foreignId('house_id')->nullable()->constrained('houses')->nullOnDelete();
            $table->timestamp('hired_at')->nullable();
            $table->timestamps();

            $table->index('character_preset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
