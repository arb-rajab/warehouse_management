<script setup lang="ts">
import { user_search_report } from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
import DataTable from '@/components/DataTable.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import { t } from '@/lib/i18n';
import type { Paginated } from '@/types/admin';
import type {
    StoreSearchReportRow,
    StoreReportFilters,
} from '@/types/storeReports';

defineProps<{
    rows: Paginated<StoreSearchReportRow>;
    filters: StoreReportFilters;
}>();
</script>
<template>
    <StoreReportPage
        :title="t('store.reports.searchesTitle')"
        :url="user_search_report.url()"
        :filters="filters"
        :links="rows.meta.links"
    >
        <DataTable
            :columns="[
                '#',
                t('store.reports.searchBy'),
                t('store.reports.searchCount'),
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
                <td class="px-4 py-2">{{ row.query }}</td>
                <td class="px-4 py-2">{{ row.count }}</td>
            </template>
        </DataTable>
    </StoreReportPage>
</template>
