<script setup lang="ts">
import { edit as productEdit } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import DataTable from '@/components/DataTable.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type {
    StoreProductReportRow,
    StoreReportFilters,
    StoreReportOption,
} from '@/types/storeReports';

const access = useStoreAccess();

defineProps<{
    title: string;
    numbered?: boolean;
    metricLabel: string;
    url: string;
    rows: Paginated<StoreProductReportRow>;
    filters: StoreReportFilters;
    options: StoreReportOption[];
}>();
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
        <DataTable
            :columns="[
                ...(numbered ? ['#'] : []),
                t('store.reports.productName'),
                metricLabel,
            ]"
            :rows="rows.data"
            :empty-message="t('store.reports.empty')"
        >
            <template #row="{ row }">
                <td v-if="numbered" class="px-4 py-2">
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
                <td class="px-4 py-2">{{ row.value }}</td>
            </template>
        </DataTable>
    </StoreReportPage>
</template>
