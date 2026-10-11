<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import {
    create,
    destroy,
    edit,
    index as packagesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePackageController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { RepresentativePackage } from '@/types/storeRepresentatives';

defineProps<{
    packages: Paginated<RepresentativePackage>;
    filters: PerPageFilters;
}>();
const access = useStoreAccess();
function onPerPageChange(perPage: number): void {
    router.get(
        packagesIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.representativePackages.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.representativePackages.title')">
            <AddResourceLink
                v-if="access.can('add_representative_package')"
                :href="create()"
                :label="t('store.representativePackages.create')"
            />
        </PageHeader>
        <DataTable
            :columns="[
                t('store.representativePackages.name'),
                t('store.representativePackages.percentage'),
                t('store.representativePackages.actions'),
            ]"
            :rows="packages.data"
            :empty-message="t('store.representativePackages.empty')"
        >
            <template #row="{ row: item }">
                <td class="px-4 py-2">{{ item.name }}</td>
                <td class="px-4 py-2">{{ item.percentage }}%</td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_representative_package')"
                            :href="edit(item)"
                            ><Pencil class="h-3.5 w-3.5" />{{
                                t('store.representativePackages.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('delete_representative_package')"
                            :href="destroy(item, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(item.name)"
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.representativePackages.delete')
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
