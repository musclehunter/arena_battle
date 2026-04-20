<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('battles', function (Blueprint $table) {
            $table->id();
            // 所有者識別: user / guest どちらかが入る
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('house_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_session_id', 64)->nullable()->index();

            // プレイヤーはユーザー作成キャラ。敵は引き続き preset を使う
            $table->foreignId('player_character_id')->constrained('characters')->restrictOnDelete();
            $table->foreignId('enemy_preset_id')->constrained('character_presets')->restrictOnDelete();

            $table->string('status')->default('in_progress')->index();
            $table->string('winner')->nullable();
            $table->unsignedInteger('turn_number')->default(1);
            $table->unsignedInteger('player_hp');
            $table->unsignedInteger('enemy_hp');
            $table->string('action_token', 32)->nullable()->index();

            // 勝利時のみ値が入る報酬記録
            $table->unsignedInteger('reward_gold_total')->nullable();
            $table->unsignedInteger('reward_gold_to_character')->nullable();
            $table->unsignedInteger('reward_gold_to_house')->nullable();

            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('battles');
    }
};
