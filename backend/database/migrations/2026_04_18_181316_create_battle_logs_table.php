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
        Schema::create('battle_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('turn_number');
            $table->string('player_action')->nullable();
            $table->string('enemy_action')->nullable();
            $table->integer('player_damage_to_enemy')->default(0);
            $table->integer('enemy_damage_to_player')->default(0);
            $table->unsignedInteger('player_hp_after');
            $table->unsignedInteger('enemy_hp_after');
            $table->text('summary_text');
            $table->timestamps();

            $table->index(['battle_id', 'turn_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('battle_logs');
    }
};
