<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category');
            $table->timestamps();
        });

        Schema::create('house_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
            $table->unique(['house_id', 'item_id']);
        });

        Schema::create('production_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('activity_key');
            $table->string('status')->default('in_progress')->index();
            $table->json('output');
            $table->unsignedInteger('gold_cost');
            $table->timestamp('started_at');
            $table->timestamp('completes_at');
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();
            $table->index(['house_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_jobs');
        Schema::dropIfExists('house_inventories');
        Schema::dropIfExists('items');
    }
};
