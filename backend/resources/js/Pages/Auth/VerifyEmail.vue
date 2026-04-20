<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: String },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <GuestLayout>
        <Head title="メール認証" />

        <h2 class="text-xl font-semibold text-center mb-4">メール認証</h2>

        <p class="mb-4 text-sm text-gray-400 leading-relaxed">
            登録ありがとうございます！開始する前に、先ほど送信したメール内のリンクをクリックしてメールアドレスを認証してください。届いていない場合は下のボタンから再送できます。
        </p>

        <div
            v-if="verificationLinkSent"
            class="mb-4 text-sm font-medium text-emerald-400 text-center"
        >
            新しい認証リンクを送信しました。
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-3 rounded-xl bg-indigo-500 hover:bg-indigo-400 transition font-semibold tracking-wide disabled:opacity-50"
            >
                {{ form.processing ? '送信中...' : '認証メールを再送' }}
            </button>

            <div class="text-center">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-xs text-gray-400 hover:text-gray-200 underline"
                >
                    ログアウト
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
