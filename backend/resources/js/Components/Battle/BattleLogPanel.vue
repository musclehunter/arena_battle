<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    logs: {
        type: Array,
        required: true,
    },
});

const scrollEl = ref(null);

// 新しいログが増えるたびに最下部に自動スクロール
watch(
    () => props.logs.length,
    async () => {
        await nextTick();
        if (scrollEl.value) {
            scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
        }
    },
    { immediate: true },
);
</script>

<template>
    <section
        ref="scrollEl"
        class="bg-gray-800 rounded-xl p-4 h-72 overflow-y-auto font-mono text-sm space-y-2"
    >
        <div
            v-for="(log, index) in logs"
            :key="`${log.turn_number}-${index}`"
            class="whitespace-pre-wrap text-gray-200 border-b border-gray-700/50 pb-2 last:border-0"
        >{{ log.summary_text }}</div>
    </section>
</template>
