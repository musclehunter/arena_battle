<script setup>
import CharacterIcon from '@/Components/CharacterIcon.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    party: {
        type: Object,
        required: true,
    },
    characters: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: props.party.name,
    strategy: props.party.strategy,
    risk: props.party.risk,
    destination: props.party.destination ?? 'dungeon',
    character_ids: [...props.party.character_ids],
});

const selected = (id) => form.character_ids.includes(id);
const toggle = (id) => {
    if (selected(id)) {
        form.character_ids = form.character_ids.filter((value) => value !== id);
        return;
    }
    if (form.character_ids.length < 5) {
        form.character_ids.push(id);
    }
};

const reorder = (fromIndex, toIndex) => {
    const ids = [...form.character_ids];
    const [moved] = ids.splice(fromIndex, 1);
    ids.splice(toIndex, 0, moved);
    form.character_ids = ids;
};

const save = () => form.put(route('parties.update'));

const strategyLabels = {
    aggressive: '攻撃的',
    balanced: '均衡',
    defensive: '防御的',
    wait: '待機的',
    retreat: '撤退',
    skill: 'スキル優先',
    heal: '回復優先',
};
const riskLabels = { safe: '安全', normal: '通常', high: '高リスク' };
const destinationLabels = { arena: 'アリーナ', dungeon: 'ダンジョン' };

const emptySlot = { name: '空き枠', preset: { name: '-' }, level: null };
const slots = computed(() => {
    const list = Array.from({ length: 5 }, () => emptySlot);
    form.character_ids.forEach((id, idx) => {
        const c = props.characters.find((char) => char.id === id);
        if (c) list[idx] = c;
    });
    return list;
});

// 予測情報（未実装のため簡易モック：今後サーバから取得）
const prediction = computed(() => ({
    winRate: form.risk === 'safe' ? 78 : form.risk === 'normal' ? 65 : 52,
    retreatRate: form.risk === 'safe' ? 95 : form.risk === 'normal' ? 82 : 60,
    lostRate: form.risk === 'safe' ? 2 : form.risk === 'normal' ? 8 : 22,
}));
</script>

<template>
    <Head title="遠征隊編成" />
    <main class="min-h-screen bg-gray-900 p-4 text-gray-100 md:p-8">
        <div class="mx-auto max-w-5xl space-y-6">
            <header class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-black">遠征隊編成</h1>
                    <p class="mt-1 text-sm text-gray-400">五名までを選び、戦いに備えよ。</p>
                </div>
                <Link :href="route('houses.mine')" class="text-sm text-gray-400 hover:text-white">家門へ戻る</Link>
            </header>

            <section class="rounded-2xl border border-gray-700/50 bg-gray-800/60 p-5 space-y-4">
                <div class="flex flex-col gap-4 md:flex-row md:items-end">
                    <label class="flex-1 text-sm">
                        遠征隊名
                        <input v-model="form.name" class="mt-1 w-full rounded-lg border-gray-700 bg-gray-900 text-sm px-3 py-2" />
                    </label>
                    <label class="text-sm md:w-40">
                        戦術
                        <select v-model="form.strategy" class="mt-1 w-full rounded-lg border-gray-700 bg-gray-900 text-sm px-3 py-2">
                            <option v-for="(label, key) in strategyLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </label>
                    <label class="text-sm md:w-40">
                        危険度
                        <select v-model="form.risk" class="mt-1 w-full rounded-lg border-gray-700 bg-gray-900 text-sm px-3 py-2">
                            <option v-for="(label, key) in riskLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </label>
                    <label class="text-sm md:w-44">
                        出撃先
                        <select v-model="form.destination" class="mt-1 w-full rounded-lg border-gray-700 bg-gray-900 text-sm px-3 py-2">
                            <option v-for="(label, key) in destinationLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </label>
                </div>

                <div class="grid grid-cols-5 gap-2 sm:gap-3">
                    <div v-for="(slot, index) in slots" :key="index"
                         class="relative min-h-28 rounded-xl border border-dashed border-gray-600 bg-gray-900/50 p-2 text-center flex flex-col items-center justify-center gap-1 transition hover:border-gray-500">
                        <template v-if="slot.id">
                            <CharacterIcon
                                :icon-key="slot.preset.icon_key"
                                :icon-index="slot.icon_index"
                                :gender="slot.gender"
                                :name="slot.name"
                                :size="52"
                                class="mx-auto"
                            />
                            <div class="truncate text-xs font-bold w-full">{{ slot.name }}</div>
                            <div class="text-[10px] text-gray-500">{{ slot.preset.name }} <span v-if="slot.level">Lv.{{ slot.level }}</span></div>
                        </template>
                        <span v-else class="text-gray-500 text-xs">空き枠</span>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 rounded-xl bg-gray-900/40 p-4 border border-gray-700/30 text-center">
                    <div>
                        <div class="text-[10px] text-gray-500">勝率目安</div>
                        <div class="text-lg font-bold text-emerald-300">{{ prediction.winRate }}%</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-gray-500">撤退成功率</div>
                        <div class="text-lg font-bold text-amber-300">{{ prediction.retreatRate }}%</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-gray-500">ロスト率</div>
                        <div class="text-lg font-bold text-rose-300">{{ prediction.lostRate }}%</div>
                    </div>
                </div>

                <p v-if="form.errors.character_ids" class="text-sm text-rose-400">{{ form.errors.character_ids }}</p>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button @click="save" :disabled="form.processing"
                            class="rounded-xl bg-emerald-700 px-6 py-3 font-bold hover:bg-emerald-600 disabled:opacity-50 transition">
                        編成を保存する（{{ form.character_ids.length }}/5）
                    </button>
                    <Link :href="route('party-battles.select')"
                          :class="form.character_ids.length === 0 ? 'pointer-events-none opacity-40' : ''"
                          class="rounded-xl bg-amber-600 px-6 py-3 font-bold hover:bg-amber-500 transition text-center">
                        この編成で出撃選択へ
                    </Link>
                </div>
            </section>

            <section>
                <h2 class="text-sm font-bold text-gray-300 mb-3">待機中の契約者</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button v-for="character in characters" :key="character.id" type="button" @click="toggle(character.id)"
                            :class="selected(character.id) ? 'border-emerald-400 bg-emerald-950/40' : 'border-gray-700/50 bg-gray-800/60'"
                            class="flex items-center gap-3 rounded-xl border p-3 text-left transition hover:border-gray-500">
                        <CharacterIcon
                            :icon-key="character.preset.icon_key"
                            :icon-index="character.icon_index"
                            :gender="character.gender"
                            :name="character.name"
                            :size="52"
                        />
                        <span class="min-w-0">
                            <b class="block truncate">{{ character.name }}</b>
                            <small class="block text-gray-400">{{ character.preset.name }} / Lv.{{ character.level }}</small>
                            <small class="text-gray-500">HP {{ character.stats.hp }} / ATK {{ character.stats.atk }}</small>
                        </span>
                    </button>
                </div>
            </section>
        </div>
    </main>
</template>
