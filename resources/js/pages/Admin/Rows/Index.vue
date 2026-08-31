<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Eye, QrCode, Trash2, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import {
    create,
    destroy,
    exportQrCodes,
    show,
} from '@/actions/App/Http/Controllers/Admin/RowController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { pillLinkClass, primaryPillVariantClass } from '@/lib/actionLink';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import type { Paginated, Row } from '@/types/admin';

defineProps<{
    rows: Paginated<Row>;
}>();

const deleteError = computed(
    () => (usePage().props.errors as Partial<Record<'row', string>>)?.row,
);
</script>

<template>
    <Head :title="t('rows.index.title')" />

    <AdminLayout>
        <PageHeader :title="t('rows.index.title')">
            <AddResourceLink :href="create()" :label="t('rows.index.addRow')" />
        </PageHeader>

        <ActionErrorBanner :message="deleteError" />

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
                        <a
                            :href="exportQrCodes.url(row)"
                            :class="[pillLinkClass, primaryPillVariantClass]"
                        >
                            <QrCode class="h-3.5 w-3.5" />
                            {{ t('rows.index.exportQrCodes') }}
                        </a>
                        <TableActionLink
                            v-if="!row.has_pallets"
                            :href="destroy(row, { mergeQuery: {} })"
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
                            class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400"
                            :title="t('rows.index.hasPalletsTitle')"
                        >
                            <TriangleAlert class="h-3.5 w-3.5 shrink-0" />
                            {{ t('rows.index.hasPallets') }}
                        </span>
                    </div>
                </td>
            </template>
        </DataTable>

        <Pagination :links="rows.meta.links" />
    </AdminLayout>
</template>
