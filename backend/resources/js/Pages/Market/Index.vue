<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const categories = [
    { key: 'weapon', label: '武器' },
    { key: 'armor', label: '防具' },
    { key: 'material', label: '素材' },
    { key: 'consumable', label: '消費アイテム' },
    { key: 'skill', label: 'スキル書' },
];
const activeCategory = ref('weapon');

const items = ref([
    { name: '鉄の剣', price: 350, seller: 'NPC商店', time: '残り24h' },
    { name: '革の鎧', price: 280, seller: 'NPC商店', time: '残り24h' },
    { name: '回復薬', price: 50, seller: 'NPC商店', time: '残り24h' },
    { name: '魔鉄の欠片', price: 120, seller: 'NPC商店', time: '残り24h' },
]);

const buy = (item) => alert(`購入処理は未実装です（${item.name}）`);
const listItem = () => alert('出品処理は未実装です');
</script>

<template>
    <Head title="市場" />
    <main class="min-h-screen bg-gray-900 p-4 text-gray-100 md:p-8">
        <div class="mx-auto max-w-5xl space-y-5">
            <header class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-black tracking-tight">市場</h1>
                    <p class="text-sm text-gray-400 mt-1">装備・素材の売買（v1 では NPC商店のみ表示）</p>
                </div>
                <Link :href="route('houses.mine')" class="text-sm text-gray-400 hover:text-white">家門へ戻る</Link>
            </header>

            <div class="flex flex-wrap gap-2">
                <button v-for="cat in categories" :key="cat.key" @click="activeCategory = cat.key"
                        :class="activeCategory === cat.key ? 'bg-amber-700 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition">
                    {{ cat.label }}
                </button>
            </div>

            <section class="rounded-2xl bg-gray-800/60 border border-gray-700/50 p-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <h2 class="font-bold text-gray-200">出品一覧</h2>
                    <button @click="listItem" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold hover:bg-emerald-600 transition">出品する</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-300">
                        <thead class="text-xs text-gray-500 uppercase border-b border-gray-700">
                            <tr>
                                <th class="px-3 py-2">アイテム</th>
                                <th class="px-3 py-2">価格</th>
                                <th class="px-3 py-2">出品者</th>
                                <th class="px-3 py-2">残り時間</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, i) in items" :key="i" class="border-b border-gray-800 hover:bg-gray-800/40">
                                <td class="px-3 py-3">{{ item.name }}</td>
                                <td class="px-3 py-3 text-amber-300 font-semibold">{{ item.price }} G</td>
                                <td class="px-3 py-3">{{ item.seller }}</td>
                                <td class="px-3 py-3 text-gray-500">{{ item.time }}</td>
                                <td class="px-3 py-3">
                                    <button @click="buy(item)" class="rounded bg-indigo-700 px-3 py-1 text-xs hover:bg-indigo-600 transition">購入</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl bg-gray-800/60 border border-gray-700/50 p-5">
                <h2 class="font-bold text-gray-200 mb-3">取引履歴</h2>
                <p class="text-sm text-gray-500">取引履歴は今後の拡張です。</p>
            </section>
        </div>
    </main>
</template>
