<script setup>
import CharacterIcon from '@/Components/CharacterIcon.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useMasterData } from '@/Composables/useMasterData';

const { master, jobName } = useMasterData();

const props = defineProps({ characters: Array });
const selectedId = ref(props.characters[0]?.id ?? null);
const learning = ref(false);

const selected = () => props.characters.find((c) => c.id === selectedId.value);
const jobKeyFromIcon = (iconKey) => ({ human_warrior: 'warrior', human_rogue: 'rogue', human_priest: 'priest', human_mage: 'mage' }[iconKey] ?? 'warrior');
const jobLabel = (iconKey) => {
    const jk = jobKeyFromIcon(iconKey);
    return jobName(jk) ?? iconKey;
};
const targetLabel = (t) => ({ enemy_single: '敵単体', enemy_area: '敵範囲', ally_single: '味方単体', self: '自身' }[t] ?? t);

const learn = async (skill) => {
    if (! skill.can_learn || learning.value) return;
    learning.value = true;
    try {
        const response = await fetch(route('skills.learn', { character: selectedId.value }), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            body: JSON.stringify({ skill_id: skill.id }),
        });
        if (response.ok) {
            const data = await response.json();
            const char = selected();
            char.skill_points = data.skill_points;
            char.skills = char.skills.map((s) => s.id === skill.id ? { ...s, learned: true, can_learn: false } : s);
        }
    } finally {
        learning.value = false;
    }
};
</script>

<template>
    <Head title="スキル習得" />
    <main class="min-h-screen bg-gray-950 p-4 text-gray-100">
        <div class="mx-auto max-w-5xl space-y-4">
            <header class="flex items-center justify-between">
                <h1 class="text-xl font-bold">スキル習得</h1>
                <Link :href="route('houses.mine')" class="text-sm text-gray-400 hover:text-white">家門へ戻る</Link>
            </header>

            <section class="flex flex-wrap gap-2">
                <button v-for="char in characters" :key="char.id" @click="selectedId = char.id"
                    class="flex items-center gap-2 rounded-lg border px-3 py-2 transition"
                    :class="selectedId === char.id ? 'border-amber-400 bg-gray-800' : 'border-gray-700 bg-gray-900 hover:border-gray-500'">
                    <CharacterIcon :icon-key="char.job" :icon-index="0" :gender="'unknown'" :name="char.name" :size="32" />
                    <div class="text-left">
                        <div class="text-sm font-bold">{{ char.name }}</div>
                        <div class="text-xs text-gray-500">{{ jobLabel(char.job) }} Lv.{{ char.level }} SP {{ char.skill_points }}</div>
                    </div>
                </button>
            </section>

            <section v-if="selected()" class="space-y-2">
                <div class="rounded-xl border border-gray-700 bg-gray-900/70 p-4">
                    <h2 class="mb-3 text-sm font-bold text-gray-300">{{ selected().name }}の習得可能スキル</h2>
                    <div class="grid gap-2 md:grid-cols-2">
                        <div v-for="skill in selected().skills" :key="skill.id"
                            class="rounded-lg border p-3 transition"
                            :class="skill.learned ? 'border-emerald-700 bg-emerald-950/30' : skill.can_learn ? 'border-gray-700 bg-gray-800/50' : 'border-gray-800 bg-gray-900/30 opacity-60'">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-bold">{{ skill.name }}</span>
                                    <span v-if="skill.learned" class="ml-2 text-xs text-emerald-400">習得済み</span>
                                </div>
                                <span class="text-xs text-gray-500">Lv{{ skill.unlock_level }}以上 · SP{{ skill.sp_cost }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">{{ skill.description }}</p>
                            <div class="mt-1 flex gap-3 text-[10px] text-gray-500">
                                <span>威力 {{ skill.power }}</span>
                                <span>{{ targetLabel(skill.target_type) }}</span>
                                <span v-if="skill.element">属性 {{ skill.element }}</span>
                            </div>
                            <button v-if="! skill.learned && skill.can_learn" @click="learn(skill)" :disabled="learning"
                                class="mt-2 w-full rounded bg-indigo-600 px-3 py-1 text-xs font-bold hover:bg-indigo-500 disabled:opacity-50">
                                習得する (SP{{ skill.sp_cost }})
                            </button>
                            <div v-else-if="! skill.learned && ! skill.can_learn" class="mt-2 text-center text-xs text-gray-600">
                                条件不足
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
