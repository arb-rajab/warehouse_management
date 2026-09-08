<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/vue3';
import { Check, SlidersHorizontal, X } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import CellLogActivityFilterFields from '@/components/CellLogActivityFilterFields.vue';
import CellStatusLogRowCells from '@/components/CellStatusLogRowCells.vue';
import DataTable from '@/components/DataTable.vue';
import DateRangeFilterFields from '@/components/DateRangeFilterFields.vue';
import FilterCheckbox from '@/components/FilterCheckbox.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import FilterProductSelect from '@/components/FilterProductSelect.vue';
import LocationFilterFields from '@/components/LocationFilterFields.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
    cellLogActionLabel,
    mergeTransferPairs,
} from '@/lib/cellStatusLogDisplay';
import {
    countActive,
    countBadgeClass,
    createdDateRangeExclusivity,
    dateRangeActive,
    exclusivePair,
    filterApplyButtonClass,
    filterClearButtonClass,
    filterFooterClass,
    filterSectionClass,
    filterSectionHeadingClass as sectionHeadingClass,
    filterTriggerButtonClass,
    selectedCountLabel,
    toggleSort,
    useColumnFilterPopover,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    CellStatusLog,
    CellStatusLogFilterOptions,
    CellStatusLogFilters,
    Paginated,
} from '@/types/admin';

const props = defineProps<{
    logs: Paginated<CellStatusLog>;
    filters: CellStatusLogFilters;
    filterOptions: CellStatusLogFilterOptions;
}>();

const filters = reactive({
    product_id: (props.filters.product_id ?? []).map(String),
    pallet_id: props.filters.pallet_id?.toString() ?? '',
    row_id: props.filters.row_id?.toString() ?? '',
    column_number: props.filters.column_number?.toString() ?? '',
    user_id: (props.filters.user_id ?? []).map(String),
    action: [...(props.filters.action ?? [])],
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    created_within_days: props.filters.created_within_days?.toString() ?? '',
    expiration_date_from: props.filters.expiration_date_from ?? '',
    expiration_date_to: props.filters.expiration_date_to ?? '',
    expires_within_days: props.filters.expires_within_days?.toString() ?? '',
    sort_by: props.filters.sort_by ?? '',
    sort_direction: props.filters.sort_direction ?? '',
    flagged: props.filters.flagged ?? false,
    per_page: props.filters.per_page ?? 20,
});

const { dateRangeDisabled, createdWithinDaysDisabled } =
    createdDateRangeExclusivity(filters);

const {
    rangeDisabled: expirationRangeDisabled,
    daysDisabled: expiresWithinDaysDisabled,
} = exclusivePair(
    () =>
        filters.expiration_date_from !== '' ||
        filters.expiration_date_to !== '',
    () => filters.expires_within_days !== '',
);

const filtersOpen = ref(false);

const activeFilterCount = computed(() =>
    countActive([
        filters.product_id.length > 0,
        filters.row_id !== '',
        filters.column_number !== '',
        filters.user_id.length > 0,
        filters.action.length > 0,
        dateRangeActive(filters),
        filters.expiration_date_from !== '' ||
            filters.expiration_date_to !== '' ||
            filters.expires_within_days !== '',
        filters.flagged,
    ]),
);

const cellColumnFiltered = computed(
    () => filters.row_id !== '' || filters.column_number !== '',
);
const productColumnFiltered = computed(() => filters.product_id.length > 0);
const actionColumnFiltered = computed(() => filters.action.length > 0);
const doneByColumnFiltered = computed(() => filters.user_id.length > 0);
const whenColumnFiltered = computed(() => dateRangeActive(filters));
const palletColumnFiltered = computed(
    () =>
        filters.pallet_id !== '' ||
        filters.expiration_date_from !== '' ||
        filters.expiration_date_to !== '' ||
        filters.expires_within_days !== '',
);

/**
 * `flagged` is only ever included when checked, mirroring the `expired`
 * filter on the products page — keeps the query string clean instead of
 * always carrying a literal `flagged=false`.
 */
function filterQuery(): Record<string, FormDataConvertible> {
    const { flagged, ...rest } = filters;

    return flagged ? { ...rest, flagged: true } : rest;
}

function applyFilters(): void {
    router.get(cellLogsIndex().url, filterQuery(), {
        preserveState: true,
        replace: true,
    });
    filtersOpen.value = false;
}

const { openFilterKey } = useColumnFilterPopover(
    filters,
    filtersOpen,
    applyFilters,
);

