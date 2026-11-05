<script setup lang="ts">
import { computed } from 'vue';
import { user_search_report } from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
import DataTable from '@/components/DataTable.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import { t } from '@/lib/i18n';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type {
    StoreSearchReportRow,
    StoreReportFilters,
} from '@/types/storeReports';

defineProps<{
    rows: Paginated<StoreSearchReportRow>;
    filters: StoreReportFilters;
}>();
const columns = useTableColumns({
    listId: 'reports.searches',
    columns: [
        { key: 'number', label: 'store.reports.number' },
        { key: 'searchBy', label: 'store.reports.searchBy', required: true },
        { key: 'searchCount', label: 'store.reports.searchCount' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
]);
</script>
<template>
    <StoreReportPage
        :title="t('store.reports.searchesTitle')"
        :url="user_search_report.url()"
        :filters="filters"
        :links="rows.meta.links"
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
                <td class="px-4 py-2">{{ row.query }}</td>
                <td v-if="shown.searchCount" class="px-4 py-2">
                    {{ row.count }}
                </td>
            </template>
        </DataTable>
    </StoreReportPage>
</template>
