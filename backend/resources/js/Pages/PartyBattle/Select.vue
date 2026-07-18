<script setup>
import CharacterIcon from '@/Components/CharacterIcon.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    party: { type: Object, required: true },
});

const form = useForm({
    destination: 'dungeon',
    risk: props.party.risk ?? 'normal',
});

const strategyLabels = { aggressive: '攻撃的', balanced: '均衡', defensive: '防御的' };
const riskLabels = { safe: '安全', normal: '通常', high: '高リスク' };
const destinationLabels = { arena: 'アリーナ', dungeon: 'ダンジョン' };

const prediction = computed(() => {
    const r = form.risk;
    return {
        winRate: r === 'safe' ? 78 : r === 'normal' ? 65 : 52,
        retreatRate: r === 'safe' ? 95 : r === 'normal' ? 82 : 60,
        lostRate: r === 'safe' ? 2 : r === 'normal' ? 8 : 22,
        duration: r === 'safe' ? '8〜12分' : r === 'normal' ? '10〜15分' : '12〜18分',
        reward: r === 'safe' ? '低' : r === 'normal' ? '中' : '高',
    };
});

const cost = computed(() => form.destination === 'arena' ? 100 : 0);
</script>

<template>
    <Head title="出撃選択" />
    <main class="min-h-screen bg-gray-900 p-4 text-gray-100 md:p-8">
        <div class="mx-auto max-w-4xl space-y-6">
            <header class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-black">出撃選択</h1>
                    <p class="mt-1 text-sm text-gray-400">遠征隊の目的地とリスクを最終確認します。</p>
                </div>
                <Link :href="route('parties.edit')" class="text-sm text-gray-400 hover:text-white">編成へ戻る</Link>
            </header>

            <!-- 編成サマリー -->
            <section class="rounded-2xl border border-gray-700/50 bg-gray-800/60 p-5 space-y-4">
                <h2 class="font-bold text-gray-200">遠征隊</h2>
                <div class="grid grid-cols-5 gap-2 sm:gap-3">
                    <div v-for="(member, idx) in party.members" :key="idx"
                         class="aspect-square rounded-xl border border-gray-700 bg-gray-900/50 p-2 flex flex-col items-center justify-center gap-1">
                        <CharacterIcon v-if="member.id"
                                       :icon-key="member.preset.icon_key"
                                       :icon-index="member.icon_index"
                                       :gender="member.gender"
                                       :name="member.name"
                                       :size="44" />
                        <span v-else class="text-gray-600 text-xs">空</span>
                        <span class="text-[10px] text-gray-300 truncate w-full text-center leading-none">
                            {{ member.id ? member.name : '未配置' }}
                        </span>
                        <span v-if="member.id" class="text-[9px] text-gray-500 leading-none">{{ member.preset.name }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 text-sm text-gray-400">
                    <div class="rounded bg-gray-900/50 px-3 py-2 border border-gray-700/30">
                        戦術：<span class="text-gray-200">{{ strategyLabels[party.strategy] ?? party.strategy }}</span>
                    </div>
                    <div class="rounded bg-gray-900/50 px-3 py-2 border border-gray-700/30">
                        編成名：<span class="text-gray-200">{{ party.name }}</span>
                    </div>
                </div>
            </section>

            <!-- 出撃先・リスク選択 -->
            <section class="rounded-2xl border border-gray-700/50 bg-gray-800/60 p-5 space-y-5">
                <h2 class="font-bold text-gray-200">目的地とリスク</h2>

                <div>
                    <label class="block text-sm text-gray-400 mb-2">出撃先</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button v-for="(label, key) in destinationLabels" :key="key" type="button"
                                @click="form.destination = key"
                                :class="form.destination === key ? 'border-amber-400 bg-amber-950/30 text-amber-200' : 'border-gray-700 bg-gray-900/50 text-gray-300 hover:border-gray-500'"
                                class="rounded-xl border p-4 text-left transition">
                            <span class="font-bold block">{{ label }}</span>
                            <span class="text-xs text-gray-500 block mt-1">
                                {{ key === 'arena' ? '他の家門（NPC含む）と競う。入場コスト100G。' : 'エリアを進みながらモンスターと遭遇。' }}
                            </span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-2">リスク</label>
                    <div class="grid grid-cols-3 gap-3">
                        <button v-for="(label, key) in riskLabels" :key="key" type="button"
                                @click="form.risk = key"
                                :class="form.risk === key ? 'border-emerald-400 bg-emerald-950/30 text-emerald-200' : 'border-gray-700 bg-gray-900/50 text-gray-300 hover:border-gray-500'"
                                class="rounded-xl border p-3 text-center transition">
                            <span class="font-bold text-sm">{{ label }}</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 rounded-xl bg-gray-900/40 p-4 border border-gray-700/30 text-center">
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
                    <div>
                        <div class="text-[10px] text-gray-500">想定所要時間</div>
                        <div class="text-sm font-bold text-gray-200">{{ prediction.duration }}</div>
                    </div>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">報酬目安</span>
                    <span class="font-bold text-amber-300">{{ prediction.reward }}</span>
                </div>
                <div v-if="cost > 0" class="flex items-center justify-between text-sm">
                    <span class="text-gray-400">出撃コスト</span>
                    <span class="font-bold text-amber-300">{{ cost }} G</span>
                </div>
            </section>

            <form @submit.prevent="form.post(route('party-battles.store'))" class="flex flex-wrap gap-3">
                <button type="submit"
                      :disabled="form.processing || party.members.filter(m => m.id).length === 0"
                      class="rounded-xl bg-amber-600 px-8 py-3 font-bold hover:bg-amber-500 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    {{ form.processing ? '出撃準備中...' : '遠征を開始する' }}
                </button>
                <Link :href="route('parties.edit')"
                      class="rounded-xl bg-gray-700 px-6 py-3 font-bold hover:bg-gray-600 transition text-center inline-flex items-center">
                    編成に戻る
                </Link>
            </form>

            <p v-if="form.errors.risk" class="text-sm text-rose-400">
                {{ form.errors.risk }}
            </p>
        </div>
    </main>
</template>
