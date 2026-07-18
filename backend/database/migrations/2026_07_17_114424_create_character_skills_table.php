<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->timestamp('learned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['character_id', 'skill_id']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('skill_points')->default(0)->after('exp');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('skill_points');
        });
        Schema::dropIfExists('character_skills');
    }
};
