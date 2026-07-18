<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import CharacterIcon from '@/Components/CharacterIcon.vue';

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

const genderText = { male: '男', female: '女', unknown: '不明' };
</script>

<template>
    <Head title="契約者名簿" />

    <div class="min-h-screen bg-gray-900 text-gray-100 p-3 sm:p-4 md:p-8">
        <div class="max-w-6xl mx-auto space-y-4">
            <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black tracking-tight">契約者名簿</h1>
                    <p class="text-xs text-gray-400 mt-1">遠征の帰還後に、名簿の顔ぶれが入れ替わります</p>
                </div>
                <div class="text-right bg-gray-800/60 border border-gray-700/50 rounded-xl px-5 py-3">
                    <div v-if="viewer.has_house" class="text-amber-300 font-bold text-lg">
                        家門資金: {{ viewer.house.gold }} G
                    </div>
                    <div v-else class="text-amber-300 font-bold text-lg">
                        遠征資金: {{ viewer.guest.gold }} G
                    </div>
                    <div v-if="viewer.has_house" class="text-xs text-gray-400">
                        雇用枠: {{ viewer.house.hired_count }}/{{ viewer.house.hire_slots }}
                    </div>
                </div>
            </header>

            <section v-if="seekers.length === 0"
                     class="bg-gray-800/60 rounded-2xl p-8 text-center text-gray-400 border border-gray-700/50">
現在、契約可能な者はいません。
            </section>

            <section v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="s in seekers" :key="s.id"
                     class="bg-gray-800/60 rounded-2xl p-5 flex flex-col gap-4 border border-gray-700/50 hover:border-gray-600/50 transition">
                    <div class="flex items-center gap-4">
                        <CharacterIcon
                            :icon-key="s.preset.icon_key"
                            :icon-index="s.icon_index"
                            :gender="s.gender"
                            :name="s.name"
                            :size="80"
                        />
                        <div class="min-w-0">
                            <div class="font-bold text-lg truncate">{{ s.name }}</div>
                            <div class="text-xs text-gray-400">{{ s.preset.name }} / Lv.{{ s.level }} / {{ genderText[s.gender] || '不明' }}</div>
                        </div>
                    </div>

                    <div class="text-sm text-gray-300 space-y-1">
                        <div class="flex justify-between">
                            <span>HP</span>
                            <span class="font-semibold">{{ s.stats.hp_max }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>ATK / DEF</span>
                            <span class="font-semibold">{{ s.stats.atk }} / {{ s.stats.def }}</span>
                        </div>
                        <div class="text-[10px] text-gray-500 flex justify-between">
                            <span>力{{ s.stats.str }} 体{{ s.stats.vit }} 器{{ s.stats.dex }} 魔{{ s.stats.int_stat }}</span>
                        </div>
                        <div class="text-[10px] text-gray-500 flex justify-between">
                            <span>EXP {{ s.exp }} / {{ s.next_exp }}</span>
                        </div>
                        <div class="text-[10px] text-gray-500 flex justify-between">
                            <span>取り分 {{ (s.reward_share_bp / 100).toFixed(1) }}%</span>
                            <span>自己資金 {{ s.gold }} G</span>
                        </div>
                    </div>

                    <div class="mt-auto space-y-2">
                        <button v-if="viewer.has_house"
                                @click="hireByHouse(s.id)"
                                :disabled="!houseCanHire(s.hire_cost) || hireForm.processing"
                                class="w-full py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-600 text-sm font-bold disabled:opacity-40 disabled:cursor-not-allowed transition">
                            契約を結ぶ ({{ s.hire_cost }} G)
                        </button>
                        <button @click="hireAsGuest(s.id)"
                                :disabled="!guestCanHire(s.guest_hire_cost) || guestHireForm.processing"
                                class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-sm font-bold disabled:opacity-40 disabled:cursor-not-allowed transition">
                            一時契約で挑む ({{ s.guest_hire_cost }} G)
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="errorMessage" class="text-sm text-rose-400 text-center">
                {{ errorMessage }}
            </div>

            <div class="text-center pt-2">
                <Link :href="homeHref" class="text-sm text-gray-400 hover:text-gray-200 underline">ホームに戻る</Link>
            </div>
        </div>
    </div>
</template>
