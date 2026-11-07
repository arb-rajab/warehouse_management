<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { pageViews } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type { StoreCustomerPageView } from '@/types/storeCustomers';
const props = defineProps<{
    customer: { id: number; name: string };
    views: Paginated<StoreCustomerPageView>;
    filters: { kind: string | null; per_page: number };
}>();
function apply(perPage: number): void {
    router.get(
        pageViews(props.customer).url,
        { kind: props.filters.kind, per_page: perPage },
        { preserveState: true, replace: true },
    );
}
const columns = useTableColumns({
    listId: 'customers.views',
    columns: [
        { key: 'name', label: 'store.customers.fields.name', required: true },
        { key: 'kind', label: 'store.customers.kind' },
        { key: 'viewCount', label: 'store.customers.viewCount' },
        { key: 'lastViewed', label: 'store.customers.lastViewed' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
]);
</script>
<template>
    <Head :title="t('store.customers.views')" /><StoreAdminLayout
        ><PageHeader
            :title="t('store.customers.viewsFor', { name: customer.name })" />
        <nav class="mb-4 flex gap-3" :aria-label="t('common.filters.title')">
            <Link
                :href="
                    pageViews(customer, {
                        query: { per_page: filters.per_page },
                    })
                "
                >{{ t('store.customers.all') }}</Link
            >
            <Link
                v-for="kind in ['products', 'categories']"
                :key="kind"
                :href="
                    pageViews(customer, {
                        query: { kind, per_page: filters.per_page },
                    })
                "
                >{{ t(`store.customers.${kind}`) }}</Link
            >
        </nav>
        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="views.data"
            :empty-message="t('store.customers.empty')"
            ><template #row="{ row }"
                ><td class="px-4 py-2">
                    <a v-if="row.public_url" :href="row.public_url">{{
                        row.name
                    }}</a
                    ><span v-else>{{ row.name }}</span>
                </td>
                <td v-if="shown.kind" class="px-4 py-2">
                    {{ t(`store.customers.${row.kind}`) }}
                </td>
                <td v-if="shown.viewCount" class="px-4 py-2">
                    {{ row.view_count }}
                </td>
                <td v-if="shown.lastViewed" class="px-4 py-2">
                    {{ row.last_viewed_at }}
                </td></template
            ></DataTable
        ><Pagination
            :links="views.meta.links"
            :per-page="filters.per_page"
            @update:per-page="apply"
    /></StoreAdminLayout>
</template>
