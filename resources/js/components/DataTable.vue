<script setup lang="ts" generic="T extends { id: number }">
import { ArrowDown, ArrowUp, ArrowUpDown } from '@lucide/vue';

type Column = string | { label: string; sortKey: string };

defineProps<{
    columns: Column[];
    rows: T[];
    emptyMessage: string;
    sort?: { by: string; direction: 'asc' | 'desc' };
}>();

const emit = defineEmits<{
    sort: [sortKey: string];
}>();

function columnLabel(column: Column): string {
    return typeof column === 'string' ? column : column.label;
}

function sortKey(column: Column): string | null {
    return typeof column === 'string' ? null : column.sortKey;
}
</script>

<template>
    <div
        class="overflow-hidden rounded-lg border border-gray-200 dark:border-neutral-800"
    >
        <table class="w-full text-left text-sm">
            <thead
                class="bg-gray-50 text-gray-500 dark:bg-neutral-900 dark:text-neutral-400"
            >
                <tr>
                    <th
                        v-for="column in columns"
                        :key="columnLabel(column)"
                        class="px-4 py-2 font-medium"
                    >
                        <button
                            v-if="sortKey(column)"
                            type="button"
                            class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                            @click="emit('sort', sortKey(column)!)"
                        >
                            {{ columnLabel(column) }}
                            <ArrowUp
                                v-if="
                                    sort?.by === sortKey(column) &&
                                    sort.direction === 'asc'
                                "
                                class="h-3 w-3 shrink-0"
                            />
                            <ArrowDown
                                v-else-if="sort?.by === sortKey(column)"
                                class="h-3 w-3 shrink-0"
                            />
                            <ArrowUpDown
                                v-else
                                class="h-3 w-3 shrink-0 text-gray-300 dark:text-neutral-600"
                            />
                        </button>
                        <template v-else>{{ columnLabel(column) }}</template>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-neutral-800">
                <tr v-for="row in rows" :key="row.id">
                    <slot name="row" :row="row" />
                </tr>
                <tr v-if="rows.length === 0">
                    <td
                        :colspan="columns.length"
                        class="px-4 py-6 text-center text-gray-500 dark:text-neutral-400"
                    >
                        {{ emptyMessage }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
