<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    name: { type: String, required: true },
    hp: { type: Number, required: true },
    maxHp: { type: Number, required: true },
    level: { type: Number, default: null },
    stats: { type: Object, default: null }, // { str?, vit?, dex?, int_stat?, atk, def }
    color: { type: String, default: 'emerald' }, // emerald | rose
});

const percent = computed(() => {
    if (!props.maxHp) return 0;
    return Math.max(0, Math.min(100, Math.round((props.hp / props.maxHp) * 100)));
});

const barClass = computed(() =>
    ({
        emerald: 'bg-emerald-400',
        rose: 'bg-rose-400',
    }[props.color] ?? 'bg-emerald-400'),
);
</script>

<template>
    <div class="bg-gray-800 rounded-xl p-4 space-y-2">
        <div class="flex items-baseline justify-between">
            <div class="text-sm text-gray-400">{{ label }}</div>
            <div class="font-semibold">
                {{ name }}<span v-if="level !== null" class="text-xs text-gray-400 ms-1">Lv.{{ level }}</span>
            </div>
        </div>
        <div class="text-sm">HP: {{ hp }} / {{ maxHp }}</div>
        <div class="h-2 rounded bg-gray-700 overflow-hidden">
            <div
                class="h-full transition-all"
                :class="barClass"
                :style="{ width: `${percent}%` }"
            />
        </div>
        <div v-if="stats" class="text-[10px] text-gray-500 space-y-0.5 pt-1 border-t border-gray-700/50">
            <div>ATK {{ stats.atk }} / DEF {{ stats.def }}</div>
            <div v-if="stats.str !== undefined">
                力{{ stats.str }} 体{{ stats.vit }} 器{{ stats.dex }} 魔{{ stats.int_stat }}
            </div>
        </div>
    </div>
</template>
