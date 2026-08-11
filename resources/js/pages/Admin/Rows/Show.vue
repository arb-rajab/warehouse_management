<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarPlus, CalendarX, Pencil } from '@lucide/vue';
import { computed } from 'vue';
import { edit } from '@/actions/App/Http/Controllers/Admin/RowController';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import type { Cell, Row } from '@/types/admin';

const props = defineProps<{
    row: Row;
    cells: Cell[];
}>();

const grid = computed(() => {
    const byCoordinate = new Map(
        props.cells.map((cell) => [
            `${cell.cell_number}-${cell.flat_number}`,
            cell,
        ]),
    );

    return Array.from({ length: props.row.cells_count }, (_, cellIndex) =>
        Array.from({ length: props.row.flats_count }, (_, flatIndex) => ({
            flatNumber: flatIndex + 1,
            cell: byCoordinate.get(`${cellIndex + 1}-${flatIndex + 1}`) ?? null,
        })).reverse(),
    );
});

const flatNumbers = computed(() =>
    Array.from(
        { length: props.row.flats_count },
        (_, flatIndex) => flatIndex + 1,
    ).reverse(),
);

const cellNumbers = computed(() =>
    Array.from({ length: props.row.cells_count }, (_, i) => i + 1),
);

const stateClasses: Record<Cell['state'], string> = {
    empty: 'border-gray-200 bg-white dark:border-neutral-800 dark:bg-neutral-900',
    full: 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950',
    opened: 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950',
};
</script>

<template>
    <Head :title="t('rows.show.title', { letter: props.row.letter })" />

    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">
                {{ t('rows.show.title', { letter: props.row.letter }) }}
            </h1>
            <Link
                :href="edit(props.row)"
                class="inline-flex items-center gap-1 text-sm text-gray-600 hover:underline dark:text-neutral-400"
            >
                <Pencil class="h-3.5 w-3.5" />
                {{ t('rows.show.editRow') }}
            </Link>
        </div>

        <div class="overflow-x-auto pb-2">
            <div class="flex flex-col gap-2">
                <div class="flex gap-2">
                    <div
                        class="sticky start-0 z-10 h-6 w-12 shrink-0 bg-gray-50 dark:bg-neutral-950"
                    ></div>
                    <div
                        v-for="cellNumber in cellNumbers"
                        :key="cellNumber"
                        data-testid="cell-header"
                        class="flex h-6 w-32 shrink-0 items-center justify-center text-xs font-medium text-gray-500 dark:text-neutral-400"
                    >
                        {{ t('rows.show.column', { n: cellNumber }) }}
                    </div>
                </div>

                <div class="flex gap-2">
                    <div
                        class="sticky start-0 z-10 flex flex-col gap-2 bg-gray-50 dark:bg-neutral-950"
                    >
                        <div
                            v-for="flatNumber in flatNumbers"
                            :key="flatNumber"
                            data-testid="flat-header"
                            class="flex h-28 w-12 shrink-0 items-center justify-center text-xs font-medium text-gray-500 dark:text-neutral-400"
                        >
                            {{ t('rows.show.flat', { n: flatNumber }) }}
                        </div>
                    </div>
                    <div
                        v-for="(flatColumn, cellIndex) in grid"
                        :key="cellIndex"
                        class="flex flex-col gap-2"
                    >
                        <div
                            v-for="entry in flatColumn"
                            :key="entry.flatNumber"
                            class="group relative flex h-28 w-32 shrink-0 flex-col justify-between rounded-md border p-2 text-xs"
                            :class="
                                entry.cell
                                    ? stateClasses[entry.cell.state]
                                    : 'border-dashed border-gray-200 dark:border-neutral-800'
                            "
                        >
                            <span
                                class="font-medium text-gray-500 dark:text-neutral-400"
                            >
                                {{
                                    formatSlot(
                                        props.row.letter,
                                        cellIndex + 1,
                                        entry.flatNumber,
                                    )
                                }}
                            </span>

                            <template v-if="entry.cell?.pallet">
                                <img
                                    v-if="entry.cell.pallet.product_image_url"
                                    :src="entry.cell.pallet.product_image_url"
                                    :alt="entry.cell.pallet.product_name"
                                    class="mb-1 h-8 w-8 rounded object-cover"
                                />
                                <div
                                    class="truncate font-medium text-gray-900 dark:text-neutral-100"
                                >
                                    {{ entry.cell.pallet.product_name }}
                                </div>
                                <div class="space-y-0.5">
                                    <div
                                        class="flex items-center gap-1 text-xs text-gray-500 dark:text-neutral-400"
                                    >
                                        <CalendarX class="h-3 w-3 shrink-0" />
                                        {{
                                            formatDate(
                                                entry.cell.pallet
                                                    .expiration_date,
                                            )
                                        }}
                                    </div>
                                    <div
                                        class="flex items-center gap-1 text-xs text-gray-400 dark:text-neutral-500"
                                    >
                                        <CalendarPlus
                                            class="h-3 w-3 shrink-0"
                                        />
                                        {{
                                            formatDateTime(
                                                entry.cell.pallet.added_at,
                                            )
                                        }}
                                    </div>
                                </div>
                            </template>
                            <span
                                v-else
                                class="text-gray-400 dark:text-neutral-600"
                                >{{ t('rows.show.empty') }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
