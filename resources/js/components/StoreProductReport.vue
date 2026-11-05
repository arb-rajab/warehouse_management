<script setup lang="ts">
import { computed } from 'vue';
import { edit as productEdit } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import DataTable from '@/components/DataTable.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type {
    StoreProductReportRow,
    StoreReportFilters,
    StoreReportOption,
} from '@/types/storeReports';

const access = useStoreAccess();

const props = defineProps<{
    title: string;
    numbered?: boolean;
    /** The translation key of the metric column's header. */
    metricKey: string;
    /** Names the report in the column picker's storage key. */
    listId: string;
    url: string;
    rows: Paginated<StoreProductReportRow>;
    filters: StoreReportFilters;
    options: StoreReportOption[];
}>();

const columns = useTableColumns({
    listId: () => props.listId,
    columns: () => [
        {
            key: 'number',
            label: 'store.reports.number',
            available: props.numbered === true,
        },
        { key: 'name', label: 'store.reports.productName', required: true },
        { key: 'metric', label: props.metricKey },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() =>
    visibleColumns.value.map((column) => t(column.label)),
);
</script>

<template>
    <StoreReportPage
        :title="title"
        :url="url"
        :filters="filters"
        :links="rows.meta.links"
        selection="category_ids"
        :selection-label="t('store.reports.category')"
        :options="options"
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
                        :allowed="access.can('product_edit')"
                        :href="productEdit(row)"
                        >{{ row.name }}</TableObjectLink
                    >
                </td>
                <td v-if="shown.metric" class="px-4 py-2">{{ row.value }}</td>
            </template>
        </DataTable>
    </StoreReportPage>
</template>
