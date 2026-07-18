<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CharacterIcon from '@/Components/CharacterIcon.vue';

const props = defineProps({ characters: { type: Array, required: true } });

const genderText = { male: '男', female: '女', unknown: '不明' };

const filterJob = ref('all');
const filterState = ref('all');
const sortKey = ref('level');

const jobs = computed(() => {
    const keys = new Set(props.characters.map((c) => c.preset.name));
    return ['all', ...Array.from(keys)];
});

const filtered = computed(() => {
    let list = [...props.characters];
    if (filterJob.value !== 'all') {
        list = list.filter((c) => c.preset.name === filterJob.value);
    }
    // state: 現状は待機のみ（出撃/生産フラグが無いため）。
    if (filterState.value === 'idle') list = list.filter((c) => true);
    list.sort((a, b) => {
        if (sortKey.value === 'level') return b.level - a.level;
        if (sortKey.value === 'power') {
            const pa = (a.stats.atk + a.stats.def) * a.stats.hp_max;
            const pb = (b.stats.atk + b.stats.def) * b.stats.hp_max;
            return pb - pa;
        }
        if (sortKey.value === 'acquired') return a.id - b.id;
        return 0;
    });
    return list;
});

const releaseForm = useForm({});
const release = (id) => {
    if (!confirm('このキャラを解雇しますか？')) return;
    releaseForm.post(route('houses.release', { character: id }));
};
</script>

<template>
    <Head title="契約者一覧" />
    <main class="min-h-screen bg-gray-900 p-4 text-gray-100 md:p-8">
        <div class="mx-auto max-w-6xl space-y-5">
            <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black tracking-tight">契約者一覧</h1>
                    <p class="text-sm text-gray-400 mt-1">家門に所属する者たちを確認・管理します。</p>
                </div>
                <Link :href="route('houses.mine')" class="text-sm text-gray-400 hover:text-white">家門へ戻る</Link>
            </header>

            <section class="flex flex-wrap items-end gap-3 rounded-xl bg-gray-800/60 border border-gray-700/50 p-4">
                <label class="text-sm">
                    職業
                    <select v-model="filterJob" class="mt-1 block rounded-lg border-gray-700 bg-gray-900 text-sm px-3 py-2">
                        <option value="all">すべて</option>
                        <option v-for="job in jobs.filter(j => j !== 'all')" :key="job" :value="job">{{ job }}</option>
                    </select>
                </label>
                <label class="text-sm">
                    ソート
                    <select v-model="sortKey" class="mt-1 block rounded-lg border-gray-700 bg-gray-900 text-sm px-3 py-2">
                        <option value="level">Lv</option>
                        <option value="power">戦闘力</option>
                        <option value="acquired">獲得順</option>
                    </select>
                </label>
            </section>

            <section v-if="characters.length === 0" class="text-center py-12 text-gray-500 border border-gray-700/50 rounded-2xl">
                まだ家門に契約者がいません。<br />
                <Link :href="route('job-seekers.index')" class="text-emerald-400 underline mt-2 inline-block">求職者名簿を見る</Link>
            </section>

            <section v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="c in filtered" :key="c.id"
                     class="bg-gray-800/60 rounded-2xl p-4 flex gap-4 border border-gray-700/50 hover:border-gray-500 transition">
                    <CharacterIcon
                        :icon-key="c.preset.icon_key"
                        :icon-index="c.icon_index"
                        :gender="c.gender"
                        :name="c.name"
                        :size="80"
                    />
                    <div class="flex-1 min-w-0 space-y-1">
                        <div class="flex items-baseline gap-2">
                            <div class="font-bold truncate text-base">{{ c.name }}</div>
                            <div class="text-xs text-gray-400">{{ c.preset.name }}</div>
                        </div>
                        <div class="text-xs text-gray-400">Lv.{{ c.level }} / {{ genderText[c.gender] || '不明' }}</div>
                        <div class="text-xs text-gray-300">HP {{ c.stats.hp_max }} / ATK {{ c.stats.atk }} / DEF {{ c.stats.def }}</div>
                        <div class="text-[10px] text-gray-500">
                            力{{ c.stats.str }} 体{{ c.stats.vit }} 器{{ c.stats.dex }} 魔{{ c.stats.int_stat }}
                        </div>
                        <div class="text-[10px] text-gray-500">EXP {{ c.exp }} / {{ c.next_exp }} · SP {{ c.skill_points }}</div>
                        <div class="flex flex-wrap gap-2 pt-2">
                            <Link :href="route('characters.show', { character: c.id })"
                                  class="px-3 py-1.5 rounded-lg bg-indigo-700 hover:bg-indigo-600 text-xs font-semibold">
                                詳細
                            </Link>
                            <button @click="release(c.id)"
                                    :disabled="releaseForm.processing"
                                    class="px-3 py-1.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-xs disabled:opacity-40">
                                契約終了
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
