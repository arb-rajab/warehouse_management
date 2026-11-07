<script setup lang="ts">
import { in_house_sale_report } from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
import StoreProductReport from '@/components/StoreProductReport.vue';
import { t } from '@/lib/i18n';
import type { Paginated } from '@/types/admin';
import type {
    StoreProductReportRow,
    StoreReportFilters,
    StoreReportOption,
} from '@/types/storeReports';

defineProps<{
    rows: Paginated<StoreProductReportRow>;
    filters: StoreReportFilters;
    options: StoreReportOption[];
}>();
</script>

<template>
    <StoreProductReport
        numbered
        :title="t('store.reports.inHouseSalesTitle')"
        metric-key="store.reports.sales"
        list-id="reports.inHouseSales"
        :url="in_house_sale_report.url()"
        :rows="rows"
        :filters="filters"
        :options="options"
    />
</template>
