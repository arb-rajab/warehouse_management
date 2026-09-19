<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import { CircleHelp } from '@lucide/vue';
import { t } from '@/lib/i18n';

defineProps<{
    href: string | UrlMethodPair;
}>();
</script>

<template>
    <Link
        :href="href"
        :title="t('help.viewHelp')"
        :aria-label="t('help.viewHelp')"
        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
    >
        <CircleHelp class="h-5 w-5" />
    </Link>
</template>
