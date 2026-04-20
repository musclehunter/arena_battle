<script setup>
defineProps({
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['submit']);

const buttons = [
    { action: 'weak', label: '弱攻撃', base: 'bg-sky-600 hover:bg-sky-500 active:bg-sky-700' },
    { action: 'strong', label: '強攻撃', base: 'bg-red-600 hover:bg-red-500 active:bg-red-700' },
    { action: 'counter', label: 'カウンター', base: 'bg-amber-600 hover:bg-amber-500 active:bg-amber-700' },
];
</script>

<template>
    <section class="grid grid-cols-3 gap-3">
        <button
            v-for="btn in buttons"
            :key="btn.action"
            class="py-3 rounded-xl transition font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
            :class="btn.base"
            :disabled="disabled"
            @click="emit('submit', btn.action)"
        >{{ btn.label }}</button>
    </section>
</template>
