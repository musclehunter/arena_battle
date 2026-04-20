<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    guest: {
        type: Object,
        required: true, // { gold, hired_character_id }
    },
    active_battle_id: {
        type: [Number, null],
        default: null,
    },
    is_authenticated: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <Head title="1v1 Arena" />

    <div class="min-h-screen bg-gray-900 text-gray-100 p-6 flex items-center justify-center">
        <div class="w-full max-w-xl bg-gray-800 rounded-2xl shadow-2xl p-8 space-y-6">
            <header class="space-y-2 text-center">
                <h1 class="text-3xl font-bold tracking-wide">1v1 Arena</h1>
                <p class="text-sm text-gray-400">雇用して戦う。勝って稼ぐ。</p>
            </header>

            <!-- ゲスト情報 -->
            <section class="bg-gray-900/60 rounded-xl p-5 text-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-400">ゲスト所持金</span>
                    <span class="text-lg font-bold text-amber-300">{{ guest.gold }} G</span>
                </div>
                <div v-if="guest.hired_character_id" class="text-xs text-indigo-300">
                    ゲスト雇用中(ID: {{ guest.hired_character_id }})
                </div>
                <div v-else class="text-xs text-gray-500">
                    まだ誰も雇用していません
                </div>
            </section>

            <!-- 進行中バトルリンク -->
            <div v-if="active_battle_id" class="bg-amber-900/30 border border-amber-700 rounded-xl p-4 text-center text-sm">
                進行中のバトルがあります。
                <Link :href="route('battles.show', { battle: active_battle_id })"
                      class="underline text-amber-300 font-semibold ml-1">続きから再開</Link>
            </div>

            <!-- 行動ボタン -->
            <div class="space-y-3">
                <Link :href="route('job-seekers.index')"
                      class="block w-full py-3 rounded-xl bg-indigo-500 hover:bg-indigo-400 transition font-semibold tracking-wide text-center">
                    求職者を見る
                </Link>

                <template v-if="is_authenticated">
                    <Link :href="route('houses.create')"
                          class="block w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 transition font-semibold tracking-wide text-center">
                        家門を作成する
                    </Link>
                </template>
                <template v-else>
                    <div class="flex gap-2">
                        <Link :href="route('login')"
                              class="flex-1 py-3 rounded-xl bg-gray-700 hover:bg-gray-600 transition font-semibold text-center text-sm">
                            ログイン
                        </Link>
                        <Link :href="route('register')"
                              class="flex-1 py-3 rounded-xl bg-gray-700 hover:bg-gray-600 transition font-semibold text-center text-sm">
                            新規登録
                        </Link>
                    </div>
                    <p class="text-xs text-gray-500 text-center">ログインすると家門を作って継続雇用できます</p>
                </template>
            </div>

            <section class="text-xs text-gray-500 leading-relaxed border-t border-gray-700 pt-4">
                <p class="font-semibold text-gray-400 mb-1">ルール概要</p>
                <ul class="list-disc list-inside space-y-0.5">
                    <li>求職者から雇用してバトル開始</li>
                    <li>勝利で 200G 獲得(キャラと分配)</li>
                    <li>ゲスト雇用はコスト 1.5 倍、バトル終了で自動解雇</li>
                </ul>
            </section>
        </div>
    </div>
</template>
