<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('job');
            $table->string('line');
            $table->text('description')->nullable();
            $table->integer('sp_cost')->default(1);
            $table->integer('unlock_level')->default(1);
            $table->string('scales_with')->nullable();
            $table->float('power')->default(1.0);
            $table->integer('cast_gauge')->default(5000);
            $table->integer('cooldown_gauge')->default(2000);
            $table->string('element')->nullable();
            $table->string('target_type')->default('enemy_single');
            $table->integer('target_count')->default(1);
            $table->string('effect_type')->nullable();
            $table->integer('effect_power')->default(0);
            $table->integer('effect_duration')->default(0);
            $table->boolean('is_passive')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
