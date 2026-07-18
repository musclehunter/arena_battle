<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('strategy');
            $table->string('risk');
            $table->string('status')->default('in_progress')->index();
            $table->string('winner')->nullable();
            $table->unsignedInteger('round')->default(0);
            $table->json('player_state');
            $table->json('enemy_state');
            $table->json('logs');
            $table->unsignedInteger('reward_gold')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_battles');
    }
};
