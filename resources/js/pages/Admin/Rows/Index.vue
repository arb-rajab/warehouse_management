<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Eye, Trash2 } from '@lucide/vue';
import {
    create,
    destroy,
    show,
} from '@/actions/App/Http/Controllers/Admin/RowController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import type { Paginated, Row } from '@/types/admin';

defineProps<{
    rows: Paginated<Row>;
}>();
</script>

<template>
    <Head :title="t('rows.index.title')" />

    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('rows.index.title') }}</h1>
            <AddResourceLink :href="create()" :label="t('rows.index.addRow')" />
        </div>

        <DataTable
            :columns="[
                t('rows.index.columnLetter'),
                t('rows.index.columnCells'),
                t('rows.index.columnFlats'),
                t('rows.index.columnAction'),
            ]"
            :rows="rows.data"
            :empty-message="t('rows.index.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    <span
                        class="font-medium text-gray-900 dark:text-neutral-100"
                        >{{ row.letter }}</span
                    >
                </td>
                <td class="px-4 py-2">{{ row.cells_count }}</td>
                <td class="px-4 py-2">{{ row.flats_count }}</td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink :href="show(row)">
                            <Eye class="h-3.5 w-3.5" />
                            {{ t('rows.index.view') }}
                        </TableActionLink>
                        <TableActionLink
                            v-if="!row.has_pallets"
                            :href="destroy(row)"
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
                            class="text-xs text-amber-600 dark:text-amber-400"
                            :title="t('rows.index.hasPalletsTitle')"
                        >
                            {{ t('rows.index.hasPallets') }}
                        </span>
                    </div>
                </td>
            </template>
        </DataTable>

        <Pagination :links="rows.meta.links" />
    </AdminLayout>
</template>
