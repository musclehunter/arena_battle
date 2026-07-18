<script setup>
import BattleActionButtons from '@/Components/Battle/BattleActionButtons.vue';
import BattleLogPanel from '@/Components/Battle/BattleLogPanel.vue';
import BattleResultPanel from '@/Components/Battle/BattleResultPanel.vue';
import BattleStatusPanel from '@/Components/Battle/BattleStatusPanel.vue';
import CharacterIcon from '@/Components/CharacterIcon.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    battle: {
        type: Object,
        required: true,
    },
});

const isFinished = computed(() => props.battle.status === 'finished');
const canRestart = computed(() => isFinished.value && !props.battle.is_guest_battle);

const form = useForm({
    action: null,
    token: props.battle.action_token,
});

const restartForm = useForm({});

const submitAction = (action) => {
    if (isFinished.value || form.processing) return;
    form
        .transform(() => ({
            action,
            token: props.battle.action_token,
        }))
        .post(route('battles.turn', { battle: props.battle.id }), {
            preserveScroll: true,
        });
};

const startNewBattle = () => {
    restartForm.post(route('battles.restart', { battle: props.battle.id }));
};

const homeHref = computed(() => route('home'));

const errorMessage = computed(
    () =>
        form.errors.token
        || form.errors.action
        || form.errors.status
        || restartForm.errors.restart
        || '',
);
</script>

<template>
    <Head :title="`Battle #${battle.id}`" />

    <div class="min-h-screen bg-gray-900 text-gray-100 p-3 md:p-6">
        <div class="max-w-6xl mx-auto space-y-4">
            <!-- トップバー -->
            <header class="flex items-center justify-between bg-gray-800/60 rounded-xl px-4 py-3">
                <div class="flex items-center gap-3">
                    <Link :href="homeHref" class="text-gray-400 hover:text-white">&larr;</Link>
                    <div>
                        <h1 class="text-lg font-bold tracking-wide">闘技場</h1>
                        <div class="text-xs text-gray-400">Battle #{{ battle.id }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-xs md:text-sm text-gray-300">
                    <span>Turn <span class="font-bold text-white">{{ battle.turn_number }}</span></span>
                    <span class="px-2 py-0.5 rounded bg-gray-700">{{ battle.is_guest_battle ? 'GUEST' : 'HOUSE' }}</span>
                    <span v-if="battle.status === 'finished'" class="px-2 py-0.5 rounded bg-gray-700">終了</span>
                </div>
            </header>

            <!-- メイン戦闘エリア -->
            <section class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <!-- 味方カラム -->
                <div class="lg:col-span-3 space-y-3">
                    <BattleStatusPanel
                        label="味方"
                        :name="battle.player.name ?? '?'"
                        :hp="battle.player.hp"
                        :max-hp="battle.player.max_hp"
                        :level="battle.player.level"
                        :stats="battle.player.stats"
                        color="emerald"
                    />
                    <CharacterIcon
                        :icon-key="battle.player.icon_key"
                        :icon-index="battle.player.icon_index ?? 0"
                        :gender="battle.player.gender"
                        :name="battle.player.name"
                        :size="160"
                        class="mx-auto"
                    />
                </div>

                <!-- 俯瞰フィールド -->
                <div class="lg:col-span-6 relative">
                    <div class="aspect-video rounded-2xl overflow-hidden bg-gray-800 relative border border-gray-700 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-slate-700 via-gray-800 to-gray-900">
                        <div class="absolute inset-0 bg-[url('/images/backgrounds/arena.png')] bg-cover bg-center opacity-20"></div>
                        <div class="absolute inset-0 bg-gradient-to-b from-gray-900/30 to-gray-900/70"></div>

                        <!-- フィールド上のキャラ配置 -->
                        <div class="absolute inset-0 flex items-center justify-around px-8 md:px-16">
                            <div class="flex flex-col items-center gap-2">
                                <div class="relative">
                                    <CharacterIcon
                                        :icon-key="battle.player.icon_key"
                                        :icon-index="battle.player.icon_index ?? 0"
                                        :gender="battle.player.gender"
                                        :name="battle.player.name"
                                        :size="80"
                                    />
                                    <!-- 円形ATB -->
                                    <svg class="absolute -inset-2 w-[96px] h-[96px] -rotate-90 pointer-events-none" viewBox="0 0 36 36">
                                        <path class="text-gray-700" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" />
                                        <path class="text-emerald-400" stroke-dasharray="0, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" />
                                    </svg>
                                </div>
                                <div class="text-xs font-semibold text-emerald-300">{{ battle.player.name }}</div>
                                <div class="text-[10px] text-gray-400">Lv.{{ battle.player.level }}</div>
                            </div>

                            <div class="text-4xl text-gray-600 font-black">VS</div>

                            <div class="flex flex-col items-center gap-2">
                                <div class="relative">
                                    <CharacterIcon
                                        :icon-key="battle.enemy.icon_key"
                                        :icon-index="battle.enemy.icon_index ?? 0"
                                        :gender="battle.enemy.gender"
                                        :name="battle.enemy.name"
                                        :size="80"
                                    />
                                    <svg class="absolute -inset-2 w-[96px] h-[96px] -rotate-90 pointer-events-none" viewBox="0 0 36 36">
                                        <path class="text-gray-700" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" />
                                        <path class="text-rose-400" stroke-dasharray="0, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" />
                                    </svg>
                                </div>
                                <div class="text-xs font-semibold text-rose-300">{{ battle.enemy.name }}</div>
                                <div class="text-[10px] text-gray-400">Lv.{{ battle.enemy.level }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- アクションバー -->
                    <div class="mt-4 bg-gray-800/60 rounded-xl p-3 space-y-3">
                        <BattleActionButtons
                            v-if="!isFinished"
                            :disabled="form.processing"
                            @submit="submitAction"
                        />
                        <BattleResultPanel
                            v-else
                            :winner="battle.winner"
                            :reward="battle.reward"
                            :can-restart="canRestart"
                            :processing="restartForm.processing"
                            :home-href="homeHref"
                            restart-label="同じ戦士で再戦"
                            @restart="startNewBattle"
                        />

                        <div v-if="errorMessage" class="text-sm text-rose-400 text-center">
                            {{ errorMessage }}
                        </div>
                    </div>
                </div>

                <!-- 敵カラム -->
                <div class="lg:col-span-3 space-y-3">
                    <CharacterIcon
                        :icon-key="battle.enemy.icon_key"
                        :icon-index="battle.enemy.icon_index ?? 0"
                        :gender="battle.enemy.gender"
                        :name="battle.enemy.name"
                        :size="160"
                        class="mx-auto"
                    />
                    <BattleStatusPanel
                        label="敵"
                        :name="battle.enemy.name ?? '?'"
                        :hp="battle.enemy.hp"
                        :max-hp="battle.enemy.max_hp"
                        :level="battle.enemy.level"
                        :stats="battle.enemy.stats"
                        color="rose"
                    />
                </div>
            </section>

            <!-- ログ -->
            <BattleLogPanel :logs="battle.logs" />
        </div>
    </div>
</template>
