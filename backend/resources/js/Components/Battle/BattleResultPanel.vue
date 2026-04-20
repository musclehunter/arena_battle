<script setup>
import { computed } from 'vue';

const props = defineProps({
    winner: { type: String, default: null }, // 'player' | 'enemy' | 'draw'
    processing: { type: Boolean, default: false },
    reward: { type: Object, default: null }, // { total, to_character, to_house }
    canRestart: { type: Boolean, default: true },
    restartLabel: { type: String, default: 'もう一度挑む' },
    homeHref: { type: String, default: null },
});

const emit = defineEmits(['restart']);

const text = computed(() => ({
    player: { title: '勝利！', tone: 'text-amber-300' },
    enemy: { title: '敗北…', tone: 'text-rose-400' },
    draw: { title: '引き分け', tone: 'text-gray-200' },
}[props.winner] ?? { title: '', tone: '' }));

const hasReward = computed(() => {
    const r = props.reward;
    return r && Number(r.total) > 0;
});
</script>

<template>
    <section class="bg-gray-800 rounded-xl p-5 text-center space-y-3">
        <div class="text-2xl font-bold tracking-wide" :class="text.tone">
            {{ text.title }}
        </div>

        <div v-if="hasReward" class="text-sm text-gray-300 space-y-1">
            <div class="text-amber-300 font-semibold">獲得報酬: {{ reward.total }} G</div>
            <div class="text-xs text-gray-400">
                キャラ取り分 {{ reward.to_character }} G / 家門取り分 {{ reward.to_house }} G
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 justify-center">
            <button
                v-if="canRestart"
                class="px-6 py-3 rounded-xl bg-indigo-500 hover:bg-indigo-400 active:bg-indigo-600 transition font-semibold disabled:opacity-50"
                :disabled="processing"
                @click="emit('restart')"
            >{{ processing ? '準備中...' : restartLabel }}</button>
            <a
                v-if="homeHref"
                :href="homeHref"
                class="px-6 py-3 rounded-xl bg-gray-700 hover:bg-gray-600 transition font-semibold"
            >ホームに戻る</a>
        </div>
    </section>
</template>
