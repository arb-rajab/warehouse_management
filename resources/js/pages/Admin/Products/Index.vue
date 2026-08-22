<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/vue3';
import { Check, SlidersHorizontal, X } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { index as productsIndex } from '@/actions/App/Http/Controllers/Admin/ProductController';
import DataTable from '@/components/DataTable.vue';
import FilterDateField from '@/components/FilterDateField.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import FilterNumberField from '@/components/FilterNumberField.vue';
import FilterProductSelect from '@/components/FilterProductSelect.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import Pagination from '@/components/Pagination.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
    columnNumberOptions,
    countActive,
    countBadgeClass,
    debounce,
    filterApplyButtonClass,
    filterClearButtonClass,
    filterSectionHeadingClass as sectionHeadingClass,
    filterTriggerButtonClass,
    selectedCountLabel,
    toggleSort,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    Cell,
    CellLogAction,
    Paginated,
    ProductFilters,
    ProductIndexFilterOptions,
    ProductSummary,
} from '@/types/admin';
import type { QueryParams } from '@/wayfinder';

const props = defineProps<{
    products: Paginated<ProductSummary>;
    today: string;
    weekStart: string;
    expiringSoonDays: number;
    filters: ProductFilters;
    filterOptions: ProductIndexFilterOptions;
}>();

function actionLabel(action: CellLogAction): string {
    return t(`cellLog.actions.${action}`);
}

function stateLabel(state: Cell['state']): string {
    return t(`cellLog.states.${state}`);
}

const columnNumbers = columnNumberOptions(props.filterOptions.maxColumnNumber);
// No `empty` option here (unlike the cells map) — an empty cell never holds
// a product, so filtering to it would always zero out every column.
const occupiedCellStates: Extract<Cell['state'], 'full' | 'opened'>[] = [
    'full',
    'opened',
];

// Mirrors the dashboard's fixed expiring-soon windows, as a quick-pick
// shortcut for this field instead of typing a day count every time.
const expiringSoonQuickPicks = [7, 14, 30, 60];

const filters = reactive({
    row_id: props.filters.row_id?.toString() ?? '',
    column_number: props.filters.column_number?.toString() ?? '',
    state: props.filters.state ?? '',
    expired: props.filters.expired ?? false,
    expires_within_days: props.filters.expires_within_days?.toString() ?? '',
    product_id: (props.filters.product_id ?? []).map(String),
    user_id: (props.filters.user_id ?? []).map(String),
    action: [...(props.filters.action ?? [])],
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    created_within_days: props.filters.created_within_days?.toString() ?? '',
    sort_by: props.filters.sort_by ?? '',
    sort_direction: props.filters.sort_direction ?? '',
});

const dateRangeDisabled = computed(() => filters.created_within_days !== '');
const createdWithinDaysDisabled = computed(
    () => filters.date_from !== '' || filters.date_to !== '',
);

const filtersOpen = ref(false);

const activeFilterCount = computed(() =>
    countActive([
        filters.row_id !== '',
        filters.column_number !== '',
        filters.state !== '',
        filters.expired,
        filters.expires_within_days !== '',
        filters.product_id.length > 0,
        filters.user_id.length > 0,
        filters.action.length > 0,
        filters.date_from !== '' ||
            filters.date_to !== '' ||
            filters.created_within_days !== '',
    ]),
);

const productColumnFiltered = computed(() => filters.product_id.length > 0);

/**
 * Row/column/state narrow every occupancy-derived column identically on the
 * backend (see `ProductController::occupancyCountSubquery()`/
 * `applyHistoryLogFilters()`), so the Full/Opened/Expired/Expiring-soon
 * columns are always marked filtered together.
 */
const occupancyColumnsFiltered = computed(
    () =>
        filters.row_id !== '' ||
        filters.column_number !== '' ||
        filters.state !== '' ||
        filters.expired ||
        filters.expires_within_days !== '',
);

const activityColumnsFiltered = computed(
    () =>
        filters.row_id !== '' ||
        filters.column_number !== '' ||
        filters.state !== '' ||
        filters.user_id.length > 0 ||
        filters.action.length > 0 ||
        filters.date_from !== '' ||
        filters.date_to !== '' ||
        filters.created_within_days !== '',
);

