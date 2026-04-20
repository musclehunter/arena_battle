<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="ログイン" />

        <h2 class="text-xl font-semibold text-center mb-6">ログイン</h2>

        <div v-if="status" class="mb-4 text-sm font-medium text-emerald-400 text-center">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm text-gray-300 mb-1">メールアドレス</label>
                <input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autofocus
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
                    autocomplete="current-password"
                    class="w-full px-3 py-2 rounded-lg bg-gray-900 border border-gray-700 focus:border-indigo-500 focus:outline-none text-gray-100"
                />
                <p v-if="form.errors.password" class="text-xs text-rose-400 mt-1">{{ form.errors.password }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-400 cursor-pointer select-none">
                <input
                    type="checkbox"
                    v-model="form.remember"
                    class="rounded bg-gray-900 border-gray-700 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-0"
                />
                <span>ログイン状態を保持する</span>
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-3 rounded-xl bg-indigo-500 hover:bg-indigo-400 active:bg-indigo-600 transition font-semibold tracking-wide disabled:opacity-50"
            >
                {{ form.processing ? 'ログイン中...' : 'ログイン' }}
            </button>

            <div class="flex items-center justify-between pt-2 text-xs">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-gray-400 hover:text-gray-200 underline"
                >
                    パスワードを忘れた方
                </Link>
                <Link :href="route('register')" class="text-gray-400 hover:text-gray-200 underline ms-auto">
                    新規登録
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
