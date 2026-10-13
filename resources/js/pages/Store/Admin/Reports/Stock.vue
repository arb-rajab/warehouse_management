<script setup lang="ts">
import { stock_report } from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
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
        :title="t('store.reports.stockTitle')"
        :metric-label="t('store.reports.stock')"
        :url="stock_report.url()"
        :rows="rows"
        :filters="filters"
        :options="options"
    />
</template>
