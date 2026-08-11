<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';

defineProps<{
    href: string | UrlMethodPair;
}>();
</script>

<template>
    <Link
        :href="href"
        class="inline-flex items-center gap-1 text-blue-600 hover:underline dark:text-blue-400"
    >
        <slot />
        <ArrowUpRight class="h-3 w-3 shrink-0" />
    </Link>
</template>
