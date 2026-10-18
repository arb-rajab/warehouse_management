<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Download, Eye, Plus, SlidersHorizontal, X } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { show as showHelp } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as palletsIndex } from '@/actions/App/Http/Controllers/Admin/PalletController';
import {
    destroyBarcode,
    downloadAllBarcodes,
    downloadBarcode,
    previewBarcode,
    previewDefaultBarcode,
    exportQr,
    index as productsIndex,
    storeBarcode,
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
import ProductActiveBadge from '@/components/ProductActiveBadge.vue';
import ProductOccupancyFilterFields from '@/components/ProductOccupancyFilterFields.vue';
import ProductSelect from '@/components/ProductSelect.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
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
    plainFieldInputClass,
    selectedCountLabel,
    toggleSort,
    useColumnFilterPopover,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import { productName } from '@/lib/productName';
import type {
    Paginated,
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
    hide_inactive: props.filters.hide_inactive ?? false,
    low_stock: props.filters.low_stock ?? false,
    product_id: (props.filters.product_id ?? []).map(String),
    user_id: (props.filters.user_id ?? []).map(String),
    action: [...(props.filters.action ?? [])],
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    created_within_days: props.filters.created_within_days?.toString() ?? '',
    sort_by: props.filters.sort_by ?? 'name',
    sort_direction: props.filters.sort_direction ?? 'asc',
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
        filters.hide_inactive,
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
        filters.product_id.length > 0 ||
        filters.inactive ||
        filters.hide_inactive ||
        filters.low_stock,
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
 * omitted rather than sent as literal `false`.
 */
function filterQuery(): Record<string, FormDataConvertible> {
    const { expired, inactive, hide_inactive, low_stock, ...rest } = filters;

    return {
        ...rest,
        ...(expired ? { expired: true } : {}),
        ...(inactive ? { inactive: true } : {}),
        ...(hide_inactive ? { hide_inactive: true } : {}),
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
    filters.hide_inactive = false;
    filters.low_stock = false;
    filters.product_id = [];
    filters.user_id = [];
    filters.action = [];
    filters.date_from = '';
    filters.date_to = '';
    filters.created_within_days = '';
    filters.sort_by = 'name';
    filters.sort_direction = 'asc';
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
    return palletsIndex.url({
        query: { product_id: [product.id], ...overrides },
    });
}

/**
 * Shared by the box-count, minimum-pallets and barcode requests: keep the row
 * in view and the page's filter state intact across the redirect. Dialogs
 * close (or clear their input) only on success, so a failed request leaves the
 * input in place with the server's message beside it.
 */
const mutationOptions = { preserveScroll: true, preserveState: true };

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
    minimumPalletsDraft.value =
        product.minimum_pallets === null ? '' : String(product.minimum_pallets);
    minimumPalletsDialogProduct.value = product;
}

function isLowStock(product: ProductSummary): boolean {
    return (
        product.minimum_pallets !== null &&
        product.pallets_count < product.minimum_pallets
    );
}

const barcodesDialogProduct = ref<ProductSummary | null>(null);
/** The barcode whose printable label is shown inline in the dialog, if any. */
const previewedBarcodeId = ref<number | 'default' | null>(null);

function togglePreview(barcodeId: number | 'default'): void {
    previewedBarcodeId.value =
        previewedBarcodeId.value === barcodeId ? null : barcodeId;
}

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
    downloadError.value = '';
    previewedBarcodeId.value = null;
    barcodesDialogProduct.value = product;
}

const downloadError = ref('');

/**
 * Fetched rather than followed as a plain link so a failed response (a barcode
 * that cannot be rendered, a lost session, a network error) surfaces as a
 * message in the dialog instead of navigating the admin to an error page.
 */
async function downloadFile(url: string): Promise<void> {
    downloadError.value = '';

    try {
        const response = await fetch(url, { credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error(`Download failed with status ${response.status}`);
        }

        const blob = await response.blob();
        const fileName =
            /filename="([^"]+)"/.exec(
                response.headers.get('Content-Disposition') ?? '',
            )?.[1] ?? 'barcode';
        const objectUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = objectUrl;
        link.download = fileName;
        document.body.append(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(objectUrl);
    } catch {
        downloadError.value = t('products.downloadFailed');
    }
}

/**
 * Unlike the single-value box-count/minimum-pallets dialogs (which close on
 * submit), this dialog manages a list and stays open across additions/
 * removals — so after each mutation it re-points itself at the freshest copy
 * of the product from the now-updated `products` prop, rather than closing.
 */
function refreshBarcodesDialogProduct(): void {
    const productId = barcodesDialogProduct.value?.id;

    barcodesDialogProduct.value =
        props.products.data.find((product) => product.id === productId) ?? null;
}
</script>

