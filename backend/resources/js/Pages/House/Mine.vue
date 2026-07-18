<script setup>
import { Head, Link } from '@inertiajs/vue3';
import CharacterIcon from '@/Components/CharacterIcon.vue';
import { computed, ref } from 'vue';
import { useMasterData } from '@/Composables/useMasterData';

const { fetchMaster, clearCache, versions } = useMasterData();
const cacheStatus = ref('');
const menuOpen = ref(false);

const refreshMaster = async () => {
    cacheStatus.value = '更新確認中...';
    await fetchMaster(false);
    cacheStatus.value = `更新完了 (skills v${versions.value.skills}, atb v${versions.value.atb})`;
};
const purgeCache = () => {
    clearCache();
    cacheStatus.value = 'キャッシュを削除しました。再取得します...';
    fetchMaster(true).then(() => {
        cacheStatus.value = `再取得完了 (skills v${versions.value.skills}, atb v${versions.value.atb})`;
    });
};

const props = defineProps({
    house: { type: Object, required: true },
    characters: { type: Array, required: true },
    party: { type: Object, default: null },
    active_battle_id: { type: [Number, null], default: null },
    active_party_battle_id: { type: [Number, null], default: null },
});

const strategyLabels = { aggressive: '攻勢', balanced: '均衡', defensive: '防御', wait: '待機', retreat: '撤退', skill: '技優先', heal: '回復優先' };
const riskLabels = { safe: '慎重', normal: '標準', high: '強行' };

const activePartyCount = computed(() => props.active_party_battle_id ? 1 : 0);
const idleCount = computed(() => props.characters.length);
const productionCount = ref(0);

const notices = ref([
    'シーズン1 が開幕しました。',
    '契約者名簿が更新されました。',
]);

const emptySlot = { name: '空き枠', preset: { icon_key: null, name: '-' }, icon_index: 0, gender: 'unknown' };
const partySlots = computed(() => {
    const slots = Array.from({ length: 5 }, () => emptySlot);
    (props.party?.members ?? []).forEach((m) => {
        if (m.slot >= 0 && m.slot < 5) {
            slots[m.slot] = m;
        }
    });
    return slots;
});
</script>