/**
 * `expired` is only ever included when checked — sending the unchecked
 * `false` would still be a non-empty query value the backend treats as
 * "filled" (see FilterProductsRequest), so it must be omitted rather than
 * sent as literal `false`. Mirrors Cells/Index.vue's `highlightQuery()`.
 */
function filterQuery(): Record<string, FormDataConvertible> {
    const { expired, ...rest } = filters;

    return expired ? { ...rest, expired: true } : rest;
}

function applyFilters(): void {
    router.get(productsIndex().url, filterQuery(), {
        preserveState: true,
        replace: true,
    });
    filtersOpen.value = false;
}

/**
 * Which column's quick filter popover is open, if any — bound two-way to
 * DataTable so a change made inside it can auto-apply below.
 */
const openFilterKey = ref<string | null>(null);

const debouncedApplyFilters = debounce(applyFilters, 400);

/**
 * A column popover applies on every change instead of needing its own
 * Apply button — gated on a popover actually being open (and the full
 * dialog being closed) so editing the same `filters.x` fields from the
 * main dialog doesn't also trigger a premature navigation before its own
 * Apply is clicked.
 */
watch(
    filters,
    () => {
        if (openFilterKey.value !== null && !filtersOpen.value) {
            debouncedApplyFilters();
        }
    },
    { deep: true },
);

function clearFilters(): void {
    filters.row_id = '';
    filters.column_number = '';
    filters.state = '';
    filters.expired = false;
    filters.expires_within_days = '';
    filters.product_id = [];
    filters.user_id = [];
    filters.action = [];
    filters.date_from = '';
    filters.date_to = '';
    filters.created_within_days = '';
    filters.sort_by = '';
    filters.sort_direction = '';
    router.get(productsIndex().url, {}, { preserveState: true, replace: true });
}

function onSort(key: string): void {
    toggleSort(filters, key);
    applyFilters();
}

/**
 * The row/column location filters currently applied, carried into every
 * per-product drill-down link below so the destination page stays scoped
 * the same way the table row was computed.
 */
function locationQuery(): QueryParams {
    const query: QueryParams = {};

    if (filters.row_id !== '') {
        query.row_id = Number(filters.row_id);
    }

    if (filters.column_number !== '') {
        query.column_number = Number(filters.column_number);
    }

    return query;
}

function occupancyHref(
    product: ProductSummary,
    overrides: QueryParams,
): string {
    return cellsIndex.url({
        query: { ...locationQuery(), product_id: [product.id], ...overrides },
    });
}

/**
 * The user/action filters currently applied, carried into the activity
 * drill-down links below alongside a fixed date range — `date_from`/
 * `date_to` deliberately override rather than merge with the page's own
 * date filter, since "today"/"this week" are each a specific window.
 */
function activityHref(
    product: ProductSummary,
    dateFrom: string,
    dateTo: string,
): string {
    const query: QueryParams = {
        ...locationQuery(),
        product_id: [product.id],
        date_from: dateFrom,
        date_to: dateTo,
    };

    if (filters.user_id.length > 0) {
        query.user_id = filters.user_id.map(Number);
    }

    if (filters.action.length > 0) {
        query.action = filters.action;
    }

    return cellLogsIndex.url({ query });
}
</script>

