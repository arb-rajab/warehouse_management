<script setup lang="ts">
import { wallet_transaction_history } from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
import DataTable from '@/components/DataTable.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import { formatDate } from '@/lib/date';
import { t } from '@/lib/i18n';
import type { Paginated } from '@/types/admin';
import type {
    StoreWalletReportRow,
    StoreReportFilters,
    StoreReportOption,
} from '@/types/storeReports';

defineProps<{
    rows: Paginated<StoreWalletReportRow>;
    filters: StoreReportFilters;
    options: StoreReportOption[];
}>();
</script>
<template>
    <StoreReportPage
        :title="t('store.reports.walletHistoryTitle')"
        :url="wallet_transaction_history.url()"
        :filters="filters"
        :links="rows.meta.links"
        selection="user_ids"
        :selection-label="t('store.reports.user')"
        :options="options"
        dates
    >
        <DataTable
            :columns="[
                '#',
                t('store.reports.customer'),
                t('store.reports.createdAt'),
                t('store.reports.amount'),
                t('store.reports.paymentMethod'),
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
                    {{ row.user_name ?? t('store.reports.userNotFound') }}
                </td>
                <td class="px-4 py-2">{{ formatDate(row.created_at) }}</td>
                <td class="px-4 py-2">{{ row.amount }}</td>
                <td class="px-4 py-2">{{ row.payment_method ?? '—' }}</td>
            </template>
        </DataTable>
    </StoreReportPage>
</template>