<template>
    <Head :title="`家門: ${house.name}`" />

    <div class="min-h-screen bg-gray-900 text-gray-100">
        <!-- トップバー -->
        <header class="border-b border-gray-800 bg-gray-900/95 sticky top-0 z-20">
            <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="text-xl font-black tracking-tight">{{ house.name }}</span>
                    <span class="text-xs px-2 py-1 rounded-full bg-gray-800 border border-gray-700">家門 Lv.{{ house.level }}</span>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <div class="flex items-center gap-2 text-amber-300 font-bold">
                        <span class="text-gray-400 font-normal text-xs">所持金</span>
                        {{ house.gold.toLocaleString() }} G
                    </div>
                    <div class="hidden sm:block text-xs text-gray-500">契約枠 {{ house.hired_count }}/{{ house.hire_slots }}</div>
                    <div class="hidden sm:block text-xs text-gray-500">シーズン終了まで 30日</div>

                    <div class="relative">
                        <button @click="menuOpen = !menuOpen" class="text-gray-300 hover:text-white px-3 py-1.5 rounded-lg border border-gray-700 hover:bg-gray-800 text-xs transition">
                            メニュー
                        </button>
                        <div v-show="menuOpen" class="absolute right-0 mt-2 w-44 rounded-xl bg-gray-800 border border-gray-700 shadow-lg py-1 z-30">
                            <Link :href="route('characters.index')" @click="menuOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition">契約者一覧</Link>
                            <Link :href="route('skills.index')" @click="menuOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition">スキル習得</Link>
                            <Link :href="route('job-seekers.index')" @click="menuOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition">求職者名簿</Link>
                            <div class="border-t border-gray-700 my-1"></div>
                            <Link :href="route('home')" @click="menuOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition">トップへ</Link>
                            <Link :href="route('settings.index')" @click="menuOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition">設定</Link>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="relative z-10 p-4 md:p-6">
            <div class="max-w-6xl mx-auto space-y-5">
                <!-- 3カラムダッシュボード -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- 左：現在の活動 -->
                    <section class="bg-gray-800/60 rounded-2xl p-5 border border-gray-700/50 flex flex-col gap-4">
                        <h2 class="font-bold text-gray-200">現在の活動</h2>

                        <div class="space-y-3 flex-1">
                            <div class="flex items-center justify-between rounded-xl bg-gray-900/50 p-3 border border-gray-700/30">
                                <span class="text-sm text-gray-400">出撃中パーティ</span>
                                <span class="font-bold text-emerald-300">{{ activePartyCount }} PT</span>
                            </div>
                            <div class="flex items-center justify-between rounded-xl bg-gray-900/50 p-3 border border-gray-700/30">
                                <span class="text-sm text-gray-400">生産中</span>
                                <span class="font-bold text-amber-300">{{ productionCount }} 人</span>
                            </div>
                            <div class="flex items-center justify-between rounded-xl bg-gray-900/50 p-3 border border-gray-700/30">
                                <span class="text-sm text-gray-400">待機中</span>
                                <span class="font-bold text-gray-300">{{ idleCount }} 人</span>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-gray-700/50">
                            <h3 class="text-xs text-gray-500 mb-2">シーズン進行</h3>
                            <div class="h-2 rounded-full bg-gray-700 overflow-hidden">
                                <div class="h-full w-1/4 bg-indigo-500"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">1/4 エリア解放 / 秘宝未到達</p>
                        </div>

                        <Link v-if="active_party_battle_id" :href="route('party-battles.show', { partyBattle: active_party_battle_id })"
                              class="block w-full py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-600 transition font-semibold text-center text-sm">
                            遠征を確認する
                        </Link>
                        <Link v-else :href="route('party-battles.select')"
                              class="block w-full py-2.5 rounded-xl bg-rose-700 hover:bg-rose-600 transition font-semibold text-center text-sm">
                            出撃する
                        </Link>
                    </section>

                    <!-- 中央：遠征隊編成 -->
                    <section class="bg-gray-800/60 rounded-2xl p-5 border border-gray-700/50 flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <h2 class="font-bold text-gray-200">遠征隊編成</h2>
                            <span v-if="party?.name" class="text-xs text-gray-400">{{ party.name }}</span>
                        </div>

                        <div class="grid grid-cols-5 gap-2 flex-1">
                            <div v-for="(member, idx) in partySlots" :key="idx"
                                 class="aspect-square rounded-xl border border-dashed border-gray-600 bg-gray-900/40 flex flex-col items-center justify-center gap-1 p-1">
                                <CharacterIcon v-if="member.id"
                                                 :icon-key="member.preset.icon_key"
                                                 :icon-index="member.icon_index"
                                                 :gender="member.gender"
                                                 :name="member.name"
                                                 :size="40" />
                                <span v-else class="text-gray-600 text-xs">空</span>
                                <span class="text-[10px] text-gray-400 truncate w-full text-center leading-none">
                                    {{ member.id ? member.name : '未配置' }}
                                </span>
                                <span v-if="member.id" class="text-[9px] text-gray-500 leading-none">{{ member.preset.name }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs text-gray-400">
                            <div class="rounded bg-gray-900/50 px-3 py-2 border border-gray-700/30">
                                戦術：<span class="text-gray-200">{{ strategyLabels[party?.strategy] ?? '—' }}</span>
                            </div>
                            <div class="rounded bg-gray-900/50 px-3 py-2 border border-gray-700/30">
                                危険度：<span class="text-gray-200">{{ riskLabels[party?.risk] ?? '—' }}</span>
                            </div>
                        </div>

                        <Link :href="route('parties.edit')"
                              class="block w-full py-2.5 rounded-xl bg-indigo-700 hover:bg-indigo-600 transition font-semibold text-center text-sm">
                            編成を開く
                        </Link>
                    </section>

                    <!-- 右：お知らせ -->
                    <section class="bg-gray-800/60 rounded-2xl p-5 border border-gray-700/50 flex flex-col gap-4">
                        <h2 class="font-bold text-gray-200">お知らせ</h2>
                        <ul class="space-y-2 flex-1">
                            <li v-for="(notice, i) in notices" :key="i" class="text-sm text-gray-300 flex items-start gap-2">
                                <span class="mt-1.5 w-1 h-1 rounded-full bg-amber-400 flex-shrink-0"></span>
                                <span>{{ notice }}</span>
                            </li>
                            <li v-if="notices.length === 0" class="text-sm text-gray-500">新着はありません。</li>
                        </ul>

                        <div class="pt-2 border-t border-gray-700/50">
                            <h3 class="text-xs text-gray-500 mb-2">各種施設</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <Link :href="route('market.index')" class="flex items-center justify-center rounded-lg bg-gray-900/50 border border-gray-700/30 py-2 hover:bg-gray-700/50 transition">
                                    <span class="text-xs font-bold text-amber-400">市場</span>
                                </Link>
                                <Link :href="route('production.index')" class="flex items-center justify-center rounded-lg bg-gray-900/50 border border-gray-700/30 py-2 hover:bg-gray-700/50 transition">
                                    <span class="text-xs font-bold text-emerald-400">生産</span>
                                </Link>
                                <Link :href="route('blood-pact.index')" class="flex items-center justify-center rounded-lg bg-gray-900/50 border border-gray-700/30 py-2 hover:bg-gray-700/50 transition">
                                    <span class="text-xs font-bold text-violet-400">血盟</span>
                                </Link>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- 契約者サマリー -->
                <section class="bg-gray-800/60 rounded-2xl p-5 border border-gray-700/50">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-bold text-gray-200">家門の契約者</h2>
                        <Link :href="route('characters.index')" class="text-xs text-indigo-400 hover:text-indigo-300 transition">契約者一覧 →</Link>
                    </div>
                    <div v-if="characters.length === 0" class="text-sm text-gray-500 py-4 text-center">
                        まだ家門の盟約者がいません。
                        <Link :href="route('job-seekers.index')" class="text-emerald-400 underline ml-1">契約者名簿を見る</Link>
                    </div>
                    <div v-else class="flex flex-wrap items-center gap-3">
                        <Link v-for="c in characters" :key="c.id" :href="route('characters.show', { character: c.id })"
                              class="flex items-center gap-2 rounded-full bg-gray-900/50 border border-gray-700/30 pl-1 pr-3 py-1 hover:bg-gray-700/50 transition">
                            <CharacterIcon :icon-key="c.preset.icon_key" :icon-index="c.icon_index" :gender="c.gender" :name="c.name" :size="32" />
                            <div class="text-xs">
                                <div class="font-bold text-gray-200">{{ c.name }}</div>
                                <div class="text-[10px] text-gray-500">Lv.{{ c.level }}</div>
                            </div>
                        </Link>
                    </div>
                </section>

                <!-- マスタデータキャッシュ管理 -->
                <div class="flex items-center gap-3 text-xs text-gray-500 border-t border-gray-800 pt-4">
                    <button @click="refreshMaster" class="rounded bg-gray-800 px-3 py-1 hover:bg-gray-700 transition">マスタ更新確認</button>
                    <button @click="purgeCache" class="rounded bg-gray-800 px-3 py-1 hover:bg-gray-700 transition">キャッシュ削除</button>
                    <span v-if="cacheStatus" class="text-gray-400">{{ cacheStatus }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
