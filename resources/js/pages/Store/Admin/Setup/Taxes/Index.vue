<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import {
    destroy,
    edit,
    index as taxesIndex,
    store,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/TaxController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreReferenceStatusForm from '@/components/StoreReferenceStatusForm.vue';
import StoreTaxFormFields from '@/components/StoreTaxFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreTax } from '@/types/store';

defineProps<{
    taxes: Paginated<StoreTax>;
    filters: PerPageFilters;
}>();

function onPerPageChange(perPage: number): void {
    router.get(
        taxesIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.taxes.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.taxes.index.title')" />
        <Form
            :action="store()"
            class="mb-6 max-w-sm space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-800"
            #default="{ errors, processing }"
        >
            <h2 class="font-semibold">{{ t('store.taxes.index.add') }}</h2>
            <StoreTaxFormFields :errors="errors" />
            <SubmitButton
                :label="t('common.save')"
                :processing-label="t('common.saving')"
                :processing="processing"
            />
        </Form>
        <DataTable
            :columns="[
                t('store.taxes.index.columnName'),
                t('store.taxes.index.columnStatus'),
                t('store.reference.actions'),
            ]"
            :rows="taxes.data"
            :empty-message="t('store.taxes.index.empty')"
        >
            <template #row="{ row: tax }">
                <td class="px-4 py-2 font-medium">{{ tax.name }}</td>
                <td class="px-4 py-2">
                    <StoreReferenceStatusForm
                        :action="updateStatus(tax, { mergeQuery: {} })"
                        :active="tax.active"
                    />
                </td>
                <td class="px-4 py-2">
                    <div class="flex gap-2">
                        <TableActionLink :href="edit(tax)">
                            <Pencil class="h-3.5 w-3.5" />
                            {{ t('common.edit') }}
                        </TableActionLink>
                        <TableActionLink
                            :href="destroy(tax, { mergeQuery: {} })"
                            method="delete"
                            as="button"
                            variant="danger"
                            :on-before="() => confirmDelete(tax.name)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('common.delete') }}
                        </TableActionLink>
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="taxes.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
