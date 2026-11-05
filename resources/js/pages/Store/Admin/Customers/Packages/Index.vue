<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    create,
    destroy,
    edit,
    index as packagesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerPackageController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreCustomerPackage } from '@/types/store';

defineProps<{
    packages: Paginated<StoreCustomerPackage>;
    filters: PerPageFilters;
    defaultLanguage: string;
}>();
const access = useStoreAccess();
function onPerPageChange(perPage: number): void {
    router.get(
        packagesIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
const columns = useTableColumns({
    listId: 'customers.packages',
    columns: [
        { key: 'name', label: 'store.customerPackages.name', required: true },
        { key: 'logo', label: 'store.customerPackages.logo' },
        { key: 'amount', label: 'store.customerPackages.amount' },
        { key: 'uploads', label: 'store.customerPackages.uploads' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.customerPackages.actions'),
]);
</script>

<template>
    <Head :title="t('store.customerPackages.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.customerPackages.title')">
            <AddResourceLink
                v-if="access.can('edit_classified_package')"
                :href="create()"
                :label="t('store.customerPackages.create')"
            />
        </PageHeader>
        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="packages.data"
            :empty-message="t('store.customerPackages.empty')"
        >
            <template #row="{ row: item }">
                <td class="px-4 py-2">{{ item.localized_name }}</td>
                <td v-if="shown.logo" class="px-4 py-2">
                    <img
                        :src="item.logo_url"
                        :alt="t('store.customerPackages.logo')"
                        class="h-8 w-8 object-contain"
                    />
                </td>
                <td v-if="shown.amount" class="px-4 py-2">
                    {{ item.formatted_amount }}
                </td>
                <td v-if="shown.uploads" class="px-4 py-2">
                    {{ item.product_upload }}
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_classified_package')"
                            :href="
                                edit(item, { query: { lang: defaultLanguage } })
                            "
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.customerPackages.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('delete_classified_package')"
                            :href="destroy(item, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="
                                () => confirmDelete(item.localized_name ?? '')
                            "
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.reference.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="packages.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
