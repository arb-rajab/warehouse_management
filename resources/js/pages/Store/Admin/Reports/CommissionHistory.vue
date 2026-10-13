<script setup lang="ts">
import { commission_history } from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
import DataTable from '@/components/DataTable.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import type { Paginated } from '@/types/admin';
import type {
    StoreCommissionReportRow,
    StoreReportFilters,
    StoreReportOption,
} from '@/types/storeReports';

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
                    {{ row.order_code ?? t('store.reports.orderDeleted') }}
                </td>
                <td class="px-4 py-2">{{ row.admin_commission }}</td>
                <td class="px-4 py-2">{{ row.seller_earning }}</td>
                <td class="px-4 py-2">{{ formatDateTime(row.created_at) }}</td>
            </template>
        </DataTable>
    </StoreReportPage>
</template>
