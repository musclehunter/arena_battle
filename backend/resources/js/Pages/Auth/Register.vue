<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="新規登録" />

        <h2 class="text-xl font-semibold text-center mb-6">新規登録</h2>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="name" class="block text-sm text-gray-300 mb-1">ユーザー名</label>
                <input
                    id="name"
                    type="text"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    class="w-full px-3 py-2 rounded-lg bg-gray-900 border border-gray-700 focus:border-indigo-500 focus:outline-none text-gray-100"
                />
                <p v-if="form.errors.name" class="text-xs text-rose-400 mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="email" class="block text-sm text-gray-300 mb-1">メールアドレス</label>
                <input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    class="w-full px-3 py-2 rounded-lg bg-gray-900 border border-gray-700 focus:border-indigo-500 focus:outline-none text-gray-100"
                />
                <p v-if="form.errors.email" class="text-xs text-rose-400 mt-1">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="block text-sm text-gray-300 mb-1">パスワード</label>
                <input
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    class="w-full px-3 py-2 rounded-lg bg-gray-900 border border-gray-700 focus:border-indigo-500 focus:outline-none text-gray-100"
                />
                <p v-if="form.errors.password" class="text-xs text-rose-400 mt-1">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm text-gray-300 mb-1">パスワード(確認)</label>
                <input
                    id="password_confirmation"
                    type="password"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full px-3 py-2 rounded-lg bg-gray-900 border border-gray-700 focus:border-indigo-500 focus:outline-none text-gray-100"
                />
                <p v-if="form.errors.password_confirmation" class="text-xs text-rose-400 mt-1">{{ form.errors.password_confirmation }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-3 rounded-xl bg-indigo-500 hover:bg-indigo-400 active:bg-indigo-600 transition font-semibold tracking-wide disabled:opacity-50"
            >
                {{ form.processing ? '登録中...' : '登録する' }}
            </button>

            <div class="text-center pt-2">
                <Link :href="route('login')" class="text-xs text-gray-400 hover:text-gray-200 underline">
                    既にアカウントをお持ちの方はログイン
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
