<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/vue3';
import { Barcode, Plus, SlidersHorizontal, X } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { show as showHelp } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    addBarcode,
    exportQr,
    index as productsIndex,
    removeBarcode,
    updateBoxCount,
    updateMinimumPallets,
} from '@/actions/App/Http/Controllers/Admin/ProductController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import CellLogActivityFilterFields from '@/components/CellLogActivityFilterFields.vue';
import DataTable from '@/components/DataTable.vue';
import DateRangeFilterFields from '@/components/DateRangeFilterFields.vue';
import FilterCheckbox from '@/components/FilterCheckbox.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterDialogFooter from '@/components/FilterDialogFooter.vue';
import FilterNumberField from '@/components/FilterNumberField.vue';
import FilterProductSelect from '@/components/FilterProductSelect.vue';
import HelpLink from '@/components/HelpLink.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import ProductOccupancyFilterFields from '@/components/ProductOccupancyFilterFields.vue';
import ProductSelect from '@/components/ProductSelect.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
    countActive,
    countBadgeClass,
    createdDateRangeExclusivity,
    dateRangeActive,
    fieldLabelClass,
    filterApplyButtonClass,
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
    ProductBarcode,
    ProductFilterOption,
    ProductFilters,
    ProductIndexFilterOptions,
    ProductSummary,
} from '@/types/admin';
import type { QueryParams } from '@/wayfinder';

const props = defineProps<{
    products: Paginated<ProductSummary>;
    expiringSoonDays: number;
    filters: ProductFilters;
    filterOptions: ProductIndexFilterOptions;
}>();

const filters = reactive({
    state: props.filters.state ?? '',
    expired: props.filters.expired ?? false,
    expires_within_days: props.filters.expires_within_days?.toString() ?? '',
    inactive: props.filters.inactive ?? false,
    low_stock: props.filters.low_stock ?? false,
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
        filters.state !== '',
        filters.expired,
        filters.expires_within_days !== '',
        filters.inactive,
        filters.low_stock,
        filters.product_id.length > 0,
        filters.user_id.length > 0,
        filters.action.length > 0,
        dateRangeActive(filters),
    ]),
);

/**
 * `inactive`/`low_stock` both restrict which product rows appear, like
 * `product_id`, rather than narrowing what counts as full/opened/expired for
 * a shown row. So they mark the product column filtered, not the occupancy
 * columns (see .ai/rules/products.md — `inactive` is unrelated to cell
 * occupancy, and `low_stock` is a stock-level check, not an occupancy one).
 */
const productColumnFiltered = computed(
    () =>
        filters.product_id.length > 0 || filters.inactive || filters.low_stock,
);

/**
 * State narrows every occupancy-derived column identically on the backend
 * (see `Product::withOccupancyCounts()`/`matchingHistoryFilters()`), so
 * the Full/Opened/Expired/Expiring-soon
 * columns are always marked filtered together.
 */
const occupancyColumnsFiltered = computed(
    () =>
        filters.state !== '' ||
        filters.expired ||
        filters.expires_within_days !== '',
);

/**
 * `expired`/`inactive`/`low_stock` are only ever included when checked —
 * sending the unchecked `false` would still be a non-empty query value the
 * backend treats as "filled" (see FilterProductsRequest), so each must be
 * omitted rather than sent as literal `false`. Mirrors Cells/Index.vue's
 * `highlightQuery()`.
 */
function filterQuery(): Record<string, FormDataConvertible> {
    const { expired, inactive, low_stock, ...rest } = filters;

    return {
        ...rest,
        ...(expired ? { expired: true } : {}),
        ...(inactive ? { inactive: true } : {}),
        ...(low_stock ? { low_stock: true } : {}),
    };
}

function applyFilters(): void {
    router.get(productsIndex().url, filterQuery(), {
        preserveState: true,
        replace: true,
    });
    filtersOpen.value = false;
}

/**
 * The always-visible toolbar search — a faster path to the same
 * `filters.product_id` the column-filter popover's multi-select already
 * writes to, not a second filter param. Resets to empty once the request is
 * sent: it's a quick jump into a single product, not a persistent display of
 * the current filter (the "Product" column's filter icon shows that).
 */
