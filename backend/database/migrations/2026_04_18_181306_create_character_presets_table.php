<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * キャラクタープリセット(テンプレート)。
 *
 * v1.2: 旧 hp/attack/defense を廃し、基本ステータス (STR/VIT/DEX/INT) を保持。
 * HP/ATK/DEF は config('arena.derived_stats') に従って派生的に算出する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_presets', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');

            // 基本ステータス
            $table->unsignedInteger('base_str')->default(10);
            $table->unsignedInteger('base_vit')->default(10);
            $table->unsignedInteger('base_dex')->default(10);
            $table->unsignedInteger('base_int')->default(10);

            // このプリセットから生まれるキャラの初期 Lv と成長プリセット
            $table->unsignedInteger('base_level')->default(1);
            $table->string('growth_preset_key')->nullable(); // 敵プリセットは NULL でも可

            $table->string('ai_type')->nullable();
            $table->boolean('is_enemy')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_presets');
    }
};
