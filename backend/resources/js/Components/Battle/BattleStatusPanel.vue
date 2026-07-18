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

const textClass = computed(() =>
    ({
        emerald: 'text-emerald-300',
        rose: 'text-rose-300',
    }[props.color] ?? 'text-emerald-300'),
);
</script>

<template>
    <div class="bg-gray-800/80 rounded-xl p-4 space-y-2 border border-gray-700/50">
        <div class="flex items-baseline justify-between">
            <div class="text-sm text-gray-400">{{ label }}</div>
            <div class="font-semibold" :class="textClass">
                {{ name }}<span v-if="level !== null" class="text-xs text-gray-400 ms-1">Lv.{{ level }}</span>
            </div>
        </div>
        <div class="flex items-baseline justify-between text-sm">
            <span>HP</span>
            <span class="font-semibold">{{ hp }} / {{ maxHp }}</span>
        </div>
        <div class="h-2.5 rounded-full bg-gray-700 overflow-hidden ring-1 ring-gray-600/30">
            <div
                class="h-full transition-all duration-500"
                :class="barClass"
                :style="{ width: `${percent}%` }"
            />
        </div>
        <div v-if="stats" class="text-[10px] text-gray-500 space-y-0.5 pt-1 border-t border-gray-700/50 mt-2">
            <div class="flex justify-between">
                <span>ATK {{ stats.atk }}</span>
                <span>DEF {{ stats.def }}</span>
            </div>
            <div v-if="stats.str !== undefined" class="flex justify-between text-gray-600">
                <span>力{{ stats.str }}</span>
                <span>体{{ stats.vit }}</span>
                <span>器{{ stats.dex }}</span>
                <span>魔{{ stats.int_stat }}</span>
            </div>
        </div>
    </div>
</template>
