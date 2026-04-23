<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * character_presets にアイコン画像キーを追加する。
 *
 * icon_key: {VITE_ASSET_BASE_URL}/characters/icons/400/{icon_key}_{icon_index}_400.png のように使用する。
 * NULL の場合は画像なし(枠のみ表示)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_presets', function (Blueprint $table) {
            $table->string('icon_key')->nullable()->after('is_enemy');
        });
    }

    public function down(): void
    {
        Schema::table('character_presets', function (Blueprint $table) {
            $table->dropColumn('icon_key');
        });
    }
};
