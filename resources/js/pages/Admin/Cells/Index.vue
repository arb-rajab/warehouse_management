<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { show as showRow } from '@/actions/App/Http/Controllers/Admin/RowController';
import DataTable from '@/components/DataTable.vue';
import FilterDateField from '@/components/FilterDateField.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import Pagination from '@/components/Pagination.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDate } from '@/lib/date';
import {
    applySortToggle,
    columnNumberOptions,
    filterSectionHeadingClass as sectionHeadingClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import type {
    CellFilterOptions,
    CellFilters,
    CellWithLocation,
    Paginated,
} from '@/types/admin';

const props = defineProps<{
    cells: Paginated<CellWithLocation>;
    filters: CellFilters;
    filterOptions: CellFilterOptions;
}>();

function stateLabel(state: CellWithLocation['state']): string {
    return t(`cellLog.states.${state}`);
}

const columnNumbers = columnNumberOptions(props.filterOptions.maxColumnNumber);

const filters = reactive({
    state: props.filters.state ?? '',
    stale: props.filters.stale ? '1' : '',
    row_id: props.filters.row_id?.toString() ?? '',
    column_number: props.filters.column_number?.toString() ?? '',
    expiration_date_from: props.filters.expiration_date_from ?? '',
    expiration_date_to: props.filters.expiration_date_to ?? '',
    sort_by: props.filters.sort_by ?? '',
    sort_direction: props.filters.sort_direction ?? '',
});

const filtersOpen = ref(false);

const activeFilterCount = computed(
    () =>
        [
            filters.state !== '',
            filters.stale !== '',
            filters.row_id !== '',
            filters.column_number !== '',
            filters.expiration_date_from !== '' ||
                filters.expiration_date_to !== '',
        ].filter(Boolean).length,
);

function applyFilters(): void {
    router.get(cellsIndex().url, filters, {
        preserveState: true,
        replace: true,
    });
    filtersOpen.value = false;
}

function clearFilters(): void {
    filters.state = '';
    filters.stale = '';
    filters.row_id = '';
    filters.column_number = '';
    filters.expiration_date_from = '';
    filters.expiration_date_to = '';
    filters.sort_by = '';
    filters.sort_direction = '';
    router.get(cellsIndex().url, {}, { preserveState: true, replace: true });
}

function toggleSort(key: string): void {
    applySortToggle(filters, key, applyFilters);
}
</script>

<template>
    <Head :title="t('cells.title')" />

    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('cells.title') }}</h1>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                @click="filtersOpen = true"
            >
                <SlidersHorizontal class="h-4 w-4" />
                {{ t('cellLog.filters.title') }}
                <span
                    v-if="activeFilterCount > 0"
                    class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-gray-900 px-1 text-xs font-medium text-white dark:bg-white dark:text-gray-900"
                >
                    {{ activeFilterCount }}
                </span>
            </button>
        </div>

        <FilterDialog
            v-model:open="filtersOpen"
            :title="t('cellLog.filters.title')"
            :close-label="t('cellLog.filters.close')"
        >
            <form class="space-y-6" @submit.prevent="applyFilters">
                <div>
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellLog.filters.sections.location') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <FilterSelect
                            id="filter-state"
                            v-model="filters.state"
                            :label="t('cells.filters.state')"
                            :all-label="t('cellLog.filters.all')"
                            :options="
                                filterOptions.states.map((state) => ({
                                    value: state,
                                    label: stateLabel(state),
                                }))
                            "
                        />

                        <FilterSelect
                            id="filter-stale"
                            v-model="filters.stale"
                            :label="t('cells.filters.stale')"
                            :all-label="t('cellLog.filters.all')"
                            :options="[
                                {
                                    value: '1',
                                    label: t('cells.filters.staleOnly'),
                                },
                            ]"
                        />

                        <FilterSelect
                            id="filter-row"
                            v-model="filters.row_id"
                            :label="t('cellLog.filters.row')"
                            :all-label="t('cellLog.filters.all')"
                            :options="
                                filterOptions.rows.map((row) => ({
                                    value: row.id,
                                    label: row.letter,
                                }))
                            "
                        />

                        <FilterSelect
                            id="filter-column"
                            v-model="filters.column_number"
                            :label="t('cellLog.filters.column')"
                            :all-label="t('cellLog.filters.all')"
                            :options="
                                columnNumbers.map((columnNumber) => ({
                                    value: columnNumber,
                                    label: String(columnNumber),
                                }))
                            "
                        />
                    </div>
                </div>

                <div
                    class="border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellLog.filters.sections.expiration') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FilterDateField
                            id="filter-expiration-date-from"
                            v-model="filters.expiration_date_from"
                            :label="t('cellLog.filters.expirationFrom')"
                        />

                        <FilterDateField
                            id="filter-expiration-date-to"
                            v-model="filters.expiration_date_to"
                            :label="t('cellLog.filters.expirationTo')"
                        />
                    </div>
                </div>

                <div
                    class="flex items-center gap-2 border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <button
                        type="submit"
                        class="rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-neutral-200"
                    >
                        {{ t('cellLog.filters.apply') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
                        @click="clearFilters"
                    >
                        {{ t('cellLog.filters.clear') }}
                    </button>
                </div>
            </form>
        </FilterDialog>

        <DataTable
            :columns="[
                t('cells.columns.location'),
                t('cells.columns.state'),
                t('cells.columns.product'),
                {
                    label: t('cells.columns.expires'),
                    sortKey: 'expiration_date',
                },
            ]"
            :rows="cells.data"
            :empty-message="t('cells.empty')"
            :sort="
                filters.sort_by
                    ? {
                          by: filters.sort_by,
                          direction:
                              filters.sort_direction === 'asc' ? 'asc' : 'desc',
                      }
                    : undefined
            "
            @sort="toggleSort"
        >
            <template #row="{ row: cell }">
                <td class="px-4 py-2">
                    <TableLink :href="showRow({ letter: cell.row_letter })">
                        {{
                            formatSlot(
                                cell.row_letter,
                                cell.cell_number,
                                cell.flat_number,
                            )
                        }}
                    </TableLink>
                </td>
                <td class="px-4 py-2">
                    {{ stateLabel(cell.state) }}
                </td>
                <td class="px-4 py-2">
                    <div
                        v-if="cell.pallet"
                        class="flex items-center gap-2 font-medium text-gray-900 dark:text-neutral-100"
                    >
                        <img
                            v-if="cell.pallet.product_image_url"
                            :src="cell.pallet.product_image_url"
                            :alt="cell.pallet.product_name"
                            class="h-8 w-8 shrink-0 rounded object-cover"
                        />
                        {{ cell.pallet.product_name }}
                    </div>
                    <span v-else class="text-gray-400 dark:text-neutral-600"
                        >—</span
                    >
                </td>
                <td class="px-4 py-2 text-gray-500 dark:text-neutral-400">
                    <template v-if="cell.pallet">{{
                        formatDate(cell.pallet.expiration_date)
                    }}</template>
                    <span v-else class="text-gray-400 dark:text-neutral-600"
                        >—</span
                    >
                </td>
            </template>
        </DataTable>

        <Pagination :links="cells.meta.links" />
    </AdminLayout>
</template>
