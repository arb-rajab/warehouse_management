<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import { computed } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { edit as productEdit } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import {
    index as queriesIndex,
    show,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductQueryController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreProductQuery } from '@/types/store';

defineProps<{
    queries: Paginated<StoreProductQuery>;
    filters: PerPageFilters;
}>();
function onPerPageChange(perPage: number): void {
    router.get(
        queriesIndex.url(),
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
const access = useStoreAccess();
const columns = useTableColumns({
    listId: 'support.productQueries',
    columns: [
        { key: 'customer', label: 'store.support.customer', required: true },
        { key: 'product', label: 'store.support.product' },
        { key: 'question', label: 'store.support.question' },
        { key: 'reply', label: 'store.support.reply' },
        { key: 'status', label: 'store.support.status' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.support.actions'),
]);
</script>

<template>
    <Head :title="t('store.support.queries')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.support.queries')" />
        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="queries.data"
            :empty-message="t('store.support.emptyQueries')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    <TableObjectLink
                        v-if="row.user"
                        :allowed="access.can('edit_customer')"
                        :href="customerEdit(row.user)"
                        >{{ row.user.name }}</TableObjectLink
                    >
                    <template v-else>{{
                        t('store.support.missingUser')
                    }}</template>
                </td>
                <td v-if="shown.product" class="px-4 py-2">
                    <TableObjectLink
                        v-if="row.product"
                        :allowed="access.can('product_edit')"
                        :href="productEdit(row.product)"
                        >{{ row.product.name }}</TableObjectLink
                    >
                    <template v-else>{{
                        t('store.support.missingProduct')
                    }}</template>
                </td>
                <td v-if="shown.question" class="px-4 py-2">
                    {{ row.question }}
                </td>
                <td v-if="shown.reply" class="px-4 py-2">{{ row.reply }}</td>
                <td v-if="shown.status" class="px-4 py-2">
                    {{
                        row.reply === null
                            ? t('store.support.unanswered')
                            : t('store.support.answered')
                    }}
                </td>
                <td class="px-4 py-2">
                    <TableActionLink :href="show(row)"
                        ><Eye class="h-3.5 w-3.5" />{{
                            t('store.support.view')
                        }}</TableActionLink
                    >
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="queries.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
