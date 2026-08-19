<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import type { PaginationLink } from '@/types/admin';

const props = defineProps<{
    links: PaginationLink[];
}>();

/**
 * Laravel's paginator always places the "Previous" link first and the
 * "Next" link last in the `links` array, with page-number links in between
 * — so position, not the (locale-independent, backend-supplied) label text,
 * is what identifies them.
 */
function isPrevious(index: number): boolean {
    return index === 0;
}

function isNext(index: number): boolean {
    return index === props.links.length - 1;
}

function linkClass(link: PaginationLink): string {
    if (link.url === null) {
        return 'text-gray-400 dark:text-neutral-600';
    }

    return link.active
        ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
        : 'text-gray-600 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-800';
}
</script>

<template>
    <nav v-if="links.length > 3" class="mt-4 flex flex-wrap gap-1">
        <template v-for="(link, index) in links" :key="index">
            <component
                :is="link.url === null ? 'span' : Link"
                :href="link.url === null ? undefined : link.url"
                class="inline-flex items-center gap-1 rounded-md px-3 py-1 text-sm"
                :class="linkClass(link)"
            >
                <ChevronLeft
                    v-if="isPrevious(index)"
                    class="h-3.5 w-3.5 shrink-0 rtl:rotate-180"
                />
                <span v-html="link.label" />
                <ChevronRight
                    v-if="isNext(index)"
                    class="h-3.5 w-3.5 shrink-0 rtl:rotate-180"
                />
            </component>
        </template>
    </nav>
</template>
