<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="パスワード再設定" />

        <h2 class="text-xl font-semibold text-center mb-6">新しいパスワードを設定</h2>

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
                <label for="password" class="block text-sm text-gray-300 mb-1">新しいパスワード</label>
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
                <label for="password_confirmation" class="block text-sm text-gray-300 mb-1">新しいパスワード(確認)</label>
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
                class="w-full py-3 rounded-xl bg-indigo-500 hover:bg-indigo-400 transition font-semibold tracking-wide disabled:opacity-50"
            >
                {{ form.processing ? '更新中...' : 'パスワードを更新' }}
            </button>
        </form>
    </GuestLayout>
</template>
