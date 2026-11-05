<script setup lang="ts">
import { show as orderShow } from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import { commission_history } from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
import DataTable from '@/components/DataTable.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
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
        <DataTable
            :columns="[
                '#',
                t('store.reports.orderCode'),
                t('store.reports.adminCommission'),
                t('store.reports.sellerEarning'),
                t('store.reports.createdAt'),
            ]"
            :rows="rows.data"
            :empty-message="t('store.reports.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
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
                <td class="px-4 py-2">{{ row.admin_commission }}</td>
                <td class="px-4 py-2">{{ row.seller_earning }}</td>
                <td class="px-4 py-2">{{ formatDateTime(row.created_at) }}</td>
            </template>
        </DataTable>
    </StoreReportPage>
</template>
