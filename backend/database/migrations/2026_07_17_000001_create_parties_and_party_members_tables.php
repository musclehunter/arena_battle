<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->string('name', 32)->default('第一遠征隊');
            $table->string('strategy')->default('balanced');
            $table->string('risk')->default('normal');
            $table->timestamps();

            $table->unique('house_id');
        });

        Schema::create('party_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->timestamps();

            $table->unique(['party_id', 'slot']);
            $table->unique(['party_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_members');
        Schema::dropIfExists('parties');
    }
};
