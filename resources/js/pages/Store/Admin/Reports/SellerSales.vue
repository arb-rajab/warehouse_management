<script setup lang="ts">
import { computed } from 'vue';
import { seller_sale_report } from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
import { edit as sellerEdit } from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import DataTable from '@/components/DataTable.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type {
    StoreSellerReportRow,
    StoreReportFilters,
    StoreReportOption,
} from '@/types/storeReports';

const access = useStoreAccess();

defineProps<{
    rows: Paginated<StoreSellerReportRow>;
    filters: StoreReportFilters;
    options: StoreReportOption[];
}>();
const verificationOptions = computed(() => [
    { value: '1', label: t('store.reports.approved') },
    { value: '0', label: t('store.reports.nonApproved') },
]);
const columns = useTableColumns({
    listId: 'reports.sellerSales',
    columns: [
        {
            key: 'sellerName',
            label: 'store.reports.sellerName',
            required: true,
        },
        { key: 'shopName', label: 'store.reports.shopName' },
        { key: 'sales', label: 'store.reports.sales' },
        { key: 'orderAmount', label: 'store.reports.orderAmount' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
]);
</script>
<template>
    <StoreReportPage
        :title="t('store.reports.sellerSalesTitle')"
        :url="seller_sale_report.url()"
        :filters="filters"
        :links="rows.meta.links"
        selection="verification_statuses"
        :selection-label="t('store.reports.verification')"
        :options="verificationOptions"
    >
        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="rows.data"
            :empty-message="t('store.reports.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    <TableObjectLink
                        v-if="row.seller_name"
                        :allowed="access.can('edit_seller')"
                        :href="sellerEdit(row)"
                        >{{ row.seller_name }}</TableObjectLink
                    >
                    <template v-else>{{
                        t('store.reports.userNotFound')
                    }}</template>
                </td>
                <td v-if="shown.shopName" class="px-4 py-2">
                    {{ row.shop_name ?? '—' }}
                </td>
                <td v-if="shown.sales" class="px-4 py-2">
                    {{ row.num_of_sale }}
                </td>
                <td v-if="shown.orderAmount" class="px-4 py-2">
                    {{ row.order_amount }}
                </td>
            </template>
        </DataTable>
    </StoreReportPage>
</template>
