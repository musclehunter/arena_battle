<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    logs: {
        type: Array,
        required: true,
    },
});

const scrollEl = ref(null);
const expanded = ref(true);

// 新しいログが増えるたびに最下部に自動スクロール
watch(
    () => props.logs.length,
    async () => {
        if (!expanded.value) return;
        await nextTick();
        if (scrollEl.value) {
            scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
        }
    },
    { immediate: true },
);
</script>

<template>
    <section class="bg-gray-800/60 rounded-xl border border-gray-700/50 overflow-hidden">
        <button
            @click="expanded = !expanded"
            class="w-full px-4 py-2 flex items-center justify-between text-sm text-gray-300 hover:bg-gray-700/30 transition"
        >
            <span class="font-semibold">戦闘記録</span>
            <span class="text-gray-500">{{ expanded ? '▲' : '▼' }}</span>
        </button>
        <div
            v-show="expanded"
            ref="scrollEl"
            class="px-4 py-3 h-48 overflow-y-auto font-mono text-sm space-y-2 border-t border-gray-700/50"
        >
            <div
                v-for="(log, index) in logs"
                :key="log.id ?? `${log.turn_number}-${index}`"
                class="whitespace-pre-wrap text-gray-200 border-b border-gray-700/30 pb-2 last:border-0"
            >{{ log.summary_text }}</div>
        </div>
    </section>
</template>
