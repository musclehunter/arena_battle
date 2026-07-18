<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import CharacterIcon from '@/Components/CharacterIcon.vue';

const props = defineProps({ house: Object, characters: Array, activities: Array, inventory: Array, jobs: Array });
const house = ref(props.house);
const characters = ref(props.characters);
const activities = ref(props.activities);
const inventory = ref(props.inventory);
const jobs = ref(props.jobs);
const selectedCharacters = ref({});
const message = ref('');
let pollTimer;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const inventoryByKey = computed(() => Object.fromEntries(inventory.value.map((item) => [item.key, item])));
const activeJobs = computed(() => jobs.value.filter((job) => job.status === 'in_progress'));
const completedJobs = computed(() => jobs.value.filter((job) => job.status === 'completed'));
const materials = computed(() => inventory.value.filter((item) => item.category === 'material'));
const items = computed(() => inventory.value.filter((item) => item.category !== 'material'));

const applyData = (data) => {
    house.value = data.house;
    characters.value = data.characters;
    activities.value = data.activities;
    inventory.value = data.inventory;
    jobs.value = data.jobs;
};
const activityLabel = (key) => activities.value.find((activity) => activity.key === key)?.name ?? key;
const canStart = (activity) => {
    const characterId = selectedCharacters.value[activity.key];
    if (!characterId || house.value.gold < activity.gold_cost) return false;
    return Object.entries(activity.inputs).every(([key, quantity]) => (inventoryByKey.value[key]?.quantity ?? 0) >= quantity);
};
const inputText = (activity) => Object.entries(activity.inputs).map(([key, quantity]) => `${inventoryByKey.value[key]?.name ?? key} x${quantity}`).join('、') || 'なし';
const timeLeft = (job) => {
    if (job.status !== 'in_progress') return job.status === 'completed' ? '完成済み' : '受取済み';
    return `${Math.max(0, Math.ceil((new Date(job.completes_at).getTime() - Date.now()) / 1000))} 秒`;
};
const request = async (url, body = null) => {
    const response = await fetch(url, {
        method: body ? 'POST' : 'GET',
        headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() } : {}) },
        ...(body ? { body: JSON.stringify(body) } : {}),
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message ?? '処理に失敗しました。');
    return data;
};
const start = async (activity) => {
    message.value = '';
    try {
        applyData(await request(route('production.jobs.start'), { character_id: selectedCharacters.value[activity.key], activity_key: activity.key }));
    } catch (error) {
        message.value = error.message;
    }
};
const collect = async (job) => {
    message.value = '';
    try {
        applyData(await request(route('production.jobs.collect', { productionJob: job.id }), {}));
    } catch (error) {
        message.value = error.message;
    }
};
const refresh = async () => {
    try {
        applyData(await request(route('production.state')));
    } catch (_) {
    }
};

onMounted(() => { pollTimer = window.setInterval(refresh, 1000); });
onBeforeUnmount(() => { if (pollTimer) window.clearInterval(pollTimer); });
</script>

<template>
    <Head title="生産 / 工房" />
    <main class="min-h-screen bg-gray-950 p-4 text-gray-100 md:p-8">
        <div class="mx-auto max-w-6xl space-y-5">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-3xl font-black tracking-tight">生産 / 工房</h1>
                    <p class="mt-1 text-sm text-gray-400">キャラを割り当て、素材を採取・製作します。全作業の所要時間は5秒です。</p>
                </div>
                <div class="flex items-center gap-4"><span class="font-bold text-amber-300">家門Gold: {{ house.gold }} G</span><Link :href="route('houses.mine')" class="text-sm text-gray-400 hover:text-white">家門へ戻る</Link></div>
            </header>

            <p v-if="message" class="rounded-lg border border-rose-800 bg-rose-950/40 px-4 py-3 text-sm text-rose-200">{{ message }}</p>

            <section class="rounded-2xl border border-gray-700/50 bg-gray-800/60 p-5">
                <h2 class="mb-4 font-bold text-gray-200">家門在庫</h2>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="item in materials" :key="item.key" class="rounded-lg bg-gray-900/60 px-3 py-2 text-sm"><span class="text-gray-400">{{ item.name }}</span><span class="float-right font-bold">{{ item.quantity }}</span></div>
                    <p v-if="!materials.length" class="text-sm text-gray-500">素材はありません。採取作業を開始してください。</p>
                </div>
                <div v-if="items.length" class="mt-3 flex flex-wrap gap-2 text-sm text-emerald-200"><span v-for="item in items" :key="item.key" class="rounded bg-emerald-950/40 px-3 py-1">{{ item.name }} x{{ item.quantity }}</span></div>
            </section>

            <section class="rounded-2xl border border-gray-700/50 bg-gray-800/60 p-5">
                <h2 class="mb-4 font-bold text-gray-200">作業一覧</h2>
                <div class="grid gap-3 lg:grid-cols-2">
                    <article v-for="activity in activities" :key="activity.key" class="rounded-xl border border-gray-700 bg-gray-900/50 p-4">
                        <div class="flex items-start justify-between gap-3"><div><div class="font-bold">{{ activity.name }} <span class="ml-1 text-xs font-normal text-gray-500">{{ activity.category }}</span></div><div class="mt-1 text-xs text-gray-400">素材: {{ inputText(activity) }} / {{ activity.gold_cost }} G / {{ activity.duration_seconds }}秒</div><div class="mt-1 text-xs text-gray-500">完成: {{ inventoryByKey[activity.output]?.name ?? activity.output }} x{{ activity.output_quantity }}</div></div></div>
                        <div class="mt-3 flex gap-2"><select v-model="selectedCharacters[activity.key]" class="min-w-0 flex-1 rounded border border-gray-600 bg-gray-800 px-2 py-1.5 text-sm"><option :value="undefined">担当キャラを選択</option><option v-for="character in characters" :key="character.id" :value="character.id" :disabled="character.busy">{{ character.name }} Lv.{{ character.level }}{{ character.busy ? '（生産中）' : '' }}</option></select><button :disabled="!canStart(activity)" @click="start(activity)" class="rounded bg-emerald-700 px-4 py-1.5 text-sm font-bold enabled:hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-40">開始</button></div>
                    </article>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-700/50 bg-gray-800/60 p-5">
                <h2 class="mb-3 font-bold text-gray-200">生産キュー</h2>
                <div v-if="!activeJobs.length && !completedJobs.length" class="text-sm text-gray-500">進行中・完成待ちの生産はありません。</div>
                <div class="space-y-2"><div v-for="job in [...activeJobs, ...completedJobs]" :key="job.id" class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-gray-900/60 px-4 py-3 text-sm"><div><span class="font-bold">{{ job.activity_name }}</span><span class="ml-2 text-gray-400">担当: {{ job.character.name }}</span><span class="ml-2 text-gray-500">{{ timeLeft(job) }}</span></div><button v-if="job.status === 'completed'" @click="collect(job)" class="rounded bg-amber-700 px-3 py-1 font-bold hover:bg-amber-600">受け取る</button></div></div>
            </section>
        </div>
    </main>
</template>
