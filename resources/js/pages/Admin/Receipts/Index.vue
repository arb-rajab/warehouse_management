<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { show as showHelp } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    index as receiptsIndex,
    show as showReceipt,
} from '@/actions/App/Http/Controllers/Admin/ReceiptController';
import { show as showUser } from '@/actions/App/Http/Controllers/Admin/UserController';
import DataTable from '@/components/DataTable.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterDialogFooter from '@/components/FilterDialogFooter.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import FilterWarehouseSelect from '@/components/FilterWarehouseSelect.vue';
import HelpLink from '@/components/HelpLink.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import ReceiptStatusBadge from '@/components/ReceiptStatusBadge.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import {
    countActive,
    countBadgeClass,
    filterSectionHeadingClass as sectionHeadingClass,
    filterTriggerButtonClass,
    selectedCountLabel,
    toggleSort,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useTableColumns } from '@/lib/useTableColumns';
import type {
    Paginated,
    ReceiptFilterOptions,
    ReceiptFilters,
    ReceiptListItem,
} from '@/types/admin';

const props = defineProps<{
    receipts: Paginated<ReceiptListItem>;
    filters: ReceiptFilters;
    filterOptions: ReceiptFilterOptions;
}>();

const filters = reactive({
    warehouse_ids: (props.filters.warehouse_ids ?? []).map(String),
    status: props.filters.status ?? '',
    sort_direction: props.filters.sort_direction ?? '',
    per_page: props.filters.per_page ?? 20,
});

const filtersOpen = ref(false);

const activeFilterCount = computed(() =>
    countActive([filters.warehouse_ids.length > 0, filters.status !== '']),
);

const columns = useTableColumns({
    listId: 'admin.receipts',
    columns: [
        { key: 'id', label: 'receipts.index.columns.id', required: true },
        {
            key: 'warehouse',
            label: 'receipts.index.columns.warehouse',
            required: true,
        },
        {
            key: 'status',
            label: 'receipts.index.columns.status',
            required: true,
        },
        { key: 'pallets', label: 'receipts.index.columns.pallets' },
        { key: 'files', label: 'receipts.index.columns.files' },
        {
            key: 'createdBy',
            label: 'receipts.index.columns.createdBy',
            defaultVisible: false,
        },
        { key: 'createdAt', label: 'receipts.index.columns.createdAt' },
        {
            key: 'finalizedAt',
            label: 'receipts.index.columns.finalizedAt',
            defaultVisible: false,
        },
    ],
});
const { shown, visibleColumns } = columns;

const tableColumns = computed(() =>
    visibleColumns.value.map((column) =>
        column.key === 'createdAt'
            ? { label: t(column.label), sortKey: 'created_at' }
            : t(column.label),
    ),
);

function filterQuery(): Record<string, FormDataConvertible> {
    const { status, ...rest } = filters;

    return status === '' ? rest : { ...rest, status };
}

function applyFilters(): void {
    router.get(receiptsIndex().url, filterQuery(), {
        preserveState: true,
        replace: true,
    });
    filtersOpen.value = false;
}

function clearFilters(): void {
    filters.warehouse_ids = [];
    filters.status = '';
    filters.sort_direction = '';
    router.get(
        receiptsIndex().url,
        { per_page: filters.per_page },
        { preserveState: true, replace: true },
    );
}

function onSort(): void {
    const sort = {
        sort_by: 'created_at',
        sort_direction: filters.sort_direction,
    };
    toggleSort(sort, 'created_at');
    filters.sort_direction = sort.sort_direction;
    applyFilters();
}

function onPerPageChange(perPage: number): void {
    filters.per_page = perPage;
    applyFilters();
}
</script>

<template>
    <Head :title="t('receipts.index.title')" />

    <AdminLayout>
        <PageHeader :title="t('receipts.index.title')">
            <div class="flex items-center gap-2">
                <HelpLink :href="showHelp('receipts')" />
                <button
                    type="button"
                    :class="filterTriggerButtonClass"
                    @click="filtersOpen = true"
                >
                    <SlidersHorizontal class="h-4 w-4" />
                    {{ t('common.filters.title') }}
                    <span v-if="activeFilterCount > 0" :class="countBadgeClass">
                        {{ activeFilterCount }}
                    </span>
                </button>
            </div>
        </PageHeader>

        <FilterDialog
            v-model:open="filtersOpen"
            :title="t('common.filters.title')"
            :close-label="t('common.filters.close')"
        >
            <form
                id="receipts-filter-form"
                class="space-y-6"
                @submit.prevent="applyFilters"
            >
                <div>
                    <h3 :class="sectionHeadingClass">
                        {{ t('common.filters.title') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FilterWarehouseSelect
                            id="filter-warehouse"
                            v-model="filters.warehouse_ids"
                            :label="t('warehouses.filters.label')"
                            :all-label="t('receipts.filters.all')"
                            :selected-count-label="selectedCountLabel"
                            :selected="filterOptions.warehouses"
                        />

                        <FilterSelect
                            id="filter-status"
                            v-model="filters.status"
                            :label="t('receipts.filters.status')"
                            :all-label="t('receipts.filters.all')"
                            :options="[
                                {
                                    value: 'draft',
                                    label: t('receipts.status.draft'),
                                },
                                {
                                    value: 'finalized',
                                    label: t('receipts.status.finalized'),
                                },
                            ]"
                        />
                    </div>
                </div>
            </form>

            <template #footer>
                <FilterDialogFooter
                    form-id="receipts-filter-form"
                    :apply-label="t('common.filters.apply')"
                    :clear-label="t('common.filters.clear')"
                    @clear="clearFilters"
                />
            </template>
        </FilterDialog>

        <StoreColumnPicker :state="columns" />

        <DataTable
            :columns="tableColumns"
            :rows="receipts.data"
            :empty-message="
                activeFilterCount > 0
                    ? t('receipts.index.emptyFiltered')
                    : t('receipts.index.empty')
            "
            :sort="{
                by: 'created_at',
                direction: filters.sort_direction === 'asc' ? 'asc' : 'desc',
            }"
            @sort="onSort"
        >
            <template #row="{ row }">
                <td v-if="shown.id" class="px-4 py-2">
                    <TableLink :href="showReceipt(row)" class="font-mono">
                        #{{ row.id }}
                    </TableLink>
                </td>
                <td v-if="shown.warehouse" class="px-4 py-2">
                    {{ row.warehouse.name }}
                </td>
                <td v-if="shown.status" class="px-4 py-2">
                    <ReceiptStatusBadge :status="row.status" />
                </td>
                <td v-if="shown.pallets" class="px-4 py-2">
                    {{ row.pallets_count }}
                </td>
                <td v-if="shown.files" class="px-4 py-2">
                    {{ row.files_count }}
                </td>
                <td v-if="shown.createdBy" class="px-4 py-2">
                    <TableLink :href="showUser(row.created_by)">
                        {{ row.created_by.name }}
                    </TableLink>
                </td>
                <td v-if="shown.createdAt" class="px-4 py-2">
                    {{ formatDateTime(row.created_at) }}
                </td>
                <td v-if="shown.finalizedAt" class="px-4 py-2">
                    {{
                        row.finalized_at
                            ? formatDateTime(row.finalized_at)
                            : '—'
                    }}
                </td>
            </template>
        </DataTable>

        <Pagination
            :links="receipts.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </AdminLayout>
</template>
