<script setup lang="ts">
import { t } from '@/lib/i18n';
</script>

<template>
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:fixed focus:start-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:font-medium focus:text-gray-900 focus:shadow-lg dark:focus:bg-neutral-900 dark:focus:text-white"
    >
        {{ t('nav.skipToContent') }}
    </a>
</template>