const quickSearchProduct = ref<ProductFilterOption | null>(null);

watch(quickSearchProduct, (product) => {
    if (!product) {
        return;
    }

    filters.product_id = [String(product.id)];
    applyFilters();
    quickSearchProduct.value = null;
});

const { openFilterKey } = useColumnFilterPopover(
    filters,
    filtersOpen,
    applyFilters,
);

function clearFilters(): void {
    filters.state = '';
    filters.expired = false;
    filters.expires_within_days = '';
    filters.inactive = false;
    filters.low_stock = false;
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

function occupancyHref(
    product: ProductSummary,
    overrides: QueryParams,
): string {
    return cellsIndex.url({
        query: { product_id: [product.id], ...overrides },
    });
}

/**
 * One in-flight mutation / error message shared by the box-count,
 * minimum-pallets and barcodes dialogs — only one of them is ever open, so a
 * single pair serves all three. Dialogs close (or clear their draft) only in
 * `onSuccess`, so a failed request leaves the input in place with the
 * server's message beside it instead of discarding the user's edit.
 */
const mutationProcessing = ref(false);
const mutationError = ref('');

function mutationOptions(onSuccess: () => void) {
    return {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            mutationProcessing.value = true;
            mutationError.value = '';
        },
        onSuccess,
        onError: (errors: Record<string, string>) => {
            mutationError.value =
                Object.values(errors)[0] ?? t('products.saveFailed');
        },
        onFinish: () => {
            mutationProcessing.value = false;
        },
    };
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
    mutationError.value = '';
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

    if (product === null) {
        return;
    }

    const boxesCount = Number(boxCountDraft.value);

    if (
        boxCountDraft.value === '' ||
        !Number.isInteger(boxesCount) ||
        boxesCount < 1
    ) {
        mutationError.value = t('products.invalidCount');

        return;
    }

    router.patch(
        updateBoxCount(product.id, { mergeQuery: {} }).url,
        { boxes_count: boxesCount },
        mutationOptions(() => {
            boxCountDialogProduct.value = null;
        }),
    );
}

const minimumPalletsDialogProduct = ref<ProductSummary | null>(null);
const minimumPalletsDraft = ref('');

/**
 * Writable computed so FilterDialog's v-model:open can set it to false
 * (X button, backdrop click) without needing a separate watcher.
 */
const minimumPalletsDialogOpen = computed({
    get: () => minimumPalletsDialogProduct.value !== null,
    set: (open: boolean) => {
        if (!open) {
            minimumPalletsDialogProduct.value = null;
        }
    },
});

function openMinimumPalletsDialog(product: ProductSummary): void {
    mutationError.value = '';
    minimumPalletsDraft.value =
        product.minimum_pallets === null ? '' : String(product.minimum_pallets);
    minimumPalletsDialogProduct.value = product;
}

/**
 * Saves (or, with a cleared field, removes) a product's warehouse-stock
 * minimum. `preserveScroll`/`preserveState` keep the row in view and the
 * page filter state intact across the redirect. An empty draft submits
 * `null` rather than being rejected — clearing the threshold is a valid
 * action here, unlike the box count, which is always required.
 */
function submitMinimumPallets(): void {
    const product = minimumPalletsDialogProduct.value;

    if (product === null) {
        return;
    }

    const minimumPallets =
        minimumPalletsDraft.value === ''
            ? null
            : Number(minimumPalletsDraft.value);

    if (
        minimumPallets !== null &&
        (!Number.isInteger(minimumPallets) || minimumPallets < 1)
    ) {
        mutationError.value = t('products.invalidCount');

        return;
    }

    router.patch(
        updateMinimumPallets(product.id, { mergeQuery: {} }).url,
        { minimum_pallets: minimumPallets },
        mutationOptions(() => {
            minimumPalletsDialogProduct.value = null;
        }),
    );
}

function isLowStock(product: ProductSummary): boolean {
    return (
        product.minimum_pallets !== null &&
        product.pallets_count < product.minimum_pallets
    );
}

const barcodesDialogProduct = ref<ProductSummary | null>(null);
const newBarcodeDraft = ref('');

/**
 * Writable computed so FilterDialog's v-model:open can set it to false
 * (X button, backdrop click) without needing a separate watcher.
 */