function clearFilters(): void {
    filters.product_id = [];
    filters.pallet_id = '';
    filters.row_id = '';
    filters.column_number = '';
    filters.user_id = [];
    filters.action = [];
    filters.date_from = '';
    filters.date_to = '';
    filters.created_within_days = '';
    filters.expiration_date_from = '';
    filters.expiration_date_to = '';
    filters.expires_within_days = '';
    filters.sort_by = '';
    filters.sort_direction = '';
    filters.flagged = false;
    router.get(
        cellLogsIndex().url,
        { per_page: filters.per_page },
        { preserveState: true, replace: true },
    );
}

function onSort(key: string): void {
    toggleSort(filters, key);
    applyFilters();
}

function onPerPageChange(perPage: number): void {
    filters.per_page = perPage;
    applyFilters();
}

function viewPalletHistory(palletId: number): void {
    router.get(
        cellLogsIndex().url,
        { pallet_id: palletId },
        { preserveState: true, replace: true },
    );
}

const displayLogs = computed(() => mergeTransferPairs(props.logs.data));
</script>

<template>
    <Head :title="t('cellLog.title')" />

    <AdminLayout>
        <PageHeader :title="t('cellLog.title')">
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="filtersOpen = true"
            >
                <SlidersHorizontal class="h-4 w-4" />
                {{ t('cellLog.filters.title') }}
                <span v-if="activeFilterCount > 0" :class="countBadgeClass">
                    {{ activeFilterCount }}
                </span>
            </button>
        </PageHeader>

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
                    <LocationFilterFields
                        id-prefix="filter"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        v-model:row-id="filters.row_id"
                        v-model:column-number="filters.column_number"
                        :rows="filterOptions.rows"
                        :max-column-number="filterOptions.maxColumnNumber"
                    />
                </div>

                <div :class="filterSectionClass">
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellLog.filters.sections.activity') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <FilterProductSelect
                            id="filter-product"
                            v-model="filters.product_id"
                            :label="t('cellLog.filters.product')"
                            :all-label="t('cellLog.filters.all')"
                            :selected-count-label="selectedCountLabel"
                            :selected="filterOptions.products"
                        />

                        <CellLogActivityFilterFields
                            id-prefix="filter"
                            v-model:action="filters.action"
                            v-model:user-id="filters.user_id"
                            :actions="filterOptions.actions"
                            :users="filterOptions.users"
                        />
                    </div>

                    <FilterCheckbox
                        id="filter-flagged"
                        v-model="filters.flagged"
                        class="mt-4"
                        :label="t('cellLog.filters.flaggedOnly')"
                    />
                </div>

                <div :class="filterSectionClass">
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellLog.filters.sections.date') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <DateRangeFilterFields
                            from-id="filter-date-from"
                            to-id="filter-date-to"
                            within-days-id="filter-created-within-days"
                            :from-label="t('cellLog.filters.from')"
                            :to-label="t('cellLog.filters.to')"
                            :within-days-label="t('cellLog.filters.withinDays')"
                            v-model:from="filters.date_from"
                            v-model:to="filters.date_to"
                            v-model:within-days="filters.created_within_days"
                            :range-disabled="dateRangeDisabled"
                            :days-disabled="createdWithinDaysDisabled"
                        />
                    </div>
                </div>

                <div :class="filterSectionClass">
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellLog.filters.sections.expiration') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <DateRangeFilterFields
                            from-id="filter-expiration-date-from"
                            to-id="filter-expiration-date-to"
                            within-days-id="filter-expires-within-days"
                            :from-label="t('cellLog.filters.expirationFrom')"
                            :to-label="t('cellLog.filters.expirationTo')"
                            :within-days-label="
                                t('cellLog.filters.expiresWithinDays')
                            "
                            v-model:from="filters.expiration_date_from"
                            v-model:to="filters.expiration_date_to"
                            v-model:within-days="filters.expires_within_days"
                            :range-disabled="expirationRangeDisabled"
                            :days-disabled="expiresWithinDaysDisabled"
                        />
                    </div>
                </div>

                <div :class="filterFooterClass">
                    <button type="submit" :class="filterApplyButtonClass">
                        <Check class="h-4 w-4 shrink-0" />
                        {{ t('cellLog.filters.apply') }}
                    </button>
                    <button
                        type="button"
                        :class="filterClearButtonClass"
                        @click="clearFilters"
                    >
                        <X class="h-4 w-4 shrink-0" />
                        {{ t('cellLog.filters.clear') }}
                    </button>
                </div>
            </form>
        </FilterDialog>

        <div
            v-if="filters.pallet_id"
            class="mb-4 flex items-center justify-between rounded-md bg-blue-50 px-4 py-2 text-sm text-blue-800 dark:bg-blue-950 dark:text-blue-200"
        >
            <span>{{
                t('cellLog.filters.palletHistory', { id: filters.pallet_id })
            }}</span>
            <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-1 font-medium hover:underline"
                @click="clearFilters"
            >
                <X class="h-3.5 w-3.5 shrink-0" />
                {{ t('cellLog.filters.clear') }}
            </button>
        </div>

        <DataTable
            v-model:open-filter-key="openFilterKey"
            :columns="[
                {
                    label: t('cellLog.columns.cell'),
                    filtered: cellColumnFiltered,
                    filterKey: 'location',
                },
                {
                    label: t('cellLog.columns.action'),
                    filtered: actionColumnFiltered,
                    filterKey: 'action',
                },
                {
                    label: t('cellLog.columns.product'),
                    filtered: productColumnFiltered,
                    filterKey: 'product',
                },
                {
                    label: t('cellLog.columns.pallet'),
                    sortKey: 'expiration_date',
                    filtered: palletColumnFiltered,
                    filterKey: 'expiration',
                },
                t('cellLog.columns.note'),
                {
                    label: t('cellLog.columns.doneBy'),
                    filtered: doneByColumnFiltered,
                    filterKey: 'doneBy',
                },
                {
                    label: t('cellLog.columns.when'),
                    sortKey: 'created_at',
                    filtered: whenColumnFiltered,
                    filterKey: 'when',
                },
            ]"
            :rows="displayLogs"
            :empty-message="t('cellLog.empty')"
            :sort="
                filters.sort_by
                    ? {
                          by: filters.sort_by,
                          direction:
                              filters.sort_direction === 'asc' ? 'asc' : 'desc',
                      }
                    : undefined
            "
            @sort="onSort"
        >
            <template #column-filter="{ filterKey: key }">
                <LocationFilterFields
                    v-if="key === 'location'"
                    id-prefix="popover-filter"
                    class="space-y-3"
                    v-model:row-id="filters.row_id"
                    v-model:column-number="filters.column_number"
                    :rows="filterOptions.rows"
                    :max-column-number="filterOptions.maxColumnNumber"
                />

                <div v-else-if="key === 'product'">
                    <FilterProductSelect
                        id="popover-filter-product"
                        v-model="filters.product_id"
                        :label="t('cellLog.filters.product')"
                        :all-label="t('cellLog.filters.all')"
                        :selected-count-label="selectedCountLabel"
                        :selected="filterOptions.products"
                    />
                </div>

                <div v-else-if="key === 'action'">
                    <FilterMultiSelect
                        id="popover-filter-action"
                        v-model="filters.action"
                        :label="t('cellLog.filters.statusChange')"
                        :all-label="t('cellLog.filters.all')"
                        :selected-count-label="selectedCountLabel"
                        :options="
                            filterOptions.actions.map((action) => ({
                                value: action,
                                label: cellLogActionLabel(action),
                            }))
                        "
                    />
                </div>

                <div v-else-if="key === 'doneBy'">
                    <FilterMultiSelect
                        id="popover-filter-user"
                        v-model="filters.user_id"
                        :label="t('cellLog.filters.doneBy')"
                        :all-label="t('cellLog.filters.all')"
                        :selected-count-label="selectedCountLabel"
                        :options="
                            filterOptions.users.map((user) => ({
                                value: user.id.toString(),
                                label: user.name,
                            }))
                        "
                    />
                </div>

                <div v-else-if="key === 'when'" class="space-y-3">
                    <DateRangeFilterFields
                        from-id="popover-filter-date-from"
                        to-id="popover-filter-date-to"
                        within-days-id="popover-filter-created-within-days"
                        :from-label="t('cellLog.filters.from')"
                        :to-label="t('cellLog.filters.to')"
                        :within-days-label="t('cellLog.filters.withinDays')"
                        v-model:from="filters.date_from"
                        v-model:to="filters.date_to"
                        v-model:within-days="filters.created_within_days"
                        :range-disabled="dateRangeDisabled"
                        :days-disabled="createdWithinDaysDisabled"
                    />
                </div>

                <div v-else-if="key === 'expiration'" class="space-y-3">
                    <DateRangeFilterFields
                        from-id="popover-filter-expiration-date-from"
                        to-id="popover-filter-expiration-date-to"
                        within-days-id="popover-filter-expires-within-days"
                        :from-label="t('cellLog.filters.expirationFrom')"
                        :to-label="t('cellLog.filters.expirationTo')"
                        :within-days-label="
                            t('cellLog.filters.expiresWithinDays')
                        "
                        v-model:from="filters.expiration_date_from"
                        v-model:to="filters.expiration_date_to"
                        v-model:within-days="filters.expires_within_days"
                        :range-disabled="expirationRangeDisabled"
                        :days-disabled="expiresWithinDaysDisabled"
                    />
                </div>
            </template>

            <template #row="{ row: log }">
                <CellStatusLogRowCells
                    :log="log"
                    show-user-column
                    @view-pallet-history="viewPalletHistory"
                />
            </template>
        </DataTable>

        <Pagination
            :links="logs.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </AdminLayout>
</template>
