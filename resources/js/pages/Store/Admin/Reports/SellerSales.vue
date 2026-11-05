<script setup lang="ts">
import { computed } from 'vue';
import { seller_sale_report } from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
import { edit as sellerEdit } from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import DataTable from '@/components/DataTable.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
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
        <DataTable
            :columns="[
                t('store.reports.sellerName'),
                t('store.reports.shopName'),
                t('store.reports.sales'),
                t('store.reports.orderAmount'),
            ]"
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
                <td class="px-4 py-2">{{ row.shop_name ?? '—' }}</td>
                <td class="px-4 py-2">{{ row.num_of_sale }}</td>
                <td class="px-4 py-2">{{ row.order_amount }}</td>
            </template>
        </DataTable>
    </StoreReportPage>
</template>
