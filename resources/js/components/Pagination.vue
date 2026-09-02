<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { t } from '@/lib/i18n';
import type { PaginationLink } from '@/types/admin';

/**
 * The page-size choices offered by the "per page" selector — kept in sync
 * with the backend's PerPageOptions::VALUES by hand (see
 * app/Http/Controllers/Concerns/PerPageOptions.php).
 */
const PER_PAGE_OPTIONS = [10, 20, 25, 50, 100];

const props = defineProps<{
    links: PaginationLink[];
    /**
     * The listing's current page size. Omit to render pagination links only
     * (no per-page selector) — used by any caller that doesn't support
     * changing the page size.
     */
    perPage?: number;
}>();

const emit = defineEmits<{
    'update:perPage': [value: number];
}>();

function onPerPageChange(event: Event): void {
    emit('update:perPage', Number((event.target as HTMLSelectElement).value));
}

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
    <div
        v-if="links.length > 3 || perPage !== undefined"
        class="mt-4 flex flex-wrap items-center justify-between gap-3"
    >
        <nav v-if="links.length > 3" class="flex flex-wrap gap-1">
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

        <label
            v-if="perPage !== undefined"
            class="flex items-center gap-2 text-sm text-gray-600 dark:text-neutral-400"
        >
            {{ t('common.pagination.perPage') }}
            <select
                :value="perPage"
                class="cursor-pointer rounded-md border border-gray-300 bg-white px-2 py-1 text-sm text-gray-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
                @change="onPerPageChange"
            >
                <option
                    v-for="option in PER_PAGE_OPTIONS"
                    :key="option"
                    :value="option"
                >
                    {{ option }}
                </option>
            </select>
        </label>
    </div>
</template>
