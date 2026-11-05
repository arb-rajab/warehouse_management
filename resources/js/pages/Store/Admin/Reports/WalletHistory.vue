<script setup lang="ts">
import { computed } from 'vue';
import {
    wallet_transaction_history,
    walletUsers,
} from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
import DataTable from '@/components/DataTable.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import { formatDate } from '@/lib/date';
import { t } from '@/lib/i18n';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type { SearchOption } from '@/types/searchSelect';
import type {
    StoreWalletReportRow,
    StoreReportFilters,
} from '@/types/storeReports';

defineProps<{
    rows: Paginated<StoreWalletReportRow>;
    filters: StoreReportFilters;
    /** The users selected in the filters; the rest are searched on demand. */
    options: SearchOption[];
}>();
const columns = useTableColumns({
    listId: 'reports.walletHistory',
    columns: [
        { key: 'number', label: 'store.reports.number' },
        { key: 'customer', label: 'store.reports.customer', required: true },
        { key: 'createdAt', label: 'store.reports.createdAt' },
        { key: 'amount', label: 'store.reports.amount' },
        { key: 'paymentMethod', label: 'store.reports.paymentMethod' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
]);
</script>
<template>
    <StoreReportPage
        :title="t('store.reports.walletHistoryTitle')"
        :url="wallet_transaction_history.url()"
        :filters="filters"
        :links="rows.meta.links"
        selection="user_ids"
        :selection-label="t('store.reports.user')"
        :selection-endpoint="walletUsers"
        :selected-options="options"
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
                    {{ row.user_name ?? t('store.reports.userNotFound') }}
                </td>
                <td v-if="shown.createdAt" class="px-4 py-2">
                    {{ formatDate(row.created_at) }}
                </td>
                <td v-if="shown.amount" class="px-4 py-2">{{ row.amount }}</td>
                <td v-if="shown.paymentMethod" class="px-4 py-2">
                    {{ row.payment_method ?? '—' }}
                </td>
            </template>
        </DataTable>
    </StoreReportPage>
</template>