const barcodesDialogOpen = computed({
    get: () => barcodesDialogProduct.value !== null,
    set: (open: boolean) => {
        if (!open) {
            barcodesDialogProduct.value = null;
        }
    },
});

function openBarcodesDialog(product: ProductSummary): void {
    mutationError.value = '';
    newBarcodeDraft.value = '';
    barcodesDialogProduct.value = product;
}

/**
 * Unlike the single-value box-count/minimum-pallets dialogs (which close on
 * submit), this dialog manages a list and stays open across additions/
 * removals — so after each mutation it re-points itself at the freshest copy
 * of the product from the now-updated `products` prop, rather than closing.
 */
function refreshBarcodesDialogProduct(productId: number): void {
    barcodesDialogProduct.value =
        props.products.data.find((product) => product.id === productId) ?? null;
}

function submitNewBarcode(): void {
    const product = barcodesDialogProduct.value;
    const barcode = newBarcodeDraft.value.trim();

    if (product === null || barcode === '') {
        return;
    }

    router.post(
        addBarcode(product.id, { mergeQuery: {} }).url,
        { barcode },
        mutationOptions(() => {
            newBarcodeDraft.value = '';
            refreshBarcodesDialogProduct(product.id);
        }),
    );
}

function removeProductBarcode(barcode: ProductBarcode): void {
    const product = barcodesDialogProduct.value;

    if (product === null) {
        return;
    }

    router.delete(
        removeBarcode.url(
            { product: product.id, barcode: barcode.id },
            { mergeQuery: {} },
        ),
        mutationOptions(() => refreshBarcodesDialogProduct(product.id)),
    );
}
</script>

