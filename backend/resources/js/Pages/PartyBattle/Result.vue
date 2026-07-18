<script setup>
import { Head, Link } from '@inertiajs/vue3';
import CharacterIcon from '@/Components/CharacterIcon.vue';

const props = defineProps({ battle: { type: Object, required: true } });
const b = props.battle;

const isVictory = b.winner === 'player';
const totalReward = b.reward_gold ?? 0;
const characterReward = b.reward_gold_to_characters ?? 0;
const houseReward = b.reward_gold_to_house ?? Math.max(0, totalReward - characterReward);
const alivePlayers = b.players.filter((p) => p.hp > 0);
const defeatedPlayers = b.players.filter((p) => p.hp <= 0);

const riskLabels = { safe: '安全', normal: '通常', high: '高リスク' };
</script>

<template>
    <Head :title="`遠征結果 #${b.id}`" />
    <main class="min-h-screen bg-gray-950 p-4 text-gray-100 md:p-8">
        <div class="mx-auto max-w-4xl space-y-6">
            <header class="text-center">
                <div class="text-xs uppercase tracking-widest text-gray-500">EXPEDITION RESULT</div>
                <h1 class="text-4xl font-black mt-2" :class="isVictory ? 'text-amber-300' : 'text-rose-300'">
                    {{ isVictory ? '遠征成功' : '遠征失敗' }}
                </h1>
                <p class="text-sm text-gray-400 mt-2">
                    危険度：{{ riskLabels[b.risk] ?? b.risk }} / 戦術：{{ b.strategy }}
                </p>
            </header>

            <section class="rounded-2xl bg-gray-800/60 border border-gray-700/50 p-6">
                <h2 class="text-sm text-gray-400 mb-4 text-center">獲得報酬</h2>
                <div class="grid gap-3 text-center sm:grid-cols-3">
                    <div class="rounded-xl bg-gray-900/60 p-3">
                        <div class="text-xs text-gray-500">報酬総額</div>
                        <div class="mt-1 text-2xl font-bold text-amber-300">{{ totalReward }} G</div>
                    </div>
                    <div class="rounded-xl bg-emerald-950/30 p-3">
                        <div class="text-xs text-emerald-200">家門取り分</div>
                        <div class="mt-1 text-2xl font-bold text-emerald-300">{{ houseReward }} G</div>
                        <div class="mt-1 text-[10px] text-gray-500">家門資金に加算</div>
                    </div>
                    <div class="rounded-xl bg-amber-950/30 p-3">
                        <div class="text-xs text-amber-100">キャラ取り分合計</div>
                        <div class="mt-1 text-2xl font-bold text-amber-300">{{ characterReward }} G</div>
                        <div class="mt-1 text-[10px] text-gray-500">各キャラへ加算</div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl bg-gray-800/60 border border-gray-700/50 p-6">
                <h2 class="font-bold text-gray-200 mb-4">キャラ状態</h2>
                <div v-if="b.players.length === 0" class="text-sm text-gray-500">データがありません。</div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="p in b.players" :key="p.character_id"
                         class="rounded-xl border p-4 transition"
                         :class="p.hp > 0 ? 'border-gray-700 bg-gray-900/50' : 'border-rose-900/50 bg-rose-950/20 opacity-60'">
                        <div class="flex items-center gap-3 mb-3">
                            <CharacterIcon :icon-key="p.icon_key" :icon-index="p.icon_index" :gender="p.gender" :name="p.name" :size="56" />
                            <div>
                                <div class="font-bold">{{ p.name }}</div>
                                <div class="text-xs text-gray-500">Lv.{{ p.level }}</div>
                            </div>
                        </div>
                        <div class="text-xs text-gray-400 flex justify-between mb-1">
                            <span>HP</span>
                            <span :class="p.hp <= 0 ? 'text-rose-400 font-bold' : ''">{{ p.hp }} / {{ p.max_hp }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-700 overflow-hidden">
                            <div class="h-full transition-all duration-500" :class="p.hp > 0 ? 'bg-emerald-500' : 'bg-rose-500'" :style="{ width: `${Math.max(0, (p.hp / p.max_hp) * 100)}%` }"></div>
                        </div>
                        <div class="mt-3 text-[10px] text-gray-500 grid grid-cols-2 gap-1">
                            <span>負傷：—</span>
                            <span>契約残：—</span>
                        </div>
                        <div v-if="isVictory" class="mt-3 flex items-center justify-between rounded-lg bg-amber-950/40 px-3 py-2 text-xs">
                            <span class="text-amber-100">キャラ取り分</span>
                            <span class="font-bold text-amber-300">+{{ p.gold_gained ?? 0 }} G</span>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="defeatedPlayers.length > 0" class="rounded-2xl bg-rose-900/20 border border-rose-900/50 p-6">
                <h2 class="font-bold text-rose-200 mb-2">ロスト報告</h2>
                <p class="text-sm text-gray-300">
                    今回の遠征で戦闘不能になった者：{{ defeatedPlayers.map((p) => p.name).join('、') }}
                </p>
                <p class="text-xs text-gray-500 mt-1">v1 ではロストは発生しません（負傷のみ）。今後の拡張です。</p>
            </section>

            <section class="rounded-2xl bg-gray-800/60 border border-gray-700/50 p-6">
                <h2 class="font-bold text-gray-200 mb-4">装備劣化</h2>
                <p class="text-sm text-gray-500">装備システムは今後の拡張です。</p>
            </section>

            <div class="flex flex-wrap justify-center gap-3 pt-4">
                <Link :href="route('parties.edit')" class="rounded-xl bg-indigo-600 px-6 py-3 font-bold hover:bg-indigo-500 transition">次の出撃へ</Link>
                <Link :href="route('houses.mine')" class="rounded-xl bg-gray-700 px-6 py-3 font-bold hover:bg-gray-600 transition">家門へ戻る</Link>
            </div>
        </div>
    </main>
</template>
