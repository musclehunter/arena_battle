<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    house: { type: Object, default: null },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="min-h-screen bg-gray-900 text-gray-100 flex items-center justify-center p-6">
        <div class="w-full max-w-xl bg-gray-800/60 border border-gray-700/50 rounded-2xl p-8 text-center space-y-6">
            <h1 class="text-2xl font-bold">家門ダッシュボード</h1>

            <template v-if="house">
                <p class="text-gray-400">ログイン中: <span class="text-white font-semibold">{{ house.name }}</span></p>
                <Link :href="route('houses.mine')"
                      class="block w-full py-4 rounded-xl bg-emerald-700 hover:bg-emerald-600 font-bold">
                    家門ダッシュボードへ
                </Link>
            </template>
            <template v-else>
                <p class="text-gray-400">まだ家門がありません。</p>
                <Link :href="route('houses.create')"
                      class="block w-full py-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 font-bold">
                    家門を作成する
                </Link>
            </template>

            <div class="text-xs text-gray-500">
                <Link :href="route('home')" class="underline hover:text-gray-300">トップへ戻る</Link>
            </div>
        </div>
    </div>
</template>
