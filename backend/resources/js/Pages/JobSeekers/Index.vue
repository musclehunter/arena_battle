<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    seekers: { type: Array, required: true },
    viewer: { type: Object, required: true },
});

const hireForm = useForm({ character_id: null });
const guestHireForm = useForm({ character_id: null });

const hireByHouse = (characterId) => {
    hireForm.character_id = characterId;
    hireForm.post(route('houses.hire'));
};

const hireAsGuest = (characterId) => {
    guestHireForm.character_id = characterId;
    guestHireForm.post(route('guest-hires.store'));
};

const houseCanHire = (cost) => {
    if (!props.viewer.has_house) return false;
    const h = props.viewer.house;
    return h.gold >= cost && h.hired_count < h.hire_slots;
};

const guestCanHire = (cost) => {
    const purseGold = props.viewer.has_house ? props.viewer.house.gold : props.viewer.guest.gold;
    // ゲスト雇用中なら新規ゲスト雇用は不可
    const guestBusy = !props.viewer.has_house && props.viewer.guest.hired_character_id;
    return !guestBusy && purseGold >= cost;
};

const homeHref = computed(() => props.viewer.has_house ? route('houses.mine') : route('home'));

const errorMessage = computed(() =>
    hireForm.errors.hire
    || hireForm.errors.character_id
    || guestHireForm.errors.hire
    || guestHireForm.errors.character_id
    || ''
);
</script>

<template>
    <Head title="求職者" />

    <div class="min-h-screen bg-gray-900 text-gray-100 p-4 md:p-8">
        <div class="max-w-3xl mx-auto space-y-4">
            <header class="flex items-center justify-between">
                <h1 class="text-2xl font-bold tracking-wide">求職者</h1>
                <div class="text-right text-sm">
                    <div v-if="viewer.has_house" class="text-amber-300 font-semibold">
                        家門所持金: {{ viewer.house.gold }} G
                    </div>
                    <div v-else class="text-amber-300 font-semibold">
                        ゲスト所持金: {{ viewer.guest.gold }} G
                    </div>
                    <div v-if="viewer.has_house" class="text-xs text-gray-400">
                        雇用枠: {{ viewer.house.hired_count }}/{{ viewer.house.hire_slots }}
                    </div>
                </div>
            </header>

            <p class="text-xs text-gray-400">
                このリストは次のアリーナバトル完了時に再抽選されます。家門雇用の場合は残り人数が減るだけです。
            </p>

            <section v-if="seekers.length === 0"
                     class="bg-gray-800 rounded-xl p-8 text-center text-gray-400">
                求職者がいません。
            </section>

            <section v-else class="grid md:grid-cols-3 gap-3">
                <div v-for="s in seekers" :key="s.id"
                     class="bg-gray-800 rounded-xl p-4 flex flex-col gap-3">
                    <div>
                        <div class="font-bold truncate">{{ s.name }}</div>
                        <div class="text-xs text-gray-400">{{ s.preset.name }} / Lv.{{ s.level }}</div>
                    </div>
                    <div class="text-xs text-gray-400 space-y-0.5">
                        <div>HP {{ s.stats.hp_max }} / ATK {{ s.stats.atk }} / DEF {{ s.stats.def }}</div>
                        <div class="text-[10px] text-gray-500">力{{ s.stats.str }} 体{{ s.stats.vit }} 器{{ s.stats.dex }} 魔{{ s.stats.int_stat }}</div>
                        <div>EXP {{ s.exp }} / {{ s.next_exp }}</div>
                        <div>取り分 {{ (s.reward_share_bp / 100).toFixed(1) }}%</div>
                        <div>自己資金 {{ s.gold }} G</div>
                    </div>
                    <div class="flex-1"></div>
                    <div class="space-y-2">
                        <button v-if="viewer.has_house"
                                @click="hireByHouse(s.id)"
                                :disabled="!houseCanHire(s.hire_cost) || hireForm.processing"
                                class="w-full py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed">
                            家門で雇用 ({{ s.hire_cost }} G)
                        </button>
                        <button @click="hireAsGuest(s.id)"
                                :disabled="!guestCanHire(s.guest_hire_cost) || guestHireForm.processing"
                                class="w-full py-2 rounded-lg bg-indigo-500 hover:bg-indigo-400 text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed">
                            ゲスト雇用して挑む ({{ s.guest_hire_cost }} G)
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="errorMessage" class="text-sm text-rose-400 text-center">
                {{ errorMessage }}
            </div>

            <div class="text-center">
                <Link :href="homeHref" class="text-xs text-gray-400 hover:text-gray-200">戻る</Link>
            </div>
        </div>
    </div>
</template>
