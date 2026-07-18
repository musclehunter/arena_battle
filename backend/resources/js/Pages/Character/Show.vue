<script setup>
import { Head, Link } from '@inertiajs/vue3';
import CharacterIcon from '@/Components/CharacterIcon.vue';
import { useMasterData } from '@/Composables/useMasterData';

const { jobName } = useMasterData();

const props = defineProps({ character: { type: Object, required: true } });
const c = props.character;

const genderText = { male: '男', female: '女', unknown: '不明' };

const jobKeyFromIcon = (iconKey) => ({
    human_warrior: 'warrior',
    human_rogue: 'rogue',
    human_priest: 'priest',
    human_mage: 'mage',
    human_guardian: 'guardian',
}[iconKey] ?? iconKey);

const targetLabel = (t) => ({ enemy_single: '敵単体', enemy_area: '敵範囲', ally_single: '味方単体', self: '自身' }[t] ?? t);
const elementLabel = (e) => e || '無';

const hiredDate = c.contract?.hired_at ? new Date(c.contract.hired_at).toLocaleDateString('ja-JP') : '—';
</script>

<template>
    <Head :title="`${c.name} - 契約者詳細`" />
    <main class="min-h-screen bg-gray-900 p-4 text-gray-100 md:p-8">
        <div class="mx-auto max-w-5xl space-y-5">
            <header class="flex items-center justify-between">
                <Link :href="route('characters.index')" class="text-sm text-gray-400 hover:text-white">&larr; 契約者一覧</Link>
                <Link :href="route('houses.mine')" class="text-sm text-gray-400 hover:text-white">家門へ戻る</Link>
            </header>

            <section class="rounded-2xl bg-gray-800/60 border border-gray-700/50 p-6">
                <div class="flex flex-col sm:flex-row gap-6">
                    <CharacterIcon
                        :icon-key="c.preset.icon_key"
                        :icon-index="c.icon_index"
                        :gender="c.gender"
                        :name="c.name"
                        :size="140"
                        class="mx-auto sm:mx-0"
                    />
                    <div class="flex-1 space-y-2">
                        <h1 class="text-3xl font-black">{{ c.name }}</h1>
                        <div class="text-sm text-gray-400">
                            {{ c.preset.name }} / Lv.{{ c.level }} / {{ genderText[c.gender] || '不明' }}
                        </div>
                        <div class="text-sm text-gray-400">
                            職業系統：<span class="text-gray-200">{{ jobName(jobKeyFromIcon(c.preset.icon_key)) ?? c.preset.name }}</span>
                        </div>
                        <div class="pt-2 flex flex-wrap gap-3 text-xs text-gray-500">
                            <span class="px-2 py-1 rounded bg-gray-900 border border-gray-700">EXP {{ c.exp }} / {{ c.next_exp }}</span>
                            <span class="px-2 py-1 rounded bg-gray-900 border border-gray-700">SP {{ c.skill_points }}</span>
                            <span class="px-2 py-1 rounded bg-gray-900 border border-gray-700">所持金 {{ c.gold }} G</span>
                            <span class="px-2 py-1 rounded bg-gray-900 border border-gray-700">取り分 {{ (c.reward_share_bp / 100).toFixed(1) }}%</span>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid md:grid-cols-2 gap-5">
                <!-- 能力値 -->
                <section class="rounded-2xl bg-gray-800/60 border border-gray-700/50 p-5 space-y-4">
                    <h2 class="font-bold text-gray-200">能力値</h2>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">HP 最大</span>
                            <span class="font-bold text-emerald-300">{{ c.stats.hp_max }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-700 overflow-hidden">
                            <div class="h-full bg-emerald-500 w-full"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="rounded bg-gray-900/50 border border-gray-700/30 p-3">
                            <div class="text-xs text-gray-500">力</div>
                            <div class="text-lg font-bold">{{ c.stats.str }}</div>
                        </div>
                        <div class="rounded bg-gray-900/50 border border-gray-700/30 p-3">
                            <div class="text-xs text-gray-500">体</div>
                            <div class="text-lg font-bold">{{ c.stats.vit }}</div>
                        </div>
                        <div class="rounded bg-gray-900/50 border border-gray-700/30 p-3">
                            <div class="text-xs text-gray-500">器</div>
                            <div class="text-lg font-bold">{{ c.stats.dex }}</div>
                        </div>
                        <div class="rounded bg-gray-900/50 border border-gray-700/30 p-3">
                            <div class="text-xs text-gray-500">魔</div>
                            <div class="text-lg font-bold">{{ c.stats.int_stat }}</div>
                        </div>
                        <div class="rounded bg-gray-900/50 border border-gray-700/30 p-3">
                            <div class="text-xs text-gray-500">ATK</div>
                            <div class="text-lg font-bold">{{ c.stats.atk }}</div>
                        </div>
                        <div class="rounded bg-gray-900/50 border border-gray-700/30 p-3">
                            <div class="text-xs text-gray-500">DEF</div>
                            <div class="text-lg font-bold">{{ c.stats.def }}</div>
                        </div>
                    </div>
                </section>

                <!-- 契約・装備 -->
                <section class="rounded-2xl bg-gray-800/60 border border-gray-700/50 p-5 space-y-4">
                    <h2 class="font-bold text-gray-200">契約</h2>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">雇用主</span>
                            <span class="text-gray-200">当家門</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">契約日</span>
                            <span class="text-gray-200">{{ hiredDate }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">給与（取り分）</span>
                            <span class="text-gray-200">{{ (c.reward_share_bp / 100).toFixed(1) }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">租借中</span>
                            <span class="text-gray-200">—</span>
                        </div>
                    </div>

                    <h3 class="font-bold text-gray-200 pt-2 border-t border-gray-700/50">装備</h3>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <div class="rounded bg-gray-900/50 border border-gray-700/30 p-3 text-center">
                            <div class="text-xs text-gray-500">武器</div>
                            <div class="text-gray-400">未装備</div>
                        </div>
                        <div class="rounded bg-gray-900/50 border border-gray-700/30 p-3 text-center">
                            <div class="text-xs text-gray-500">防具</div>
                            <div class="text-gray-400">未装備</div>
                        </div>
                        <div class="rounded bg-gray-900/50 border border-gray-700/30 p-3 text-center">
                            <div class="text-xs text-gray-500">装飾品</div>
                            <div class="text-gray-400">未装備</div>
                        </div>
                    </div>
                </section>

                <!-- スキル -->
                <section class="md:col-span-2 rounded-2xl bg-gray-800/60 border border-gray-700/50 p-5 space-y-4">
                    <h2 class="font-bold text-gray-200">スキル</h2>
                    <div v-if="c.skills.length === 0" class="text-sm text-gray-500">習得済みスキルはありません。</div>
                    <div v-else class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="skill in c.skills" :key="skill.id"
                             class="rounded-lg border border-emerald-700/50 bg-emerald-950/20 p-3">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-sm">{{ skill.name }}</span>
                                <span class="text-[10px] text-gray-500">{{ jobName(skill.job) ?? skill.job }}</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1 truncate">{{ skill.description }}</p>
                            <div class="mt-2 flex gap-2 text-[10px] text-gray-500">
                                <span>威力 {{ skill.power }}</span>
                                <span>{{ targetLabel(skill.target_type) }}</span>
                                <span>{{ elementLabel(skill.element) }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 戦闘履歴 -->
                <section class="md:col-span-2 rounded-2xl bg-gray-800/60 border border-gray-700/50 p-5 space-y-4">
                    <h2 class="font-bold text-gray-200">戦闘履歴</h2>
                    <div v-if="!c.battle_history || c.battle_history.length === 0" class="text-sm text-gray-500">
                        まだ戦闘記録がありません。
                    </div>
                    <ul v-else class="space-y-2 text-sm">
                        <li v-for="log in c.battle_history" :key="log.id" class="border-b border-gray-700/30 pb-2 last:border-0">
                            <span class="text-gray-500">{{ new Date(log.created_at).toLocaleString('ja-JP') }}</span>
                            <p class="text-gray-300 mt-0.5">{{ log.summary }}</p>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </main>
</template>