<template>
    <Head :title="t('products.title')" />

    <AdminLayout>
        <PageHeader :title="t('products.title')">
            <div class="flex items-end gap-2">
                <ProductSelect
                    id="products-quick-search"
                    class="w-64"
                    v-model="quickSearchProduct"
                    :label="t('products.quickSearch.label')"
                    :placeholder="t('products.quickSearch.placeholder')"
                />
                <FilterCheckbox
                    id="products-quick-inactive"
                    v-model="filters.inactive"
                    :label="t('products.filters.inactive')"
                    @update:model-value="applyFilters"
                />
                <FilterCheckbox
                    id="products-quick-low-stock"
                    v-model="filters.low_stock"
                    :label="t('products.filters.lowStock')"
                    @update:model-value="applyFilters"
                />
                <HelpLink :href="showHelp('products')" />
                <button
                    type="button"
                    :class="filterTriggerButtonClass"
                    @click="filtersOpen = true"
                >
                    <SlidersHorizontal class="h-4 w-4" />
                    {{ t('products.filters.title') }}
                    <span v-if="activeFilterCount > 0" :class="countBadgeClass">
                        {{ activeFilterCount }}
                    </span>
                </button>
            </div>
        </PageHeader>

        <FilterDialog
            v-model:open="filtersOpen"
            :title="t('products.filters.title')"
            :close-label="t('products.filters.close')"
        >
            <form
                id="products-filter-form"
                class="space-y-6"
                @submit.prevent="applyFilters"
            >
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
            </form>

            <template #footer>
                <FilterDialogFooter
                    form-id="products-filter-form"
                    :apply-label="t('cellLog.filters.apply')"
                    :clear-label="t('cellLog.filters.clear')"
                    @clear="clearFilters"
                />
            </template>
        </FilterDialog>

        <FilterDialog
            v-model:open="boxCountDialogOpen"
            :title="t('products.columns.boxesPerPallet')"
            :close-label="t('cellLog.filters.close')"
        >
            <form
                id="products-box-count-form"
                class="space-y-4"
                @submit.prevent="submitBoxCount"
            >
                <ActionErrorBanner :message="mutationError" />
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
            </form>

            <template #footer>
                <SubmitButton
                    form="products-box-count-form"
                    :label="t('expiringWindow.apply')"
                    :processing-label="t('products.saving')"
                    :processing="mutationProcessing"
                />
            </template>
        </FilterDialog>

        <FilterDialog
            v-model:open="minimumPalletsDialogOpen"
            :title="t('products.columns.minimumPallets')"
            :close-label="t('cellLog.filters.close')"
        >
            <form
                id="products-minimum-pallets-form"
                class="space-y-4"
                @submit.prevent="submitMinimumPallets"
            >
                <ActionErrorBanner :message="mutationError" />
                <FilterNumberField
                    id="products-minimum-pallets"
                    v-model="minimumPalletsDraft"
                    :label="
                        t('products.minimumPalletsLabel', {
                            product: minimumPalletsDialogProduct
                                ? productName(
                                      minimumPalletsDialogProduct.name,
                                      minimumPalletsDialogProduct.ar_name,
                                  )
                                : '',
                        })
                    "
                    :placeholder="t('products.minimumPalletsPlaceholder')"
                />
            </form>

            <template #footer>
                <SubmitButton
                    form="products-minimum-pallets-form"
                    :label="t('expiringWindow.apply')"
                    :processing-label="t('products.saving')"
                    :processing="mutationProcessing"
                />
            </template>
        </FilterDialog>

        <FilterDialog
            v-model:open="barcodesDialogOpen"
            :title="t('products.columns.barcodes')"
            :close-label="t('cellLog.filters.close')"
        >
            <ActionErrorBanner :message="mutationError" />
            <form
                id="products-add-barcode-form"
                class="flex items-end gap-2"
                @submit.prevent="submitNewBarcode"
            >
                <div class="flex-1">
                    <label for="products-new-barcode" :class="fieldLabelClass">
                        {{
                            t('products.barcodesLabel', {
                                product: barcodesDialogProduct
                                    ? productName(
                                          barcodesDialogProduct.name,
                                          barcodesDialogProduct.ar_name,
                                      )
                                    : '',
                            })
                        }}
                    </label>
                    <input
                        id="products-new-barcode"
                        v-model="newBarcodeDraft"
                        type="text"
                        :placeholder="t('products.newBarcodePlaceholder')"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="mutationProcessing"
                    :class="filterApplyButtonClass"
                >
                    <Plus class="h-4 w-4 shrink-0" />
                    {{ t('products.addBarcode') }}
                </button>
            </form>

            <p
                v-if="(barcodesDialogProduct?.barcodes.length ?? 0) === 0"
                class="mt-4 text-sm text-gray-500 dark:text-neutral-400"
            >
                {{ t('products.noBarcodes') }}
            </p>
            <ul
                v-else
                class="mt-4 divide-y divide-gray-100 dark:divide-neutral-700"
            >
                <li
                    v-for="barcode in barcodesDialogProduct?.barcodes ?? []"
                    :key="barcode.id"
                    class="flex items-center justify-between gap-2 py-2 text-sm"
                >
                    {{ barcode.barcode }}
                    <button
                        type="button"
                        :aria-label="
                            t('products.removeBarcode', {
                                barcode: barcode.barcode,
                            })
                        "
                        :disabled="mutationProcessing"
                        class="text-gray-400 hover:text-red-600 disabled:opacity-50 dark:text-neutral-500 dark:hover:text-red-400"
                        @click="removeProductBarcode(barcode)"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </li>
            </ul>
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
                    label: t('products.columns.minimumPallets'),
                },
                {
                    label: t('products.columns.barcodes'),
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
                    <button
                        type="button"
                        :aria-label="
                            t('products.minimumPalletsLabel', {
                                product: productName(
                                    product.name,
                                    product.ar_name,
                                ),
                            })
                        "
                        :class="[
                            filterTriggerButtonClass,
                            isLowStock(product)
                                ? 'text-red-600 dark:text-red-400'
                                : '',
                        ]"
                        @click="openMinimumPalletsDialog(product)"
                    >
                        {{ product.minimum_pallets ?? '—' }}
                    </button>
                </td>
                <td class="px-4 py-2">
                    <button
                        type="button"
                        :aria-label="
                            t('products.barcodesLabel', {
                                product: productName(
                                    product.name,
                                    product.ar_name,
                                ),
                            })
                        "
                        :class="filterTriggerButtonClass"
                        @click="openBarcodesDialog(product)"
                    >
                        {{ product.barcodes.length }}
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
                        <Barcode class="h-4 w-4" />
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
