<script setup lang="ts">
import { computed } from 'vue';
import { show as orderShow } from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import { commission_history } from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
import DataTable from '@/components/DataTable.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type {
    StoreCommissionReportRow,
    StoreReportFilters,
    StoreReportOption,
} from '@/types/storeReports';

const access = useStoreAccess();

defineProps<{
    rows: Paginated<StoreCommissionReportRow>;
    filters: StoreReportFilters;
    options: StoreReportOption[];
}>();
const columns = useTableColumns({
    listId: 'reports.commissionHistory',
    columns: [
        { key: 'number', label: 'store.reports.number' },
        { key: 'orderCode', label: 'store.reports.orderCode', required: true },
        { key: 'adminCommission', label: 'store.reports.adminCommission' },
        { key: 'sellerEarning', label: 'store.reports.sellerEarning' },
        { key: 'createdAt', label: 'store.reports.createdAt' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
]);
</script>
<template>
    <StoreReportPage
        :title="t('store.reports.commissionHistoryTitle')"
        :url="commission_history.url()"
        :filters="filters"
        :links="rows.meta.links"
        selection="seller_ids"
        :selection-label="t('store.reports.seller')"
        :options="options"
        dates
    >
        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="rows.data"
            :empty-message="t('store.reports.empty')"
        >
            <template #row="{ row }">
                <td v-if="shown.number" class="px-4 py-2">
                    {{
                        (rows.meta.current_page - 1) * rows.meta.per_page +
                        rows.data.indexOf(row) +
                        1
                    }}
                </td>
                <td class="px-4 py-2">
                    <TableObjectLink
                        v-if="row.order_id !== null && row.order_code"
                        :allowed="access.can('view_order_details')"
                        :href="
                            orderShow['/temp/admin/orders/{order}/show'](
                                row.order_id,
                            )
                        "
                        >{{ row.order_code }}</TableObjectLink
                    >
                    <template v-else>{{
                        t('store.reports.orderDeleted')
                    }}</template>
                </td>
                <td v-if="shown.adminCommission" class="px-4 py-2">
                    {{ row.admin_commission }}
                </td>
                <td v-if="shown.sellerEarning" class="px-4 py-2">
                    {{ row.seller_earning }}
                </td>
                <td v-if="shown.createdAt" class="px-4 py-2">
                    {{ formatDateTime(row.created_at) }}
                </td>
            </template>
        </DataTable>
    </StoreReportPage>
</template>
