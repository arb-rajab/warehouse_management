<script setup lang="ts">
import { Head, Form, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import {
    index as resourceIndex,
    updateStatus,
    edit,
    destroy,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/TaxController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import StoreTaxFormFields from '@/components/StoreTaxFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreTax } from '@/types/store';
const props = defineProps<{
    taxes: Paginated<StoreTax>;
    filters: { per_page: number };
}>();
const access = useStoreAccess();
function changePerPage(per_page: number): void {
    router.get(
        resourceIndex.url(),
        { ...props.filters, per_page },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <StoreAdminLayout>
        <Head :title="t('store.taxes.title')" />
        <PageHeader :title="t('store.taxes.title')"> </PageHeader>
        <DataTable
            :rows="taxes.data"
            :columns="[
                t('store.reference.name'),
                t('store.reference.status'),
                t('store.reference.actions'),
            ]"
            :empty-message="t('store.reference.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">{{ row.name }}</td>
                <td class="px-4 py-2">
                    <StoreStatusButton
                        v-if="access.can('vat_&_tax_setup')"
                        :action="updateStatus(row, { mergeQuery: {} })"
                        :active="row.tax_status"
                        :label="row.name"
                    />
                </td>
                <td class="px-4 py-2">
                    <div
                        v-if="access.can('vat_&_tax_setup')"
                        class="flex gap-2"
                    >
                        <TableActionLink :href="edit(row)">
                            <Pencil class="h-3.5 w-3.5" />{{
                                t('store.reference.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            :href="destroy(row, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(row.name)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />{{
                                t('store.reference.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="taxes.meta.links"
            :per-page="filters.per_page"
            @update:per-page="changePerPage"
        />
        <section v-if="access.can('vat_&_tax_setup')" class="mt-8 max-w-sm">
            <h2 class="mb-4 text-lg font-semibold">
                {{ t('store.taxes.add') }}
            </h2>
            <Form
                :action="store()"
                class="space-y-4"
                #default="{ errors, processing }"
                reset-on-success
            >
                <StoreTaxFormFields :errors="errors" />
                <SubmitButton
                    :label="t('store.taxes.add')"
                    :processing-label="t('store.reference.saving')"
                    :processing="processing"
                />
            </Form>
        </section>
    </StoreAdminLayout>
</template>
