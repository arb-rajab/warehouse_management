<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Eye, QrCode, Trash2, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { show as showHelp } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    create,
    destroy,
    exportQrCodes,
    index as rowsIndex,
    show,
} from '@/actions/App/Http/Controllers/Admin/RowController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FilterWarehouseSelect from '@/components/FilterWarehouseSelect.vue';
import HelpLink from '@/components/HelpLink.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { pillLinkClass, primaryPillVariantClass } from '@/lib/actionLink';
import { confirmDelete } from '@/lib/confirm';
import { selectedCountLabel } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { rowLinkOptions } from '@/lib/location';
import { usePageError } from '@/lib/pageErrors';
import type {
    Paginated,
    Row,
    RowFilters,
    WarehouseFilterOptions,
} from '@/types/admin';

const props = defineProps<{
    rows: Paginated<Row>;
    filters: RowFilters;
    filterOptions: WarehouseFilterOptions;
}>();

const deleteError = usePageError('row');

const currentPerPage = computed(() => props.filters.per_page ?? 20);

function onPerPageChange(perPage: number): void {
    router.get(
        rowsIndex().url,
        { warehouse_ids: props.filters.warehouse_ids ?? [], per_page: perPage },
        { preserveState: true, replace: true },
    );
}

function onWarehouseIdsChange(ids: string[]): void {
    router.get(
        rowsIndex().url,
        { warehouse_ids: ids, per_page: currentPerPage.value },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('rows.index.title')" />

    <AdminLayout>
        <PageHeader :title="t('rows.index.title')">
            <div class="flex flex-wrap items-end gap-2">
                <FilterWarehouseSelect
                    id="rows-warehouse"
                    :model-value="(filters.warehouse_ids ?? []).map(String)"
                    :label="t('warehouses.filters.label')"
                    :all-label="t('cellLog.filters.all')"
                    :selected-count-label="selectedCountLabel"
                    :selected="filterOptions.warehouses"
                    @update:model-value="onWarehouseIdsChange"
                />
                <HelpLink :href="showHelp('rows')" />
                <AddResourceLink
                    :href="create()"
                    :label="t('rows.index.addRow')"
                />
            </div>
        </PageHeader>

        <ActionErrorBanner :message="deleteError" />

        <DataTable
            :columns="[
                t('rows.index.columnLetter'),
                t('rows.index.columnWarehouse'),
                t('rows.index.columnCells'),
                t('rows.index.columnFlats'),
                t('rows.index.columnAction'),
            ]"
            :rows="rows.data"
            :empty-message="t('rows.index.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    <span class="font-medium text-ink">{{ row.letter }}</span>
                </td>
                <td class="px-4 py-2">{{ row.warehouse.name }}</td>
                <td class="px-4 py-2">{{ row.cells_count }}</td>
                <td class="px-4 py-2">{{ row.flats_count }}</td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            :href="show(row, rowLinkOptions(row.warehouse))"
                        >
                            <Eye class="h-3.5 w-3.5" />
                            {{ t('rows.index.view') }}
                        </TableActionLink>
                        <a
                            :href="
                                exportQrCodes.url(
                                    row,
                                    rowLinkOptions(row.warehouse),
                                )
                            "
                            :class="[pillLinkClass, primaryPillVariantClass]"
                        >
                            <QrCode class="h-3.5 w-3.5" />
                            {{ t('rows.index.exportQrCodes') }}
                        </a>
                        <TableActionLink
                            v-if="!row.has_pallets"
                            :href="
                                destroy(row, {
                                    mergeQuery: {
                                        warehouse_id: row.warehouse.id,
                                    },
                                })
                            "
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="
                                () =>
                                    confirmDelete(
                                        t('rows.index.rowLabel', {
                                            letter: row.letter,
                                        }),
                                    )
                            "
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('rows.index.delete') }}
                        </TableActionLink>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 text-xs text-warning"
                            :title="t('rows.index.hasPalletsTitle')"
                        >
                            <TriangleAlert class="h-3.5 w-3.5 shrink-0" />
                            {{ t('rows.index.hasPallets') }}
                        </span>
                    </div>
                </td>
            </template>
        </DataTable>

        <Pagination
            :links="rows.meta.links"
            :per-page="currentPerPage"
            @update:per-page="onPerPageChange"
        />
    </AdminLayout>
</template>
