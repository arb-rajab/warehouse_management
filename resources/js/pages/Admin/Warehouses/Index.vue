<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { show as showHelp } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    create,
    destroy,
    edit,
    index as warehousesIndex,
} from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import HelpLink from '@/components/HelpLink.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { usePageError } from '@/lib/pageErrors';
import type { Paginated, PerPageFilters, Warehouse } from '@/types/admin';

const props = defineProps<{
    warehouses: Paginated<Warehouse>;
    filters: PerPageFilters;
}>();

const deleteError = usePageError('warehouse');

const currentPerPage = computed(() => props.filters.per_page ?? 20);

function onPerPageChange(perPage: number): void {
    router.get(
        warehousesIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('warehouses.index.title')" />

    <AdminLayout>
        <PageHeader :title="t('warehouses.index.title')">
            <div class="flex items-center gap-2">
                <HelpLink :href="showHelp('warehouses')" />
                <AddResourceLink
                    :href="create()"
                    :label="t('warehouses.index.addWarehouse')"
                />
            </div>
        </PageHeader>

        <ActionErrorBanner :message="deleteError" />

        <DataTable
            :columns="[
                t('warehouses.index.columnName'),
                t('warehouses.index.columnCode'),
                t('warehouses.index.columnAddress'),
                t('warehouses.index.columnRows'),
                t('warehouses.index.columnAction'),
            ]"
            :rows="warehouses.data"
            :empty-message="t('warehouses.index.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    <span
                        class="font-medium text-gray-900 dark:text-neutral-100"
                        >{{ row.name }}</span
                    >
                </td>
                <td class="px-4 py-2">{{ row.code ?? '—' }}</td>
                <td class="px-4 py-2">{{ row.address ?? '—' }}</td>
                <td class="px-4 py-2">{{ row.rows_count }}</td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink :href="edit(row)">
                            <Pencil class="h-3.5 w-3.5" />
                            {{ t('warehouses.index.edit') }}
                        </TableActionLink>
                        <TableActionLink
                            v-if="!row.rows_count"
                            :href="destroy(row, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="
                                () =>
                                    confirmDelete(
                                        t('warehouses.index.warehouseLabel', {
                                            name: row.name,
                                        }),
                                    )
                            "
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('warehouses.index.delete') }}
                        </TableActionLink>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400"
                            :title="t('warehouses.index.hasRowsTitle')"
                        >
                            <TriangleAlert class="h-3.5 w-3.5 shrink-0" />
                            {{ t('warehouses.index.hasRows') }}
                        </span>
                    </div>
                </td>
            </template>
        </DataTable>

        <Pagination
            :links="warehouses.meta.links"
            :per-page="currentPerPage"
            @update:per-page="onPerPageChange"
        />
    </AdminLayout>
</template>
