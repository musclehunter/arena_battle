<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const soundVolume = ref(80);
const defaultSpeed = ref(localStorage.getItem('arena-default-speed') ?? '1');
const pushEnabled = ref(false);
const resetTutorial = () => alert('チュートリアルリセットは未実装です');
const saveDefaultSpeed = () => localStorage.setItem('arena-default-speed', defaultSpeed.value);
</script>

<template>
    <Head title="設定" />
    <main class="min-h-screen bg-gray-900 p-4 text-gray-100 md:p-8">
        <div class="mx-auto max-w-2xl space-y-5">
            <header class="flex items-center justify-between">
                <h1 class="text-3xl font-black tracking-tight">設定</h1>
                <Link :href="route('houses.mine')" class="text-sm text-gray-400 hover:text-white">家門へ戻る</Link>
            </header>

            <section class="rounded-2xl bg-gray-800/60 border border-gray-700/50 p-5 space-y-5">
                <h2 class="font-bold text-gray-200">一般</h2>
                <div>
                    <label class="block text-sm text-gray-300 mb-2">効果音音量</label>
                    <input v-model.number="soundVolume" type="range" min="0" max="100" class="w-full accent-amber-500" />
                    <div class="text-xs text-gray-500 mt-1">{{ soundVolume }}%</div>
                </div>
                <div>
                    <label class="block text-sm text-gray-300 mb-2">戦闘倍速デフォルト</label>
                    <div class="flex gap-2">
                        <button v-for="v in ['1','2','4']" :key="v" @click="defaultSpeed = v; saveDefaultSpeed()"
                                :class="defaultSpeed === v ? 'bg-amber-600 text-white' : 'bg-gray-900 text-gray-400 hover:text-white'"
                                class="px-4 py-2 rounded-lg text-sm font-semibold transition">x{{ v }}</button>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-300">プッシュ通知</span>
                    <button @click="pushEnabled = !pushEnabled" :class="pushEnabled ? 'bg-emerald-600' : 'bg-gray-700'" class="px-3 py-1 rounded text-xs font-bold transition">
                        {{ pushEnabled ? 'ON' : 'OFF' }}
                    </button>
                </div>
            </section>

            <section class="rounded-2xl bg-gray-800/60 border border-gray-700/50 p-5 space-y-3">
                <h2 class="font-bold text-gray-200">その他</h2>
                <button @click="resetTutorial" class="rounded-lg bg-gray-700 hover:bg-gray-600 px-4 py-2 text-sm font-bold transition">チュートリアルをリセット</button>
                <div class="text-sm">
                    <Link :href="route('profile.edit')" class="text-indigo-400 hover:text-indigo-300 underline">プロフィール設定へ</Link>
                </div>
            </section>
        </div>
    </main>
</template>
