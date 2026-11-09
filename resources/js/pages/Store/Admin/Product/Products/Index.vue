<script setup lang="ts">
import { Form, Head, Link, router, useHttp } from '@inertiajs/vue3';
import {
    Copy,
    Eye,
    Pencil,
    RefreshCw,
    ShoppingCart,
    Tag,
    Trash2,
    TrendingUp,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { fetch as fetchProducts } from '@/actions/App/Http/Controllers/ProductController';
import { edit as categoryEdit } from '@/actions/App/Http/Controllers/Store/Admin/CategoryController';
import {
    all as allProducts,
    bulkDestroy,
    create,
    destroy,
    duplicate,
    edit,
    inHouse as inHouseProducts,
    seller as sellerProducts,
    sellerPublished,
    sellerFeatured,
    updateSaleStatus,
    updateTodaysDeal,
    updateProductApproval,
    sellerDuplicate,
    sellerDestroy,
    sellerBulkDestroy,
    sellerEdit,
    sellerOptions,
    updateFeatured,
    updateForSale,
    updatePublished,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import IconStat from '@/components/IconStat.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreListFilters from '@/components/StoreListFilters.vue';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import StoreStatusSwitch from '@/components/StoreStatusSwitch.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import {
    fieldLabelClass,
    plainFieldInputClass,
    selectedToggleClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type { SearchOption } from '@/types/searchSelect';
import type {
    StoreFilterChip,
    StoreProductListFilters,
    StoreProductListPreset,
    StoreProductListRow,
    StoreProductListType,
} from '@/types/store';

const props = withDefaults(
    defineProps<{
        listType: StoreProductListType;
        products: Paginated<StoreProductListRow>;
        filters: StoreProductListFilters;
        categories: { id: number; name: string }[];
        defaultLanguage: string;
        approvalEnabled?: boolean;
        /** The seller the list is filtered by; the others are searched on demand. */
        selectedSeller?: SearchOption | null;
    }>(),
    { approvalEnabled: false, selectedSeller: null },
);
const seller = ref<SearchOption | null>(props.selectedSeller);
const access = useStoreAccess();
const isAllList = computed(() => props.listType === 'all');
const isSellerList = computed(() => props.listType === 'seller');
const titleKey = computed(() =>
    isSellerList.value
        ? 'store.nav.sellerProducts'
        : isAllList.value
          ? 'store.products.allTitle'
          : 'store.products.inHouseTitle',
);
const listActions = computed(() =>
    isSellerList.value
        ? {
              edit: sellerEdit,
              duplicate: sellerDuplicate,
              destroy: sellerDestroy,
              bulkDestroy: sellerBulkDestroy,
          }
        : { edit, duplicate, destroy, bulkDestroy },
);
const listIndex = computed(
    () =>
        ({ admin: inHouseProducts, all: allProducts, seller: sellerProducts })[
            props.listType
        ],
);
/** Every row action names the list it came from and returns to it. */
const listQuery = computed(() => ({ mergeQuery: { list: props.listType } }));
const sorts = [
    { value: 'rating,desc', label: 'sortRatingDesc' },
    { value: 'rating,asc', label: 'sortRatingAsc' },
    { value: 'num_of_sale,desc', label: 'sortSalesDesc' },
    { value: 'num_of_sale,asc', label: 'sortSalesAsc' },
    { value: 'unit_price,desc', label: 'sortPriceDesc' },
    { value: 'unit_price,asc', label: 'sortPriceAsc' },
] as const;
/**
 * The in-house and seller lists' state boxes. The all list has the preset
 * links instead (store-list-filter-placement.md): a filter is never in both.
 */
const stateFilters = [
    { name: 'active', label: 'active' },
    { name: 'for_sale', label: 'forSale' },
    { name: 'featured', label: 'featured' },
] as const;
/** Dialog filters only, one chip each; search and the inline seller/category have none. */
const appliedFilters = computed<StoreFilterChip[]>(() => {
    const chips: StoreFilterChip[] = [];
    const { approved, category_id: categoryId, type } = props.filters;

    if (
        isSellerList.value &&
        props.approvalEnabled &&
        approved !== null &&
        approved !== undefined
    ) {
        chips.push({
            key: 'approved',
            label: t('store.products.approvalStatus'),
            value: t(
                approved
                    ? 'store.products.approved'
                    : 'store.products.unapproved',
            ),
            keys: ['approved'],
        });
    }

    if (isSellerList.value && categoryId !== null && categoryId !== undefined) {
        chips.push({
            key: 'category_id',
            label: t('store.products.category'),
            value:
                props.categories.find((category) => category.id === categoryId)
                    ?.name ?? String(categoryId),
            keys: ['category_id'],
        });
    }

    const sort = sorts.find((option) => option.value === type);

    if (type) {
        chips.push({
            key: 'type',
            label: t('store.products.sort'),
            value: sort ? t(`store.products.${sort.label}`) : type,
            keys: ['type'],
        });
    }

    if (!isAllList.value) {
        stateFilters
            .filter((state) => props.filters[state.name] === true)
            .forEach((state) =>
                chips.push({
                    key: state.name,
                    label: t(`store.products.${state.label}`),
                    keys: [state.name],
                }),
            );
    }

    return chips;
});
const presets: { name: StoreProductListPreset; label: string }[] = [
    { name: 'allProducts', label: 'presetAllProducts' },
    { name: 'allActive', label: 'presetAllActive' },
    { name: 'allForSale', label: 'presetAllForSale' },
    { name: 'allFeatured', label: 'presetAllFeatured' },
    { name: 'allDeleted', label: 'presetAllDeleted' },
];
const switches = computed(
    () =>
        [
            {
                field: 'for_sale',
                label: 'store.products.forSale',
                action: isSellerList.value ? updateSaleStatus : updateForSale,
                inputName: 'status',
            },
            {
                field: 'published',
                label: 'store.products.active',
                action: isSellerList.value ? sellerPublished : updatePublished,
                inputName: 'status',
            },
            ...(isSellerList.value && props.approvalEnabled
                ? [
                      {
                          field: 'approved',
                          label: 'store.products.approved',
                          action: updateProductApproval,
                          inputName: 'approved',
                      } as const,
                  ]
                : []),
            {
                field: 'featured',
                label: 'store.products.featured',
                action: isSellerList.value ? sellerFeatured : updateFeatured,
                inputName: 'status',
            },
            ...(isSellerList.value
                ? [
                      {
                          field: 'todays_deal',
                          label: 'store.products.form.todaysDeal',
                          action: updateTodaysDeal,
                          inputName: 'status',
                      } as const,
                  ]
                : []),
        ] as const,
);
/**
 * Active on a feed-origin row while Otajer owns `published`: the guard
 * refuses the write, so the switch shows why instead of offering it.
 */
const publishedLock = computed(() => ({
    reason: t('store.products.otajerLocked'),
    hint: t('store.products.form.otajerSynced'),
}));
/** The cells below test `shown`; the header comes from `visibleColumns`. */
const columns = useTableColumns({
    listId: () => `product.products.${props.listType}`,
    columns: () => [
        { key: 'name', label: 'store.products.name', required: true },
        { key: 'id', label: 'store.products.id' },
        { key: 'unit', label: 'store.products.unit' },
        { key: 'info', label: 'store.products.info' },
        { key: 'stock', label: 'store.products.totalStock' },
        { key: 'category', label: 'store.products.category' },
        ...switches.value.map((item) => ({
            key: item.field,
            label: item.label,
        })),
        { key: 'deleted', label: 'store.products.deletedFromOtajer' },
    ],
});
const { shown, visibleColumns } = columns;
const visibleSwitches = computed(() =>
    switches.value.filter((item) => shown.value[item.field]),
);
const tableColumns = computed(() => [
    ...(access.value.can('product_delete') ? [t('store.products.select')] : []),
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.products.actions'),
]);
const selected = ref<number[]>([]);
const allSelected = computed(
    () =>
        props.products.data.length > 0 &&
        props.products.data.every((product) =>
            selected.value.includes(product.id),
        ),
);
watch(
    () => props.products.data.map((product) => product.id).join(','),
    () => {
        selected.value = [];
    },
);
const fetchRequest = useHttp({});
const fetchNotice = ref<'fetchStarted' | 'fetchBusy' | null>(null);

function selectAll(checked: boolean): void {
    selected.value = checked
        ? props.products.data.map((product) => product.id)
        : [];
}
/** The current filters as the list's own query string. */
function currentQuery(perPage: number): Record<string, string | number> {
    const query: Record<string, string | number> = { per_page: perPage };

    for (const key of [
        'search',
        'category_id',
        'type',
        'user_id',
        'approved',
    ] as const) {
        const value = props.filters[key];

        if (value !== null && value !== undefined && value !== '') {
            query[key] = typeof value === 'boolean' ? Number(value) : value;
        }
    }

    // The all list may still carry the legacy state flags from a link.
    for (const flag of [
        ...stateFilters.map((s) => s.name),
        'deleted',
    ] as const) {
        if (props.filters[flag] === true) {
            query[flag] = 1;
        }
    }

    if (props.filters.preset) {
        query[props.filters.preset] = 1;
    }

    return query;
}
function onPerPageChange(perPage: number): void {
    router.get(listIndex.value().url, currentQuery(perPage), {
        preserveState: true,
        replace: true,
    });
}
async function requestFetch(): Promise<void> {
    fetchNotice.value = null;

    try {
        await fetchRequest.post(fetchProducts.url());
        fetchNotice.value = 'fetchStarted';
    } catch {
        fetchNotice.value = 'fetchBusy';
    }
}
</script>

<template>
    <Head :title="t(titleKey)" />
    <StoreAdminLayout>
        <PageHeader :title="t(titleKey)">
            <div
                v-if="!isSellerList && access.can('add_new_product')"
                class="flex flex-wrap items-center gap-2"
            >
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-line-strong px-3 py-2 text-sm font-medium text-ink hover:bg-subtle disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="fetchRequest.processing"
                    @click="requestFetch"
                >
                    <RefreshCw class="h-4 w-4" />{{
                        t(
                            fetchRequest.processing
                                ? 'store.products.fetching'
                                : 'store.products.fetch',
                        )
                    }}
                </button>
                <AddResourceLink
                    :href="create()"
                    :label="t('store.products.create')"
                />
            </div>
        </PageHeader>
        <p v-if="fetchNotice" role="status" class="mb-4 text-sm text-ink">
            {{ t(`store.products.${fetchNotice}`) }}
        </p>
        <nav
            v-if="isAllList && access.can('show_all_products')"
            id="product-presets"
            :aria-label="t('store.products.presets')"
            class="mb-4 flex flex-wrap gap-2"
        >
            <Link
                v-for="preset in presets"
                :key="preset.name"
                :href="allProducts({ query: { [preset.name]: 1 } })"
                class="rounded-md border border-line-strong px-3 py-1 text-sm"
                :class="
                    isAllList && filters.preset === preset.name
                        ? selectedToggleClass
                        : 'text-ink hover:bg-subtle'
                "
                >{{ t(`store.products.${preset.label}`) }}</Link
            >
        </nav>
        <StoreListFilters :action="listIndex()" :chips="appliedFilters">
            <template #default="{ errors }">
                <input
                    type="hidden"
                    name="per_page"
                    :value="filters.per_page"
                />
                <FormField
                    id="search"
                    :label="t('store.products.search')"
                    :value="filters.search ?? ''"
                    :error="errors.search"
                    maxlength="255"
                />
                <StoreSearchSelect
                    v-if="isSellerList"
                    id="user_id"
                    v-model="seller"
                    name="user_id"
                    class="min-w-56"
                    :label="t('store.products.seller')"
                    :endpoint="sellerOptions"
                    :placeholder="t('store.products.allSellers')"
                    :search-placeholder="t('store.products.searchSellers')"
                    :error="errors.user_id"
                />
                <div v-else>
                    <label for="category_id" :class="fieldLabelClass">{{
                        t('store.products.category')
                    }}</label>
                    <select
                        id="category_id"
                        name="category_id"
                        :class="plainFieldInputClass"
                    >
                        <option value="">
                            {{ t('store.products.allCategories') }}
                        </option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                            :selected="filters.category_id === category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <FieldError :message="errors.category_id" />
                </div>
            </template>
            <template #filters="{ errors }">
                <div v-if="isSellerList && approvalEnabled">
                    <label for="approved" :class="fieldLabelClass">{{
                        t('store.products.approvalStatus')
                    }}</label>
                    <select
                        id="approved"
                        name="approved"
                        :class="plainFieldInputClass"
                    >
                        <option value="">
                            {{ t('store.products.allApprovals') }}
                        </option>
                        <option value="1" :selected="filters.approved === true">
                            {{ t('store.products.approved') }}
                        </option>
                        <option
                            value="0"
                            :selected="filters.approved === false"
                        >
                            {{ t('store.products.unapproved') }}
                        </option>
                    </select>
                    <FieldError :message="errors.approved" />
                </div>
                <div v-if="isSellerList">
                    <label for="category_id" :class="fieldLabelClass">{{
                        t('store.products.category')
                    }}</label>
                    <select
                        id="category_id"
                        name="category_id"
                        :class="plainFieldInputClass"
                    >
                        <option value="">
                            {{ t('store.products.allCategories') }}
                        </option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                            :selected="filters.category_id === category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <FieldError :message="errors.category_id" />
                </div>
                <div>
                    <label for="type" :class="fieldLabelClass">{{
                        t('store.products.sort')
                    }}</label>
                    <select id="type" name="type" :class="plainFieldInputClass">
                        <option value="">
                            {{ t('store.products.defaultOrder') }}
                        </option>
                        <option
                            v-for="sort in sorts"
                            :key="sort.value"
                            :value="sort.value"
                            :selected="filters.type === sort.value"
                        >
                            {{ t(`store.products.${sort.label}`) }}
                        </option>
                    </select>
                    <FieldError :message="errors.type" />
                </div>
                <fieldset v-if="!isAllList" class="sm:col-span-2">
                    <legend :class="fieldLabelClass">
                        {{ t('store.products.stateFilters') }}
                    </legend>
                    <div class="flex flex-wrap gap-3">
                        <label
                            v-for="state in stateFilters"
                            :key="state.name"
                            class="flex cursor-pointer items-center gap-1.5 text-sm"
                        >
                            <input
                                type="checkbox"
                                :name="state.name"
                                value="1"
                                :checked="filters[state.name] === true"
                                class="cursor-pointer"
                            />{{ t(`store.products.${state.label}`) }}
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-muted">
                        {{ t('store.products.inHouseStateHint') }}
                    </p>
                </fieldset>
            </template>
        </StoreListFilters>
        <StoreColumnPicker :state="columns">
            <Form
                v-if="access.can('product_delete')"
                :action="listActions.bulkDestroy(listQuery)"
                :on-before="
                    () =>
                        selected.length > 0 &&
                        confirmDelete(
                            t('store.products.selection', {
                                count: selected.length,
                            }),
                        )
                "
                #default="{ errors, processing }"
                class="max-w-sm"
                @success="selected = []"
            >
                <input
                    v-for="id in selected"
                    :key="id"
                    type="hidden"
                    name="id[]"
                    :value="id"
                />
                <SubmitButton
                    variant="danger"
                    :label="t('store.products.deleteSelection')"
                    :processing-label="t('store.products.deleting')"
                    :processing="processing"
                    :disabled="processing || selected.length === 0"
                />
                <FieldError :message="errors.id ?? errors.list" />
            </Form>
        </StoreColumnPicker>
        <DataTable
            :columns="tableColumns"
            :rows="products.data"
            :empty-message="t('store.products.empty')"
        >
            <template #row="{ row: product }">
                <td v-if="access.can('product_delete')" class="px-4 py-2">
                    <input
                        v-model="selected"
                        type="checkbox"
                        :value="product.id"
                        :aria-label="
                            t('store.products.selectProduct', {
                                name: product.name,
                            })
                        "
                        class="cursor-pointer"
                    />
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-3">
                        <img
                            :src="product.thumbnail_url"
                            :alt="t('store.products.photo')"
                            class="h-8 w-8 shrink-0 object-contain"
                        />
                        <div
                            :class="
                                product.deleted_from_api
                                    ? 'text-danger-strong'
                                    : 'text-ink'
                            "
                        >
                            <span class="block font-medium">{{
                                product.name
                            }}</span>
                            <span
                                v-if="product.ar_name"
                                dir="auto"
                                class="block text-muted"
                                >{{ product.ar_name }}</span
                            >
                        </div>
                    </div>
                </td>
                <td v-if="shown.id" class="px-4 py-2 tabular-nums">
                    {{ product.id }}
                </td>
                <td v-if="shown.unit" class="px-4 py-2">{{ product.unit }}</td>
                <td v-if="shown.info" class="px-4 py-2">
                    <div class="flex items-center gap-4">
                        <IconStat
                            :icon="ShoppingCart"
                            :value="product.num_of_sale"
                            :label="
                                t('store.products.salesCount', {
                                    count: product.num_of_sale,
                                })
                            "
                        />
                        <IconStat
                            :icon="Tag"
                            :value="product.base_price"
                            :label="
                                t('store.products.basePrice', {
                                    price: product.base_price,
                                })
                            "
                        />
                        <IconStat
                            :icon="TrendingUp"
                            :value="product.profit"
                            tone="text-success-strong"
                            :label="
                                t('store.products.profit', {
                                    price: product.profit,
                                })
                            "
                        />
                    </div>
                </td>
                <td v-if="shown.stock" class="px-4 py-2">
                    <template v-if="product.variant_stocks">
                        <span
                            v-for="stock in product.variant_stocks"
                            :key="stock.variant"
                            class="block"
                            >{{ stock.variant }} - {{ stock.qty }}</span
                        >
                    </template>
                    <span v-else class="tabular-nums">{{
                        product.stock_quantity
                    }}</span>
                    <span
                        v-if="product.low_stock"
                        class="ms-2 inline-flex items-center gap-1 rounded bg-danger-soft px-1.5 text-xs font-medium text-danger-strong"
                        ><TriangleAlert class="h-3 w-3 shrink-0" />{{
                            t('store.products.low')
                        }}</span
                    >
                </td>
                <td v-if="shown.category" class="px-4 py-2">
                    <TableObjectLink
                        v-if="product.category && product.category_id !== null"
                        :allowed="access.can('edit_product_category')"
                        :href="
                            categoryEdit(product.category_id, {
                                query: { lang: defaultLanguage },
                            })
                        "
                        >{{ product.category }}</TableObjectLink
                    >
                    <template v-else>{{ product.category }}</template>
                </td>
                <td
                    v-for="item in visibleSwitches"
                    :key="item.field"
                    class="px-4 py-2"
                >
                    <StoreStatusSwitch
                        v-if="access.can('product_edit')"
                        :action="item.action(product, listQuery)"
                        :checked="product[item.field]"
                        :label="t(item.label)"
                        :input-name="item.inputName"
                        :locked="
                            item.field === 'published' &&
                            product.published_locked
                                ? publishedLock
                                : undefined
                        "
                    />
                    <span v-else>{{
                        t(
                            product[item.field]
                                ? 'store.reference.enabled'
                                : 'store.reference.disabled',
                        )
                    }}</span>
                </td>
                <td v-if="shown.deleted" class="px-4 py-2">
                    <span
                        v-if="product.deleted_from_api"
                        class="font-medium text-danger-strong"
                        >{{ t('store.products.deletedBadge') }}</span
                    >
                </td>
                <td class="px-4 py-2">
                    <div class="flex flex-nowrap items-center gap-2">
                        <TableActionLink
                            v-if="product.public_url"
                            :href="product.public_url"
                            :inertia="false"
                            target="_blank"
                            rel="noopener noreferrer"
                            ><Eye class="h-3.5 w-3.5" />{{
                                t('store.products.view')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('product_edit')"
                            :href="
                                listActions.edit(product, {
                                    query: { lang: defaultLanguage },
                                })
                            "
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.products.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('product_duplicate')"
                            :href="listActions.duplicate(product, listQuery)"
                            method="post"
                            as="button"
                            ><Copy class="h-3.5 w-3.5" />{{
                                t('store.products.duplicate')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('product_delete')"
                            :href="listActions.destroy(product, listQuery)"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(product.name)"
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.products.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <label
            v-if="access.can('product_delete') && products.data.length"
            class="mt-3 flex cursor-pointer items-center gap-2"
        >
            <input
                type="checkbox"
                :checked="allSelected"
                class="cursor-pointer"
                @change="selectAll(($event.target as HTMLInputElement).checked)"
            />{{ t('store.products.selectAll') }}
        </label>
        <Pagination
            :links="products.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
