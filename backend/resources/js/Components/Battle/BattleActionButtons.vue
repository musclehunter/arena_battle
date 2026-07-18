<script setup>
defineProps({
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['submit']);

// v1 MVP: 既存の1v1ターン制バックエンドをそのまま活用。
// UI上は新設計の「行動方針」に近い3択を表示し、内部では weak/strong/counter を送信。
const buttons = [
    { action: 'weak', label: '攻撃的', desc: '弱攻撃', base: 'bg-rose-600 hover:bg-rose-500 active:bg-rose-700' },
    { action: 'strong', label: 'スキル優先', desc: '強攻撃', base: 'bg-amber-600 hover:bg-amber-500 active:bg-amber-700' },
    { action: 'counter', label: '防御的', desc: 'カウンター', base: 'bg-sky-600 hover:bg-sky-500 active:bg-sky-700' },
];
</script>

<template>
    <section class="space-y-2">
        <div class="flex items-center justify-between text-xs text-gray-400 px-1">
            <span>行動方針を選択</span>
            <span>攻撃的は確実、スキル優先は高火力、防御的はカウンター</span>
        </div>
        <div class="grid grid-cols-3 gap-3">
            <button
                v-for="btn in buttons"
                :key="btn.action"
                class="py-3 rounded-xl transition font-semibold disabled:opacity-40 disabled:cursor-not-allowed flex flex-col items-center gap-1"
                :class="btn.base"
                :disabled="disabled"
                @click="emit('submit', btn.action)"
            >
                <span>{{ btn.label }}</span>
                <span class="text-[10px] opacity-75 font-normal">{{ btn.desc }}</span>
            </button>
        </div>
    </section>
</template>
