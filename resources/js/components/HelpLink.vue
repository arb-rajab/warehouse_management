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
        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-muted hover:bg-subtle hover:text-ink"
    >
        <CircleHelp class="h-5 w-5" />
    </Link>
</template>
