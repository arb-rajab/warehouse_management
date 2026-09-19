<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Warehouse } from '@lucide/vue';
import { t } from '@/lib/i18n';
</script>

<template>
    <Head :title="t('welcome.brand')" />

    <div
        class="flex min-h-screen items-center justify-center bg-gray-50 text-gray-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <div class="flex flex-col items-center gap-2 text-center">
            <Warehouse class="h-8 w-8 shrink-0" />
            <h1 class="text-lg font-semibold">{{ t('welcome.brand') }}</h1>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
                {{ t('welcome.message') }}
            </p>
        </div>
    </div>
</template>
