<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    customer_product_index as classifiedProductsIndex,
    destroy_by_admin as destroy,
    updatePublished,
} from '@/actions/App/Http/Controllers/Store/Admin/ClassifiedProductController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreStatusSwitch from '@/components/StoreStatusSwitch.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreClassifiedProduct } from '@/types/store';

defineProps<{
    products: Paginated<StoreClassifiedProduct>;
    filters: PerPageFilters;
}>();
const access = useStoreAccess();
function onPerPageChange(perPage: number): void {
    router.get(
        classifiedProductsIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
const columns = useTableColumns({
    listId: 'customers.classifiedProducts',
    columns: [
        { key: 'number', label: 'store.classifiedProducts.number' },
        { key: 'name', label: 'store.classifiedProducts.name', required: true },
        { key: 'image', label: 'store.classifiedProducts.image' },
        { key: 'uploadedBy', label: 'store.classifiedProducts.uploadedBy' },
        {
            key: 'customerStatus',
            label: 'store.classifiedProducts.customerStatus',
        },
        { key: 'published', label: 'store.classifiedProducts.published' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.classifiedProducts.actions'),
]);
</script>

<template>
    <Head :title="t('store.classifiedProducts.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.classifiedProducts.title')" />
        <StoreColumnPicker :state="columns" />
        <DataTable
            :rows="products.data"
            :columns="tableColumns"
            :empty-message="t('store.classifiedProducts.empty')"
        >
            <template #row="{ row }">
                <td v-if="shown.number" class="px-4 py-2">
                    {{ (products.meta.from ?? 1) + products.data.indexOf(row) }}
                </td>
                <td class="px-4 py-2">
                    <a
                        :href="row.public_url"
                        target="_blank"
                        rel="noopener"
                        class="underline"
                        >{{ row.name }}</a
                    >
                </td>
                <td v-if="shown.image" class="px-4 py-2">
                    <img
                        :src="row.thumbnail_url"
                        :alt="t('store.classifiedProducts.image')"
                        class="h-8 w-8 object-contain"
                    />
                </td>
                <td v-if="shown.uploadedBy" class="px-4 py-2">
                    {{ row.added_by }}
                </td>
                <td v-if="shown.customerStatus" class="px-4 py-2">
                    <span
                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                        :class="
                            row.status
                                ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200'
                                : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'
                        "
                        >{{
                            t(
                                row.status
                                    ? 'store.classifiedProducts.statusPublished'
                                    : 'store.classifiedProducts.statusUnpublished',
                            )
                        }}</span
                    >
                </td>
                <td v-if="shown.published" class="px-4 py-2">
                    <StoreStatusSwitch
                        v-if="access.can('publish_classified_product')"
                        :action="updatePublished(row, { mergeQuery: {} })"
                        :checked="row.published"
                        :label="
                            t('store.classifiedProducts.toggle', {
                                name: row.name,
                            })
                        "
                        :state-labels="{
                            on: t('store.classifiedProducts.statusPublished'),
                            off: t(
                                'store.classifiedProducts.statusUnpublished',
                            ),
                        }"
                    />
                    <button
                        v-else
                        type="button"
                        role="switch"
                        :aria-checked="row.published"
                        :aria-label="
                            t('store.classifiedProducts.toggle', {
                                name: row.name,
                            })
                        "
                        disabled
                        class="cursor-not-allowed text-gray-500 dark:text-neutral-400"
                    >
                        {{
                            t(
                                row.published
                                    ? 'store.classifiedProducts.statusPublished'
                                    : 'store.classifiedProducts.statusUnpublished',
                            )
                        }}
                    </button>
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <a
                            :href="row.public_url"
                            target="_blank"
                            rel="noopener"
                            class="underline"
                            >{{ t('store.classifiedProducts.show') }}</a
                        >
                        <TableActionLink
                            v-if="access.can('delete_classified_product')"
                            :href="destroy(row, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(row.name ?? '')"
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.classifiedProducts.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="products.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
