<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * characters にアイコン画像インデックスを追加する。
 *
 * icon_index: /images/characters/icons/400/{icon_key}_{icon_index}_400.png のように使用する。
 * 0〜8 の範囲でキャラクター生成時にランダム決定する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedTinyInteger('icon_index')->default(0)->after('hired_at');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('icon_index');
        });
    }
};
