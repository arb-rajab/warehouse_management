<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        label: string;
        value: number;
        href: string;
        query?: Record<string, FormDataConvertible>;
        tone?: 'default' | 'warning' | 'danger';
    }>(),
    {
        query: undefined,
        tone: 'default',
    },
);

const toneClasses: Record<'default' | 'warning' | 'danger', string> = {
    default: 'text-gray-900 dark:text-neutral-100',
    warning: 'text-amber-600 dark:text-amber-400',
    danger: 'text-red-600 dark:text-red-400',
};
</script>

<template>
    <Link
        :href="href"
        :data="query"
        method="get"
        class="block rounded-lg border border-gray-200 p-4 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-neutral-800 dark:hover:border-neutral-700 dark:hover:bg-neutral-900"
    >
        <div class="text-2xl font-semibold" :class="toneClasses[tone]">
            {{ value }}
        </div>
        <div class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
            {{ label }}
        </div>
    </Link>
</template>