<template>
    <Head :title="t('products.title')" />

    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('products.title') }}</h1>
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
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                        {{ t('products.filters.sections.occupancy') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FilterSelect
                            id="filter-state"
                            v-model="filters.state"
                            :label="t('cells.filters.state')"
                            :all-label="t('cellLog.filters.all')"
                            :options="
                                occupiedCellStates.map((state) => ({
                                    value: state,
                                    label: stateLabel(state),
                                }))
                            "
                        />

                        <FilterProductSelect
                            id="filter-product"
                            v-model="filters.product_id"
                            :label="t('cellLog.filters.product')"
                            :all-label="t('cellLog.filters.all')"
                            :selected-count-label="selectedCountLabel"
                            :selected="filterOptions.products"
                        />

                        <div>
                            <FilterNumberField
                                id="filter-expires-within-days"
                                v-model="filters.expires_within_days"
                                :label="t('cellHighlight.expiresWithinDays')"
                                :placeholder="String(expiringSoonDays)"
                            />
                            <div class="mt-1 flex gap-1">
                                <button
                                    v-for="days in expiringSoonQuickPicks"
                                    :key="days"
                                    type="button"
                                    class="cursor-pointer rounded-md border border-gray-300 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100 dark:border-neutral-700 dark:text-neutral-400 dark:hover:bg-neutral-800"
                                    @click="
                                        filters.expires_within_days =
                                            String(days)
                                    "
                                >
                                    {{ days }}
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                id="filter-expired"
                                v-model="filters.expired"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 dark:border-neutral-700"
                            />
                            <label
                                for="filter-expired"
                                class="text-sm text-gray-700 dark:text-neutral-300"
                                >{{ t('cellHighlight.expired') }}</label
                            >
                        </div>
                    </div>
                </div>

                <div
                    class="border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellLog.filters.sections.activity') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <FilterMultiSelect
                            id="filter-action"
                            v-model="filters.action"
                            :label="t('cellLog.filters.statusChange')"
                            :all-label="t('cellLog.filters.all')"
                            :selected-count-label="selectedCountLabel"
                            :options="
                                filterOptions.actions.map((action) => ({
                                    value: action,
                                    label: actionLabel(action),
                                }))
                            "
                        />

                        <FilterMultiSelect
                            id="filter-user"
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

                        <FilterDateField
                            id="filter-date-from"
                            v-model="filters.date_from"
                            :label="t('cellLog.filters.from')"
                            :disabled="dateRangeDisabled"
                        />

                        <FilterDateField
                            id="filter-date-to"
                            v-model="filters.date_to"
                            :label="t('cellLog.filters.to')"
                            :disabled="dateRangeDisabled"
                        />

                        <FilterNumberField
                            id="filter-created-within-days"
                            v-model="filters.created_within_days"
                            :label="t('cellLog.filters.withinDays')"
                            :disabled="createdWithinDaysDisabled"
                        />
                    </div>
                </div>

                <div
                    class="flex items-center gap-2 border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
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

        <DataTable
            v-model:open-filter-key="openFilterKey"
            :columns="[
                {
                    label: t('products.columns.product'),
                    filtered: productColumnFiltered,
                    filterKey: 'product',
                },
                {
                    label: t('products.columns.full'),
                    sortKey: 'full_cells_count',
                    filtered: occupancyColumnsFiltered,
                    filterKey: 'occupancy',
                },
                {
                    label: t('products.columns.opened'),
                    sortKey: 'opened_cells_count',
                    filtered: occupancyColumnsFiltered,
                    filterKey: 'occupancy',
                    filterIconAlwaysVisible: false,
                },
                {
                    label: t('products.columns.expired'),
                    sortKey: 'expired_cells_count',
                    filtered: occupancyColumnsFiltered,
                    filterKey: 'occupancy',
                    filterIconAlwaysVisible: false,
                },
                {
                    label: t('products.columns.expiringSoon', {
                        days: expiringSoonDays,
                    }),
                    sortKey: 'expiring_soon_count',
                    filtered: occupancyColumnsFiltered,
                    filterKey: 'occupancy',
                    filterIconAlwaysVisible: false,
                },
                {
                    label: t('products.columns.activityToday'),
                    sortKey: 'activity_today_count',
                    filtered: activityColumnsFiltered,
                    filterKey: 'activity',
                },
                {
                    label: t('products.columns.activityWeek'),
                    sortKey: 'activity_week_count',
                    filtered: activityColumnsFiltered,
                    filterKey: 'activity',
                    filterIconAlwaysVisible: false,
                },
            ]"
            :rows="products.data"
            :empty-message="t('products.empty')"
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
                <div v-if="key === 'product'" id="popover-filter-product-wrap">
                    <FilterProductSelect
                        id="popover-filter-product"
                        v-model="filters.product_id"
                        :label="t('cellLog.filters.product')"
                        :all-label="t('cellLog.filters.all')"
                        :selected-count-label="selectedCountLabel"
                        :selected="filterOptions.products"
                    />
                </div>

                <div v-else-if="key === 'occupancy'" class="space-y-3">
                    <FilterSelect
                        id="popover-filter-state"
                        v-model="filters.state"
                        :label="t('cells.filters.state')"
                        :all-label="t('cellLog.filters.all')"
                        :options="
                            occupiedCellStates.map((state) => ({
                                value: state,
                                label: stateLabel(state),
                            }))
                        "
                    />

                    <div class="flex items-center gap-2">
                        <input
                            id="popover-filter-expired"
                            v-model="filters.expired"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 dark:border-neutral-700"
                        />
                        <label
                            for="popover-filter-expired"
                            class="text-sm text-gray-700 dark:text-neutral-300"
                            >{{ t('cellHighlight.expired') }}</label
                        >
                    </div>

                    <div>
                        <FilterNumberField
                            id="popover-filter-expires-within-days"
                            v-model="filters.expires_within_days"
                            :label="t('cellHighlight.expiresWithinDays')"
                            :placeholder="String(expiringSoonDays)"
                        />
                        <div class="mt-1 flex gap-1">
                            <button
                                v-for="days in expiringSoonQuickPicks"
                                :key="days"
                                type="button"
                                class="cursor-pointer rounded-md border border-gray-300 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100 dark:border-neutral-700 dark:text-neutral-400 dark:hover:bg-neutral-800"
                                @click="
                                    filters.expires_within_days = String(days)
                                "
                            >
                                {{ days }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else-if="key === 'activity'" class="space-y-3">
                    <FilterMultiSelect
                        id="popover-filter-action"
                        v-model="filters.action"
                        :label="t('cellLog.filters.statusChange')"
                        :all-label="t('cellLog.filters.all')"
                        :selected-count-label="selectedCountLabel"
                        :options="
                            filterOptions.actions.map((action) => ({
                                value: action,
                                label: actionLabel(action),
                            }))
                        "
                    />

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

                    <FilterDateField
                        id="popover-filter-date-from"
                        v-model="filters.date_from"
                        :label="t('cellLog.filters.from')"
                        :disabled="dateRangeDisabled"
                    />

                    <FilterDateField
                        id="popover-filter-date-to"
                        v-model="filters.date_to"
                        :label="t('cellLog.filters.to')"
                        :disabled="dateRangeDisabled"
                    />

                    <FilterNumberField
                        id="popover-filter-created-within-days"
                        v-model="filters.created_within_days"
                        :label="t('cellLog.filters.withinDays')"
                        :disabled="createdWithinDaysDisabled"
                    />
                </div>
            </template>

            <template #row="{ row: product }">
                <td class="px-4 py-2">
                    <div
                        class="flex items-center gap-2 font-medium text-gray-900 dark:text-neutral-100"
                    >
                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            :alt="product.name"
                            class="h-8 w-8 shrink-0 rounded object-cover"
                        />
                        {{ product.name }}
                    </div>
                </td>
                <td class="px-4 py-2">
                    <TableLink
                        :href="occupancyHref(product, { state: 'full' })"
                    >
                        {{ product.full_cells_count }}
                    </TableLink>
                </td>
                <td class="px-4 py-2">
                    <TableLink
                        :href="occupancyHref(product, { state: 'opened' })"
                    >
                        {{ product.opened_cells_count }}
                    </TableLink>
                </td>
                <td class="px-4 py-2">
                    <TableLink
                        :href="occupancyHref(product, { expired: true })"
                    >
                        {{ product.expired_cells_count }}
                    </TableLink>
                </td>
                <td class="px-4 py-2">
                    <TableLink
                        :href="
                            occupancyHref(product, {
                                expires_within_days: expiringSoonDays,
                            })
                        "
                    >
                        {{ product.expiring_soon_count }}
                    </TableLink>
                </td>
                <td class="px-4 py-2">
                    <TableLink :href="activityHref(product, today, today)">
                        {{ product.activity_today_count }}
                    </TableLink>
                </td>
                <td class="px-4 py-2">
                    <TableLink :href="activityHref(product, weekStart, today)">
                        {{ product.activity_week_count }}
                    </TableLink>
                </td>
            </template>
        </DataTable>

        <Pagination :links="products.meta.links" />
    </AdminLayout>
</template>
