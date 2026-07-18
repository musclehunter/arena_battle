<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const diplomacy = ref([
    { race: '人間', house: 'フリード家', score: 15, reason: '初期友好' },
    { race: '森の民', house: 'エルウィン家', score: -10, reason: '領境での小競り合い' },
]);
</script>

<template>
    <Head title="血盟" />
    <main class="min-h-screen bg-gray-900 p-4 text-gray-100 md:p-8">
        <div class="mx-auto max-w-5xl space-y-5">
            <header class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-black tracking-tight">血盟</h1>
                    <p class="text-sm text-gray-400 mt-1">他の家門・種族との連合・外交（v1 未実装）</p>
                </div>
                <Link :href="route('houses.mine')" class="text-sm text-gray-400 hover:text-white">家門へ戻る</Link>
            </header>

            <section class="grid md:grid-cols-2 gap-5">
                <div class="rounded-2xl bg-gray-800/60 border border-gray-700/50 p-5">
                    <h2 class="font-bold text-gray-200 mb-4">血盟</h2>
                    <p class="text-sm text-gray-400">まだ血盟に加入していません。</p>
                    <button @click="() => alert('血盟機能は未実装です')" class="mt-4 rounded-lg bg-violet-700 px-4 py-2 text-sm font-bold hover:bg-violet-600 transition">
                        血盟を探す
                    </button>
                </div>

                <div class="rounded-2xl bg-gray-800/60 border border-gray-700/50 p-5">
                    <h2 class="font-bold text-gray-200 mb-4">外交</h2>
                    <ul class="space-y-3">
                        <li v-for="d in diplomacy" :key="d.house" class="rounded bg-gray-900/50 border border-gray-700/30 p-3 text-sm">
                            <div class="flex justify-between">
                                <span class="font-bold">{{ d.house }}（{{ d.race }}）</span>
                                <span class="font-semibold" :class="d.score >= 0 ? 'text-emerald-300' : 'text-rose-300'">{{ d.score > 0 ? '+' : '' }}{{ d.score }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">{{ d.reason }}</p>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    </main>
</template>
