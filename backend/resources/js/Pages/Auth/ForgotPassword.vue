<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String },
});

const form = useForm({ email: '' });

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="パスワード再設定" />

        <h2 class="text-xl font-semibold text-center mb-4">パスワード再設定</h2>

        <p class="mb-4 text-sm text-gray-400 leading-relaxed">
            登録済みのメールアドレスを入力してください。パスワード再設定用のリンクをお送りします。
        </p>

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

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-3 rounded-xl bg-indigo-500 hover:bg-indigo-400 transition font-semibold tracking-wide disabled:opacity-50"
            >
                {{ form.processing ? '送信中...' : '再設定リンクを送信' }}
            </button>
        </form>
    </GuestLayout>
</template>
