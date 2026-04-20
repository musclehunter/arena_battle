<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({});

const startBattle = () => {
    form.post(route('battles.store'));
};
</script>

<template>
    <Head title="1v1 Arena" />

    <div class="min-h-screen bg-gray-900 text-gray-100 flex items-center justify-center p-6">
        <div class="w-full max-w-xl bg-gray-800 rounded-2xl shadow-2xl p-8 space-y-6">
            <header class="space-y-2 text-center">
                <h1 class="text-3xl font-bold tracking-wide">1v1 Arena</h1>
                <p class="text-sm text-gray-400">ターン制 1vs1 バトル(v1)</p>
            </header>

            <div class="bg-gray-900/60 rounded-xl p-5 text-sm leading-relaxed text-gray-300">
                <p>弱攻撃 / 強攻撃 / カウンター の 3 択で敵と戦います。</p>
                <ul class="list-disc list-inside mt-2 space-y-1 text-gray-400">
                    <li>弱攻撃はカウンターされない</li>
                    <li>強攻撃は高火力だがカウンターされると不発</li>
                    <li>カウンターは強攻撃を弾き、弱攻撃には負ける</li>
                </ul>
            </div>

            <button
                class="w-full py-3 rounded-xl bg-indigo-500 hover:bg-indigo-400 active:bg-indigo-600 transition font-semibold tracking-wide disabled:opacity-50"
                :disabled="form.processing"
                @click="startBattle"
            >
                {{ form.processing ? '開始中...' : '対戦を開始' }}
            </button>
        </div>
    </div>
</template>
