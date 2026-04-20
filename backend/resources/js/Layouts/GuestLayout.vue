<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-gray-900 text-gray-100 flex flex-col items-center justify-center p-6">
        <Link href="/" class="mb-6 text-center">
            <h1 class="text-3xl font-bold tracking-wide bg-gradient-to-r from-amber-300 to-rose-400 bg-clip-text text-transparent">
                1v1 Arena
            </h1>
            <p class="text-xs text-gray-400 mt-1">雇用して戦う。勝って稼ぐ。</p>
        </Link>

        <div class="w-full sm:max-w-md bg-gray-800 rounded-2xl shadow-2xl p-8 border border-gray-700">
            <slot />
        </div>
    </div>
</template>
