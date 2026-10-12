<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ArrowUpRight, Download, Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    create,
    edit,
} from '@/actions/App/Http/Controllers/DigitalProductController';
import {
    destroy,
    download,
    index as digitalProductsIndex,
    updateFeatured,
    updatePublished,
    updateTodaysDeal,
} from '@/actions/App/Http/Controllers/Store/Admin/DigitalProductController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreDigitalProduct } from '@/types/store';
const props = defineProps<{
    products: Paginated<StoreDigitalProduct>;
    filters: PerPageFilters & { search: string | null };
    defaultLanguage: string;
}>();
const access = useStoreAccess();
const currentPerPage = computed(() => props.filters.per_page ?? 20);
const addedByLabels: Record<string, string> = {
    admin: 'store.digitalProducts.addedByAdmin',
    seller: 'store.digitalProducts.addedBySeller',
};
const switches = [
    { field: 'todays_deal', label: 'todaysDeal', action: updateTodaysDeal },
    { field: 'published', label: 'published', action: updatePublished },
    { field: 'featured', label: 'featured', action: updateFeatured },
] as const;
function onPerPageChange(perPage: number): void {
    router.get(
        digitalProductsIndex().url,
        { per_page: perPage, search: props.filters.search ?? '' },
        { preserveState: true, replace: true },
    );
}
</script>
<template>
    <Head :title="t('store.digitalProducts.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.digitalProducts.title')">
            <AddResourceLink
                v-if="access.can('add_digital_product')"
                :href="create()"
                :inertia="false"
                :label="t('store.digitalProducts.create')"
            />
        </PageHeader>
        <Form
            :action="digitalProductsIndex()"
            #default="{ errors, processing }"
            class="mb-4 flex items-end gap-3"
        >
            <input type="hidden" name="per_page" :value="currentPerPage" />
            <FormField
                id="search"
                :label="t('store.digitalProducts.search')"
                :value="filters.search ?? ''"
                :error="errors.search"
                maxlength="255"
            />
            <SubmitButton
                :label="t('store.digitalProducts.search')"
                :processing-label="t('store.digitalProducts.searching')"
                :processing="processing"
            />
        </Form>
        <DataTable
            :columns="[
                t('store.digitalProducts.name'),
                t('store.digitalProducts.addedBy'),
                t('store.digitalProducts.photo'),
                t('store.digitalProducts.basePrice'),
                t('store.digitalProducts.todaysDeal'),
                t('store.digitalProducts.published'),
                t('store.digitalProducts.featured'),
                t('store.digitalProducts.actions'),
            ]"
            :rows="products.data"
            :empty-message="t('store.digitalProducts.empty')"
        >
            <template #row="{ row: product }">
                <td
                    class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                >
                    <a
                        v-if="product.public_url"
                        :href="product.public_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 text-blue-600 hover:underline dark:text-blue-400"
                        >{{ product.name
                        }}<ArrowUpRight class="h-3 w-3 shrink-0"
                    /></a>
                    <span v-else>{{ product.name }}</span>
                </td>
                <td class="px-4 py-2">
                    {{
                        product.added_by && addedByLabels[product.added_by]
                            ? t(addedByLabels[product.added_by])
                            : (product.added_by ?? '—')
                    }}
                </td>
                <td class="px-4 py-2">
                    <img
                        :src="product.thumbnail_url"
                        :alt="t('store.digitalProducts.photo')"
                        class="h-12 w-12 object-contain"
                    />
                </td>
                <td class="px-4 py-2">{{ product.unit_price }}</td>
                <td
                    v-for="item in switches"
                    :key="item.field"
                    class="px-4 py-2"
                >
                    <StoreStatusButton
                        v-if="access.can('product_edit')"
                        :action="item.action(product, { mergeQuery: {} })"
                        :active="product[item.field]"
                        :label="t(`store.digitalProducts.${item.label}`)"
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
                    <div class="flex flex-wrap items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_digital_product')"
                            :href="
                                edit(product, {
                                    query: { lang: defaultLanguage },
                                })
                            "
                            :inertia="false"
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.digitalProducts.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('download_digital_product')"
                            :href="download(product.download_token)"
                            :inertia="false"
                            ><Download class="h-3.5 w-3.5" />{{
                                t('store.digitalProducts.download')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('delete_digital_product')"
                            :href="destroy(product, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(product.name)"
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.digitalProducts.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="products.meta.links"
            :per-page="currentPerPage"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
