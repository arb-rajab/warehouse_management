<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed, reactive } from 'vue';
import { edit } from '@/actions/App/Http/Controllers/Admin/RowController';
import CellHighlightFilters from '@/components/CellHighlightFilters.vue';
import CellSlot from '@/components/CellSlot.vue';
import PageHeader from '@/components/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
    emptyCellHighlightFilters,
    matchesCellHighlight,
} from '@/lib/cellHighlight';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import type { Cell, ProductFilterOptions, Row } from '@/types/admin';

const props = defineProps<{
    row: Row;
    cells: Cell[];
    today: string;
    filterOptions: ProductFilterOptions;
}>();

const highlightFilters = reactive(emptyCellHighlightFilters());

function highlighted(cell: Cell | null): boolean {
    return matchesCellHighlight(cell, highlightFilters, props.today);
}

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
</script>

<template>
    <Head :title="t('rows.show.title', { letter: props.row.letter })" />

    <AdminLayout>
        <PageHeader :title="t('rows.show.title', { letter: props.row.letter })">
            <div class="flex items-center gap-4">
                <CellHighlightFilters
                    :model-value="highlightFilters"
                    :products="filterOptions.products"
                />
                <Link
                    :href="edit(props.row)"
                    class="inline-flex items-center gap-1 text-sm text-gray-600 hover:underline dark:text-neutral-400"
                >
                    <Pencil class="h-3.5 w-3.5" />
                    {{ t('rows.show.editRow') }}
                </Link>
            </div>
        </PageHeader>

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
                        <CellSlot
                            v-for="entry in flatColumn"
                            :key="entry.flatNumber"
                            :cell="entry.cell"
                            :label="
                                formatSlot(
                                    props.row.letter,
                                    cellIndex + 1,
                                    entry.flatNumber,
                                )
                            "
                            :highlighted="highlighted(entry.cell)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
