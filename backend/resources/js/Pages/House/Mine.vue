<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import CharacterIcon from '@/Components/CharacterIcon.vue';

const props = defineProps({
    house: { type: Object, required: true },
    characters: { type: Array, required: true },
    active_battle_id: { type: [Number, null], default: null },
});

const startForm = useForm({ character_id: null });
const releaseForm = useForm({});

const startBattle = (characterId) => {
    startForm.character_id = characterId;
    startForm.post(route('battles.store'));
};

const release = (characterId) => {
    if (!confirm('このキャラを解雇しますか？(キャラの所持金は持ち逃げされます)')) return;
    releaseForm.post(route('houses.release', { character: characterId }));
};
</script>

<template>
    <Head :title="`家門: ${house.name}`" />

    <div class="min-h-screen bg-gray-900 text-gray-100 p-4 md:p-8">
        <div class="max-w-3xl mx-auto space-y-4">
            <header class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-wide">{{ house.name }}</h1>
                    <div class="text-xs text-gray-400">家門 Lv.{{ house.level }}</div>
                </div>
                <div class="text-right">
                    <div class="text-lg font-bold text-amber-300">{{ house.gold }} G</div>
                    <div class="text-xs text-gray-400">雇用枠: {{ house.hired_count }}/{{ house.hire_slots }}</div>
                </div>
            </header>

            <!-- 進行中バトル -->
            <div v-if="active_battle_id" class="bg-amber-900/30 border border-amber-700 rounded-xl p-4 text-center text-sm">
                進行中のバトルがあります。
                <Link :href="route('battles.show', { battle: active_battle_id })"
                      class="underline text-amber-300 font-semibold ml-1">続きから再開</Link>
            </div>

            <!-- 雇用キャラ一覧 -->
            <section class="bg-gray-800 rounded-xl p-4 space-y-3">
                <h2 class="font-semibold text-gray-300">雇用キャラクター</h2>

                <div v-if="characters.length === 0" class="text-sm text-gray-500 py-4 text-center">
                    まだ誰も雇用していません。
                </div>

                <div v-for="c in characters" :key="c.id"
                     class="bg-gray-900/60 rounded-lg p-3 flex items-center justify-between gap-3">

                    <CharacterIcon
                        :icon-key="c.preset.icon_key"
                        :icon-index="c.icon_index"
                        :alt="c.preset.name"
                        :size="100" />

                    <div class="flex-1 min-w-0">
                        <div class="flex items-baseline gap-2">
                            <div class="font-semibold truncate">{{ c.name }}</div>
                            <div class="text-xs text-gray-400">{{ c.preset.name }} / Lv.{{ c.level }}</div>
                        </div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            HP {{ c.stats.hp_max }} / ATK {{ c.stats.atk }} / DEF {{ c.stats.def }}
                        </div>
                        <div class="text-[10px] text-gray-500 mt-0.5">
                            力{{ c.stats.str }} 体{{ c.stats.vit }} 器{{ c.stats.dex }} 魔{{ c.stats.int_stat }}
                        </div>
                        <div class="text-[10px] text-gray-500 mt-0.5">
                            EXP {{ c.exp }} / {{ c.next_exp }}
                        </div>
                        <div class="text-xs text-gray-500 mt-0.5">
                            取り分 {{ (c.reward_share_bp / 100).toFixed(1) }}% /
                            所持金 <span class="text-amber-300">{{ c.gold }} G</span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1 shrink-0">
                        <button @click="startBattle(c.id)"
                                :disabled="startForm.processing || active_battle_id"
                                class="px-3 py-1.5 rounded-lg bg-indigo-500 hover:bg-indigo-400 text-xs font-semibold disabled:opacity-40">
                            この子で挑む
                        </button>
                        <button @click="release(c.id)"
                                :disabled="releaseForm.processing"
                                class="px-3 py-1.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-xs disabled:opacity-40">
                            解雇
                        </button>
                    </div>
                </div>
            </section>

            <!-- ナビ -->
            <section class="flex gap-2">
                <Link :href="route('job-seekers.index')"
                      class="flex-1 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 transition font-semibold text-center">
                    求職者を見る
                </Link>
                <Link :href="route('profile.edit')"
                      class="px-4 py-3 rounded-xl bg-gray-700 hover:bg-gray-600 text-sm text-center">
                    プロフィール
                </Link>
            </section>

            <!-- エラー表示 -->
            <div v-if="startForm.errors.character_id || releaseForm.errors.release"
                 class="text-sm text-rose-400 text-center">
                {{ startForm.errors.character_id || releaseForm.errors.release }}
            </div>
        </div>
    </div>
</template>
