<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PaginationLink } from '@/types/admin';

defineProps<{
    links: PaginationLink[];
}>();
</script>

<template>
    <nav v-if="links.length > 3" class="mt-4 flex flex-wrap gap-1">
        <template v-for="(link, index) in links" :key="index">
            <span
                v-if="link.url === null"
                class="rounded-md px-3 py-1 text-sm text-gray-400 dark:text-neutral-600"
                v-html="link.label"
            />
            <Link
                v-else
                :href="link.url"
                class="rounded-md px-3 py-1 text-sm"
                :class="
                    link.active
                        ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                        : 'text-gray-600 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-800'
                "
            >
                <span v-html="link.label" />
            </Link>
        </template>
    </nav>
</template>
