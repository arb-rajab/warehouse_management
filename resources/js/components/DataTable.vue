<script setup lang="ts" generic="T extends { id: number }">
defineProps<{
    columns: string[];
    rows: T[];
    emptyMessage: string;
}>();
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
                        :key="column"
                        class="px-4 py-2 font-medium"
                    >
                        {{ column }}
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