<template>
    <Head :title="t('products.title')" />

    <AdminLayout>
        <PageHeader :title="t('products.title')">
            <div class="flex w-full flex-wrap items-end gap-2 md:w-auto">
                <ProductSelect
                    id="products-quick-search"
                    class="w-full md:w-64"
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
                    id="products-quick-hide-inactive"
                    v-model="filters.hide_inactive"
                    :label="t('products.filters.hideInactive')"
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
                                include-inactive
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
                    :apply-label="t('common.filters.apply')"
                    :clear-label="t('common.filters.clear')"
                    @clear="clearFilters"
                />
            </template>
        </FilterDialog>

        <FilterDialog
            v-model:open="boxCountDialogOpen"
            :title="t('products.columns.boxesPerPallet')"
            :close-label="t('common.close')"
        >
            <Form
                v-if="boxCountDialogProduct"
                :action="
                    updateBoxCount(boxCountDialogProduct, { mergeQuery: {} })
                "
                :options="mutationOptions"
                class="space-y-4"
                #default="{ errors, processing }"
                @success="boxCountDialogProduct = null"
            >
                <ActionErrorBanner :message="firstError(errors)" />
                <FilterNumberField
                    id="products-box-count"
                    v-model="boxCountDraft"
                    name="boxes_count"
                    required
                    :label="
                        t('products.boxesPerPalletLabel', {
                            product: productName(
                                boxCountDialogProduct.name,
                                boxCountDialogProduct.ar_name,
                            ),
                        })
                    "
                />
                <SubmitButton
                    :label="t('expiringWindow.apply')"
                    :processing-label="t('products.saving')"
                    :processing="processing"
                />
            </Form>
        </FilterDialog>

        <FilterDialog
            v-model:open="minimumPalletsDialogOpen"
            :title="t('products.columns.minimumPallets')"
            :close-label="t('common.close')"
        >
            <Form
                v-if="minimumPalletsDialogProduct"
                :action="
                    updateMinimumPallets(minimumPalletsDialogProduct, {
                        mergeQuery: {},
                    })
                "
                :options="mutationOptions"
                class="space-y-4"
                #default="{ errors, processing }"
                @success="minimumPalletsDialogProduct = null"
            >
                <ActionErrorBanner :message="firstError(errors)" />
                <FilterNumberField
                    id="products-minimum-pallets"
                    v-model="minimumPalletsDraft"
                    name="minimum_pallets"
                    :label="
                        t('products.minimumPalletsLabel', {
                            product: productName(
                                minimumPalletsDialogProduct.name,
                                minimumPalletsDialogProduct.ar_name,
                            ),
                        })
                    "
                    :placeholder="t('products.minimumPalletsPlaceholder')"
                />
                <SubmitButton
                    :label="t('expiringWindow.apply')"
                    :processing-label="t('products.saving')"
                    :processing="processing"
                />
            </Form>
        </FilterDialog>

        <FilterDialog
            v-model:open="barcodesDialogOpen"
            :title="t('products.columns.barcodes')"
            :close-label="t('common.close')"
        >
            <template v-if="barcodesDialogProduct">
                <Form
                    :action="
                        storeBarcode(barcodesDialogProduct, { mergeQuery: {} })
                    "
                    :options="mutationOptions"
                    reset-on-success
                    #default="{ errors, processing }"
                    @success="refreshBarcodesDialogProduct()"
                >
                    <ActionErrorBanner :message="firstError(errors)" />
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label
                                for="products-new-barcode"
                                :class="fieldLabelClass"
                            >
                                {{
                                    t('products.barcodesLabel', {
                                        product: productName(
                                            barcodesDialogProduct.name,
                                            barcodesDialogProduct.ar_name,
                                        ),
                                    })
                                }}
                            </label>
                            <input
                                id="products-new-barcode"
                                type="text"
                                name="barcode"
                                required
                                :placeholder="
                                    t('products.newBarcodePlaceholder')
                                "
                                :class="plainFieldInputClass"
                            />
                        </div>
                        <button
                            type="submit"
                            :disabled="processing"
                            :class="filterApplyButtonClass"
                        >
                            <Plus class="h-4 w-4 shrink-0" />
                            {{ t('products.addBarcode') }}
                        </button>
                    </div>
                </Form>

                <ActionErrorBanner class="mt-4" :message="downloadError" />
                <button
                    type="button"
                    :class="[filterApplyButtonClass, 'mt-4']"
                    @click="
                        downloadFile(
                            downloadAllBarcodes.url(barcodesDialogProduct),
                        )
                    "
                >
                    <Download class="h-4 w-4 shrink-0" />
                    {{ t('products.downloadAllBarcodes') }}
                </button>
                <ul
                    class="mt-4 divide-y divide-gray-100 dark:divide-neutral-700"
                >
                    <li data-testid="default-barcode" class="py-2 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                {{ barcodesDialogProduct.id }}
                                <span
                                    :title="t('products.defaultBarcodeHint')"
                                    class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-neutral-700 dark:text-neutral-300"
                                >
                                    {{ t('products.defaultBarcode') }}
                                </span>
                            </span>
                            <span class="flex items-center gap-3">
                                <button
                                    type="button"
                                    :aria-label="
                                        t('products.viewBarcode', {
                                            barcode: barcodesDialogProduct.id,
                                        })
                                    "
                                    :aria-pressed="
                                        previewedBarcodeId === 'default'
                                    "
                                    class="cursor-pointer text-gray-400 hover:text-blue-600 dark:text-neutral-500 dark:hover:text-blue-400"
                                    @click="togglePreview('default')"
                                >
                                    <Eye class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    :aria-label="
                                        t('products.downloadBarcode', {
                                            barcode: barcodesDialogProduct.id,
                                        })
                                    "
                                    class="cursor-pointer text-gray-400 hover:text-blue-600 dark:text-neutral-500 dark:hover:text-blue-400"
                                    @click="
                                        downloadFile(
                                            exportQr.url(barcodesDialogProduct),
                                        )
                                    "
                                >
                                    <Download class="h-4 w-4" />
                                </button>
                            </span>
                        </div>
                        <img
                            v-if="previewedBarcodeId === 'default'"
                            :src="
                                previewDefaultBarcode.url(barcodesDialogProduct)
                            "
                            :alt="String(barcodesDialogProduct.id)"
                            class="mt-2 max-w-full rounded border border-gray-200 bg-white dark:border-neutral-700"
                        />
                    </li>
                    <li
                        v-for="barcode in barcodesDialogProduct.barcodes"
                        :key="barcode.id"
                        class="py-2 text-sm"
                    >
                        <div class="flex items-center justify-between gap-2">
                            {{ barcode.barcode }}
                            <span class="flex items-center gap-3">
                                <button
                                    type="button"
                                    :aria-label="
                                        t('products.viewBarcode', {
                                            barcode: barcode.barcode,
                                        })
                                    "
                                    :aria-pressed="
                                        previewedBarcodeId === barcode.id
                                    "
                                    class="cursor-pointer text-gray-400 hover:text-blue-600 dark:text-neutral-500 dark:hover:text-blue-400"
                                    @click="togglePreview(barcode.id)"
                                >
                                    <Eye class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    :aria-label="
                                        t('products.downloadBarcode', {
                                            barcode: barcode.barcode,
                                        })
                                    "
                                    class="cursor-pointer text-gray-400 hover:text-blue-600 dark:text-neutral-500 dark:hover:text-blue-400"
                                    @click="
                                        downloadFile(
                                            downloadBarcode.url({
                                                product: barcodesDialogProduct,
                                                barcode,
                                            }),
                                        )
                                    "
                                >
                                    <Download class="h-4 w-4" />
                                </button>
                                <Link
                                    :href="
                                        destroyBarcode(
                                            {
                                                product: barcodesDialogProduct,
                                                barcode,
                                            },
                                            { mergeQuery: {} },
                                        )
                                    "
                                    as="button"
                                    preserve-scroll
                                    preserve-state
                                    :aria-label="
                                        t('products.removeBarcode', {
                                            barcode: barcode.barcode,
                                        })
                                    "
                                    :on-before="
                                        () => confirmDelete(barcode.barcode)
                                    "
                                    :on-success="refreshBarcodesDialogProduct"
                                    class="cursor-pointer text-gray-400 hover:text-red-600 dark:text-neutral-500 dark:hover:text-red-400"
                                >
                                    <X class="h-4 w-4" />
                                </Link>
                            </span>
                        </div>
                        <img
                            v-if="previewedBarcodeId === barcode.id"
                            :src="
                                previewBarcode.url({
                                    product: barcodesDialogProduct,
                                    barcode,
                                })
                            "
                            :alt="barcode.barcode"
                            class="mt-2 max-w-full rounded border border-gray-200 bg-white dark:border-neutral-700"
                        />
                    </li>
                </ul>
                <p
                    v-if="barcodesDialogProduct.barcodes.length === 0"
                    class="mt-4 text-sm text-gray-500 dark:text-neutral-400"
                >
                    {{ t('products.noBarcodes') }}
                </p>
            </template>
        </FilterDialog>

        <DataTable
            v-model:open-filter-key="openFilterKey"
            :columns="[
                {
                    label: t('products.columns.id'),
                    sortKey: 'id',
                },
                {
                    label: t('products.columns.product'),
                    sortKey: 'name',
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
                        include-inactive
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
                <td class="px-4 py-2 text-gray-500 dark:text-neutral-400">
                    {{ product.id }}
                </td>
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
                        <ProductActiveBadge :active="product.active" />
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
                        {{ product.barcodes.length + 1 }}
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
            </template>
        </DataTable>

        <Pagination
            :links="products.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </AdminLayout>
</template>
