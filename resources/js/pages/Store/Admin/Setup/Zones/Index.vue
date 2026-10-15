<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2, CircleCheck, CircleSlash } from '@lucide/vue';
import {
    index as resourceIndex,
    edit,
    destroy,
    create,
} from '@/actions/App/Http/Controllers/Store/Admin/ZoneController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreZone } from '@/types/store';
const props = defineProps<{
    zones: Paginated<StoreZone>;
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
        <Head :title="t('store.zones.title')" />
        <PageHeader :title="t('store.zones.title')">
            <AddResourceLink
                v-if="access.can('manage_zones')"
                :href="create()"
                :label="t('store.zones.add')"
            />
        </PageHeader>
        <DataTable
            :rows="zones.data"
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
                    <span class="inline-flex items-center gap-1">
                        <CircleCheck v-if="row.status" class="h-4 w-4" />
                        <CircleSlash v-else class="h-4 w-4" />{{
                            t(
                                row.status
                                    ? 'store.reference.enabled'
                                    : 'store.reference.disabled',
                            )
                        }}</span
                    >
                </td>
                <td class="px-4 py-2">
                    <div v-if="access.can('manage_zones')" class="flex gap-2">
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
            :links="zones.meta.links"
            :per-page="filters.per_page"
            @update:per-page="changePerPage"
        />
    </StoreAdminLayout>
</template>
