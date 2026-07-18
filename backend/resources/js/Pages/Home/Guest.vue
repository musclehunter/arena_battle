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
    <Head title="Arena Battle" />

    <div class="min-h-screen bg-gray-900 text-gray-100 relative overflow-hidden">
        <!-- 背景：生成した画像を使用。読み込み失敗時はグレー背景+グラデーションで自然にフォールバック -->
        <div class="absolute inset-0 bg-[url('/images/backgrounds/title.png')] bg-cover bg-center opacity-30"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/80 to-gray-900/40"></div>

        <div class="relative z-10 min-h-screen flex items-center justify-center p-6">
            <div class="w-full max-w-xl bg-gray-900/80 backdrop-blur rounded-2xl shadow-2xl border border-gray-700/50 p-8 space-y-6">
                <header class="space-y-2 text-center">
                    <h1 class="text-4xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-amber-300 to-rose-400">
                        Arena Battle
                    </h1>
                    <p class="text-sm text-gray-400">盟約を結び、戦果を刻む。螺旋の世界に、あなたの家門を築け。</p>
                </header>

                <!-- ゲスト情報 -->
                <section class="bg-gray-800/60 rounded-xl p-5 border border-gray-700/50">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-gray-400">遠征資金</span>
                        <span class="text-2xl font-bold text-amber-300 flex items-center gap-1">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/><text x="12" y="16" font-size="12" text-anchor="middle" fill="#1f2937" font-weight="bold">G</text></svg>
                            {{ guest.gold }} G
                        </span>
                    </div>
                    <div v-if="guest.hired_character_id" class="text-xs text-indigo-300 bg-indigo-900/30 rounded p-2">
                        一時契約を結んでいます (ID: {{ guest.hired_character_id }})
                    </div>
                    <div v-else class="text-xs text-gray-500">
                        まだ盟約を結んだ者はいません
                    </div>
                </section>

                <!-- 進行中バトル -->
                <div v-if="active_battle_id" class="bg-amber-900/30 border border-amber-700/50 rounded-xl p-4 text-center text-sm">
                    進行中の戦いがあります。
                    <Link :href="route('battles.show', { battle: active_battle_id })"
                          class="underline text-amber-300 font-semibold ml-1">続きから再開</Link>
                </div>

                <!-- 行動ボタン -->
                <div class="space-y-3">
                    <Link :href="route('job-seekers.index')"
                          class="block w-full py-4 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 transition font-bold tracking-wide text-center shadow-lg shadow-indigo-900/30">
                        契約者名簿を見る
                    </Link>

                    <template v-if="is_authenticated">
                        <Link :href="route('houses.create')"
                              class="block w-full py-4 rounded-xl bg-emerald-700 hover:bg-emerald-600 transition font-bold tracking-wide text-center">
                            家門を作成する
                        </Link>
                    </template>
                    <template v-else>
                        <div class="grid grid-cols-2 gap-3">
                            <Link :href="route('login')"
                                  class="py-3 rounded-xl bg-gray-700 hover:bg-gray-600 transition font-semibold text-center text-sm">
                                ログイン
                            </Link>
                            <Link :href="route('register')"
                                  class="py-3 rounded-xl bg-gray-700 hover:bg-gray-600 transition font-semibold text-center text-sm">
                                新規登録
                            </Link>
                        </div>
                        <p class="text-xs text-gray-500 text-center">ログインすると家門を作って継続雇用できます</p>
                    </template>
                </div>

                <section class="text-xs text-gray-500 leading-relaxed border-t border-gray-700/50 pt-4">
                    <p class="font-semibold text-gray-400 mb-2">MVP ルール</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>求職者からキャラを雇用してバトル開始</li>
                        <li>勝利で Gold 獲得（キャラと分配）</li>
                        <li>ゲスト雇用はコスト 1.5 倍、バトル終了で自動解雇</li>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</template>
