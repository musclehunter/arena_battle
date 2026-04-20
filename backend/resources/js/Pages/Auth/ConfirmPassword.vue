<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ password: '' });

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="パスワード確認" />

        <h2 class="text-xl font-semibold text-center mb-4">パスワード確認</h2>

        <p class="mb-4 text-sm text-gray-400 leading-relaxed">
            ここから先は保護された操作です。続行する前にパスワードを再入力してください。
        </p>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="password" class="block text-sm text-gray-300 mb-1">パスワード</label>
                <input
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autofocus
                    autocomplete="current-password"
                    class="w-full px-3 py-2 rounded-lg bg-gray-900 border border-gray-700 focus:border-indigo-500 focus:outline-none text-gray-100"
                />
                <p v-if="form.errors.password" class="text-xs text-rose-400 mt-1">{{ form.errors.password }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-3 rounded-xl bg-indigo-500 hover:bg-indigo-400 transition font-semibold tracking-wide disabled:opacity-50"
            >
                {{ form.processing ? '確認中...' : '確認する' }}
            </button>
        </form>
    </GuestLayout>
</template>
