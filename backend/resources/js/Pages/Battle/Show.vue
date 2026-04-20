<script setup>
import BattleActionButtons from '@/Components/Battle/BattleActionButtons.vue';
import BattleLogPanel from '@/Components/Battle/BattleLogPanel.vue';
import BattleResultPanel from '@/Components/Battle/BattleResultPanel.vue';
import BattleStatusPanel from '@/Components/Battle/BattleStatusPanel.vue';
import { Head, useForm } from '@inertiajs/vue3';
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

    <div class="min-h-screen bg-gray-900 text-gray-100 p-4 md:p-8">
        <div class="max-w-3xl mx-auto space-y-4">
            <header class="flex items-center justify-between">
                <h1 class="text-2xl font-bold tracking-wide">1v1 Arena</h1>
                <div class="text-xs text-gray-400">
                    <span v-if="battle.is_guest_battle" class="mr-2 px-2 py-0.5 rounded bg-indigo-900 text-indigo-200">GUEST</span>
                    Battle #{{ battle.id }}
                </div>
            </header>

            <section class="grid grid-cols-2 gap-3">
                <BattleStatusPanel
                    label="Player"
                    :name="battle.player.name ?? '?'"
                    :hp="battle.player.hp"
                    :max-hp="battle.player.max_hp"
                    :level="battle.player.level"
                    :stats="battle.player.stats"
                    color="emerald"
                />
                <BattleStatusPanel
                    label="Enemy"
                    :name="battle.enemy.name ?? '?'"
                    :hp="battle.enemy.hp"
                    :max-hp="battle.enemy.max_hp"
                    :level="battle.enemy.level"
                    :stats="battle.enemy.stats"
                    color="rose"
                />
            </section>

            <section class="bg-gray-800 rounded-xl px-4 py-2 flex justify-between text-sm">
                <span>Turn: <span class="font-semibold">{{ battle.turn_number }}</span></span>
                <span class="text-gray-400">Status: {{ battle.status }}</span>
            </section>

            <BattleLogPanel :logs="battle.logs" />

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
                restart-label="同じキャラで再戦"
                @restart="startNewBattle"
            />

            <div v-if="errorMessage" class="text-sm text-rose-400 text-center">
                {{ errorMessage }}
            </div>
        </div>
    </div>
</template>
