<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ name: '' });

const submit = () => {
    form.post(route('houses.store'));
};
</script>

<template>
    <Head title="家門を作成" />

    <div class="min-h-screen bg-gray-900 text-gray-100 p-6 flex items-center justify-center">
        <form @submit.prevent="submit"
              class="w-full max-w-md bg-gray-800 rounded-2xl shadow-2xl p-8 space-y-6">
            <header class="space-y-2 text-center">
                <h1 class="text-2xl font-bold tracking-wide">家門を作成</h1>
                <p class="text-xs text-gray-400">作成するとゲストの所持金 1000G は破棄され、家門 1000G で再スタートします。</p>
            </header>

            <div class="space-y-1">
                <label class="block text-sm text-gray-300" for="name">家門名</label>
                <input id="name" v-model="form.name"
                       type="text" maxlength="24" minlength="1" required
                       class="w-full px-3 py-2 rounded-lg bg-gray-900 border border-gray-700 focus:border-indigo-500 focus:outline-none text-gray-100" />
                <p v-if="form.errors.name" class="text-xs text-rose-400">{{ form.errors.name }}</p>
                <p class="text-xs text-gray-500">1〜24 文字。後から変更できません。</p>
            </div>

            <button type="submit"
                    :disabled="form.processing"
                    class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 transition font-semibold tracking-wide disabled:opacity-50">
                {{ form.processing ? '作成中...' : '家門を作成する' }}
            </button>

            <div class="text-center">
                <Link :href="route('home')" class="text-xs text-gray-400 hover:text-gray-200">戻る</Link>
            </div>
        </form>
    </div>
</template>
