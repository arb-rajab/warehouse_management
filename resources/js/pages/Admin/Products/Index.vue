<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/vue3';
import { Check, QrCode, SlidersHorizontal, X } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { show as showHelp } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    exportQr,
    index as productsIndex,
    updateBoxCount,
} from '@/actions/App/Http/Controllers/Admin/ProductController';
import CellLogActivityFilterFields from '@/components/CellLogActivityFilterFields.vue';
import DataTable from '@/components/DataTable.vue';
import DateRangeFilterFields from '@/components/DateRangeFilterFields.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterNumberField from '@/components/FilterNumberField.vue';
import FilterProductSelect from '@/components/FilterProductSelect.vue';
import HelpLink from '@/components/HelpLink.vue';
import LocationFilterFields from '@/components/LocationFilterFields.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import ProductOccupancyFilterFields from '@/components/ProductOccupancyFilterFields.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
    countActive,
    countBadgeClass,
    createdDateRangeExclusivity,
    dateRangeActive,
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
import { productName } from '@/lib/productName';
import type {
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

const filters = reactive({
    row_id: props.filters.row_id?.toString() ?? '',
    column_number: props.filters.column_number?.toString() ?? '',
    state: props.filters.state ?? '',
    expired: props.filters.expired ?? false,
    expires_within_days: props.filters.expires_within_days?.toString() ?? '',
    inactive: props.filters.inactive ?? false,
    product_id: (props.filters.product_id ?? []).map(String),
    user_id: (props.filters.user_id ?? []).map(String),
    action: [...(props.filters.action ?? [])],
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    created_within_days: props.filters.created_within_days?.toString() ?? '',
    sort_by: props.filters.sort_by ?? '',
    sort_direction: props.filters.sort_direction ?? '',
    per_page: props.filters.per_page ?? 20,
});

const { dateRangeDisabled, createdWithinDaysDisabled } =
    createdDateRangeExclusivity(filters);

const filtersOpen = ref(false);

const activeFilterCount = computed(() =>
    countActive([
        filters.row_id !== '',
        filters.column_number !== '',
        filters.state !== '',
        filters.expired,
        filters.expires_within_days !== '',
        filters.inactive,
        filters.product_id.length > 0,
        filters.user_id.length > 0,
        filters.action.length > 0,
        dateRangeActive(filters),
    ]),
);

/**
 * `inactive` filters to the store admin's `published = 0` products — it
 * restricts which product rows appear, like `product_id`, rather than
 * narrowing what counts as full/opened/expired for a shown row. So it marks
 * the product column filtered, not the occupancy columns (see
 * .ai/rules/products.md — `inactive` is unrelated to cell occupancy).
 */
const productColumnFiltered = computed(
    () => filters.product_id.length > 0 || filters.inactive,
);

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
        dateRangeActive(filters),
);

/**
 * `expired`/`inactive` are only ever included when checked — sending the
 * unchecked `false` would still be a non-empty query value the backend
 * treats as "filled" (see FilterProductsRequest), so each must be omitted
 * rather than sent as literal `false`. Mirrors Cells/Index.vue's
 * `highlightQuery()`.
 */
function filterQuery(): Record<string, FormDataConvertible> {
    const { expired, inactive, ...rest } = filters;

    return {
        ...rest,
        ...(expired ? { expired: true } : {}),
        ...(inactive ? { inactive: true } : {}),
    };
}

