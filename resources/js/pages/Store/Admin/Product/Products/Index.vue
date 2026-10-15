<script setup lang="ts">
import { Form, Head, Link, router, useHttp } from '@inertiajs/vue3';
import { Copy, Eye, Pencil, RefreshCw, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    admin_product_edit,
    create,
    fetch as fetchProducts,
} from '@/actions/App/Http/Controllers/ProductController';
import {
    all as allProducts,
    bulkDestroy,
    destroy,
    duplicate,
    inHouse as inHouseProducts,
    updateFeatured,
    updateForSale,
    updatePublished,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import {
    fieldLabelClass,
    plainFieldInputClass,
    selectedToggleClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type {
    StoreProductListFilters,
    StoreProductListPreset,
    StoreProductListRow,
    StoreProductListType,
} from '@/types/store';

const props = defineProps<{
    listType: StoreProductListType;
    products: Paginated<StoreProductListRow>;
    filters: StoreProductListFilters;
    categories: { id: number; name: string }[];
    defaultLanguage: string;
}>();
const access = useStoreAccess();
const isAllList = computed(() => props.listType === 'all');
const listIndex = computed(() =>
    isAllList.value ? allProducts : inHouseProducts,
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
const stateFilters = computed(() => [
    { name: 'active', label: 'active' },
    { name: 'for_sale', label: 'forSale' },
    { name: 'featured', label: 'featured' },
    ...(isAllList.value ? [{ name: 'deleted', label: 'deleted' }] : []),
]);
const presets: { name: StoreProductListPreset; label: string }[] = [
    { name: 'allProducts', label: 'presetAllProducts' },
    { name: 'allActive', label: 'presetAllActive' },
    { name: 'allForSale', label: 'presetAllForSale' },
    { name: 'allFeatured', label: 'presetAllFeatured' },
    { name: 'allDeleted', label: 'presetAllDeleted' },
];
const switches = [
    { field: 'for_sale', label: 'forSale', action: updateForSale },
    { field: 'published', label: 'active', action: updatePublished },
    { field: 'featured', label: 'featured', action: updateFeatured },
] as const;
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
    const flags = stateFilters.value.map((filter) => filter.name);
    const query: Record<string, string | number> = { per_page: perPage };

    for (const key of ['search', 'category_id', 'type', 'user_id'] as const) {
        const value = props.filters[key];

        if (value !== null && value !== undefined && value !== '') {
            query[key] = value;
        }
    }

    for (const flag of flags) {
        if (props.filters[flag as keyof StoreProductListFilters] === true) {
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
    <Head
        :title="
            t(
                isAllList
                    ? 'store.products.allTitle'
                    : 'store.products.inHouseTitle',
            )
        "
    />
    <StoreAdminLayout>
        <PageHeader
            :title="
                t(
                    isAllList
                        ? 'store.products.allTitle'
                        : 'store.products.inHouseTitle',
                )
            "
        >
            <div
                v-if="access.can('add_new_product')"
                class="flex flex-wrap items-center gap-2"
            >
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:text-neutral-100 dark:hover:bg-neutral-800"
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
                    :inertia="false"
                    :label="t('store.products.create')"
                />
            </div>
        </PageHeader>
        <p
            v-if="fetchNotice"
            role="status"
            class="mb-4 text-sm text-gray-700 dark:text-neutral-300"
        >
            {{ t(`store.products.${fetchNotice}`) }}
        </p>
        <nav
            v-if="access.can('show_all_products')"
            id="product-presets"
            :aria-label="t('store.products.presets')"
            class="mb-4 flex flex-wrap gap-2"
        >
            <Link
                v-for="preset in presets"
                :key="preset.name"
                :href="allProducts({ query: { [preset.name]: 1 } })"
                class="rounded-md border border-gray-300 px-3 py-1 text-sm dark:border-neutral-700"
                :class="
                    isAllList && filters.preset === preset.name
                        ? selectedToggleClass
                        : 'text-gray-700 hover:bg-gray-100 dark:text-neutral-300 dark:hover:bg-neutral-800'
                "
                >{{ t(`store.products.${preset.label}`) }}</Link
            >
        </nav>
        <Form
            :action="listIndex()"
            #default="{ errors, processing }"
            class="mb-4 flex flex-wrap items-end gap-3"
        >
            <input type="hidden" name="per_page" :value="filters.per_page" />
            <FormField
                id="search"
                :label="t('store.products.search')"
                :value="filters.search ?? ''"
                :error="errors.search"
                maxlength="255"
            />
            <div>
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
            <fieldset class="max-w-md">
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
                            :checked="
                                filters[
                                    state.name as keyof StoreProductListFilters
                                ] === true
                            "
                            class="cursor-pointer"
                        />{{ t(`store.products.${state.label}`) }}
                    </label>
                </div>
                <p class="mt-1 text-xs text-gray-600 dark:text-neutral-400">
                    {{
                        t(
                            isAllList
                                ? 'store.products.allStateHint'
                                : 'store.products.inHouseStateHint',
                        )
                    }}
                </p>
            </fieldset>
            <SubmitButton
                :label="t('store.products.apply')"
                :processing-label="t('store.products.applying')"
                :processing="processing"
            />
        </Form>
        <Form
            v-if="access.can('product_delete')"
            :action="bulkDestroy(listQuery)"
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
            class="mb-4 max-w-sm"
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
                :label="t('store.products.deleteSelection')"
                :processing-label="t('store.products.deleting')"
                :processing="processing"
                :disabled="processing || selected.length === 0"
            />
            <FieldError :message="errors.id ?? errors.list" />
        </Form>
        <DataTable
            :columns="[
                ...(access.can('product_delete')
                    ? [t('store.products.select')]
                    : []),
                t('store.products.name'),
                t('store.products.materialId'),
                t('store.products.unit'),
                t('store.products.info'),
                t('store.products.totalStock'),
                t('store.products.category'),
                t('store.products.forSale'),
                t('store.products.active'),
                t('store.products.featured'),
                t('store.products.deletedFromOtajer'),
                t('store.products.actions'),
            ]"
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
                            class="h-12 w-12 shrink-0 object-contain"
                        />
                        <div
                            :class="
                                product.deleted_from_api
                                    ? 'text-red-700 dark:text-red-400'
                                    : 'text-gray-900 dark:text-neutral-100'
                            "
                        >
                            <span class="block font-medium">{{
                                product.name
                            }}</span>
                            <span
                                v-if="product.ar_name"
                                dir="auto"
                                class="block"
                                >{{ product.ar_name }}</span
                            >
                        </div>
                    </div>
                </td>
                <td class="px-4 py-2">{{ product.mat_id }}</td>
                <td class="px-4 py-2">{{ product.unit }}</td>
                <td class="px-4 py-2 whitespace-nowrap">
                    <span class="block">{{
                        t('store.products.salesCount', {
                            count: product.num_of_sale,
                        })
                    }}</span>
                    <span class="block">{{
                        t('store.products.basePrice', {
                            price: product.base_price,
                        })
                    }}</span>
                    <span class="block">{{
                        t('store.products.profit', { price: product.profit })
                    }}</span>
                </td>
                <td class="px-4 py-2">
                    <template v-if="product.variant_stocks">
                        <span
                            v-for="stock in product.variant_stocks"
                            :key="stock.variant"
                            class="block"
                            >{{ stock.variant }} - {{ stock.qty }}</span
                        >
                    </template>
                    <span v-else class="block">{{
                        product.stock_quantity
                    }}</span>
                    <span
                        v-if="product.low_stock"
                        class="mt-1 inline-block rounded bg-red-100 px-1.5 text-xs font-medium text-red-800 dark:bg-red-950 dark:text-red-200"
                        >{{ t('store.products.low') }}</span
                    >
                </td>
                <td class="px-4 py-2">{{ product.category }}</td>
                <td
                    v-for="item in switches"
                    :key="item.field"
                    class="px-4 py-2"
                >
                    <StoreStatusButton
                        v-if="access.can('product_edit')"
                        :action="item.action(product, listQuery)"
                        :active="product[item.field]"
                        :label="t(`store.products.${item.label}`)"
                    />
                    <span v-else>{{
                        t(
                            product[item.field]
                                ? 'store.reference.enabled'
                                : 'store.reference.disabled',
                        )
                    }}</span>
                </td>
                <td class="px-4 py-2">
                    <span
                        v-if="product.deleted_from_api"
                        class="font-medium text-red-700 dark:text-red-400"
                        >{{ t('store.products.deletedBadge') }}</span
                    >
                </td>
                <td class="px-4 py-2">
                    <div class="flex flex-wrap items-center gap-2">
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
                                admin_product_edit(product, {
                                    query: { lang: defaultLanguage },
                                })
                            "
                            :inertia="false"
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.products.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('product_duplicate')"
                            :href="duplicate(product, listQuery)"
                            method="post"
                            as="button"
                            ><Copy class="h-3.5 w-3.5" />{{
                                t('store.products.duplicate')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('product_delete')"
                            :href="destroy(product, listQuery)"
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