function applyFilters(): void {
    router.get(productsIndex().url, filterQuery(), {
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
    filters.row_id = '';
    filters.column_number = '';
    filters.state = '';
    filters.expired = false;
    filters.expires_within_days = '';
    filters.inactive = false;
    filters.product_id = [];
    filters.user_id = [];
    filters.action = [];
    filters.date_from = '';
    filters.date_to = '';
    filters.created_within_days = '';
    filters.sort_by = '';
    filters.sort_direction = '';
    router.get(
        productsIndex().url,
        { per_page: filters.per_page },
        { preserveState: true, replace: true },
    );
}

function onPerPageChange(perPage: number): void {
    filters.per_page = perPage;
    applyFilters();
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

const boxCountDialogProduct = ref<ProductSummary | null>(null);
const boxCountDraft = ref('');

/**
 * Writable computed so FilterDialog's v-model:open can set it to false
 * (X button, backdrop click) without needing a separate watcher.
 */
const boxCountDialogOpen = computed({
    get: () => boxCountDialogProduct.value !== null,
    set: (open: boolean) => {
        if (!open) {
            boxCountDialogProduct.value = null;
        }
    },
});

function openBoxCountDialog(product: ProductSummary): void {
    boxCountDraft.value = String(product.boxes_count);
    boxCountDialogProduct.value = product;
}

/**
 * Saves a product's pallet capacity. `preserveScroll`/`preserveState` keep
 * the row in view and the page filter state intact across the redirect.
 * Client-side validation mirrors the server rule so an invalid draft is
 * rejected without a round trip.
 */
function submitBoxCount(): void {
    const product = boxCountDialogProduct.value;

    if (product === null || boxCountDraft.value === '') {
        return;
    }

    const boxesCount = Number(boxCountDraft.value);

    if (!Number.isInteger(boxesCount) || boxesCount < 1) {
        return;
    }

    router.patch(
        updateBoxCount(product.id).url,
        { boxes_count: boxesCount },
        { preserveScroll: true, preserveState: true },
    );
    boxCountDialogProduct.value = null;
}
</script>

<template>
    <Head :title="t('products.title')" />

    <AdminLayout>
        <PageHeader :title="t('products.title')">
            <div class="flex items-center gap-2">
                <HelpLink :href="showHelp('products')" />
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
                        {{ t('products.filters.sections.occupancy') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <ProductOccupancyFilterFields
                            id-prefix="filter"
                            v-model:state="filters.state"
                            v-model:expired="filters.expired"
                            v-model:expires-within-days="
                                filters.expires_within_days
                            "
                            v-model:inactive="filters.inactive"
                            :expiring-soon-days="expiringSoonDays"
                        >
                            <FilterProductSelect
                                id="filter-product"
                                v-model="filters.product_id"
                                :label="t('cellLog.filters.product')"
                                :all-label="t('cellLog.filters.all')"
                                :selected-count-label="selectedCountLabel"
                                :selected="filterOptions.products"
                            />
                        </ProductOccupancyFilterFields>
                    </div>
                </div>

                <div :class="filterSectionClass">
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellLog.filters.sections.activity') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <CellLogActivityFilterFields
                            id-prefix="filter"
                            v-model:action="filters.action"
                            v-model:user-id="filters.user_id"
                            :actions="filterOptions.actions"
                            :users="filterOptions.users"
                        />

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

        <FilterDialog
            v-model:open="boxCountDialogOpen"
            :title="t('products.columns.boxesPerPallet')"
            :close-label="t('cellLog.filters.close')"
        >
            <form class="space-y-4" @submit.prevent="submitBoxCount">
                <FilterNumberField
                    id="products-box-count"
                    v-model="boxCountDraft"
                    :label="
                        t('products.boxesPerPalletLabel', {
                            product: boxCountDialogProduct
                                ? productName(
                                      boxCountDialogProduct.name,
                                      boxCountDialogProduct.ar_name,
                                  )
                                : '',
                        })
                    "
                />
                <button type="submit" :class="filterApplyButtonClass">
                    <Check class="h-4 w-4 shrink-0" />
                    {{ t('expiringWindow.apply') }}
                </button>
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
                    label: t('products.columns.boxesPerPallet'),
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
                {
                    label: t('products.columns.qr'),
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
                    <ProductOccupancyFilterFields
                        id-prefix="popover-filter"
                        v-model:state="filters.state"
                        v-model:expired="filters.expired"
                        v-model:expires-within-days="
                            filters.expires_within_days
                        "
                        v-model:inactive="filters.inactive"
                        :expiring-soon-days="expiringSoonDays"
                    />
                </div>

                <div v-else-if="key === 'activity'" class="space-y-3">
                    <CellLogActivityFilterFields
                        id-prefix="popover-filter"
                        v-model:action="filters.action"
                        v-model:user-id="filters.user_id"
                        :actions="filterOptions.actions"
                        :users="filterOptions.users"
                    />

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
            </template>

            <template #row="{ row: product }">
                <td class="px-4 py-2">
                    <div
                        class="flex items-center gap-2 font-medium text-gray-900 dark:text-neutral-100"
                    >
                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            :alt="productName(product.name, product.ar_name)"
                            class="h-8 w-8 shrink-0 rounded object-cover"
                        />
                        {{ productName(product.name, product.ar_name) }}
                    </div>
                </td>
                <td class="px-4 py-2">
                    <button
                        type="button"
                        :aria-label="
                            t('products.boxesPerPalletLabel', {
                                product: productName(
                                    product.name,
                                    product.ar_name,
                                ),
                            })
                        "
                        :class="filterTriggerButtonClass"
                        @click="openBoxCountDialog(product)"
                    >
                        {{ product.boxes_count }}
                    </button>
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
                <td class="px-4 py-2">
                    <a
                        :href="exportQr.url(product.id)"
                        :aria-label="
                            t('products.exportQrLabel', {
                                product: productName(
                                    product.name,
                                    product.ar_name,
                                ),
                            })
                        "
                        :title="t('products.columns.qr')"
                        class="inline-flex text-gray-400 hover:text-blue-600 dark:text-neutral-500 dark:hover:text-blue-400"
                    >
                        <QrCode class="h-4 w-4" />
                    </a>
                </td>
            </template>
        </DataTable>

        <Pagination
            :links="products.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </AdminLayout>
</template>
