<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import {
    create,
    destroy,
    edit,
    index as zonesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/ZoneController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreZone } from '@/types/store';

defineProps<{
    zones: Paginated<StoreZone>;
    filters: PerPageFilters;
}>();

function onPerPageChange(perPage: number): void {
    router.get(
        zonesIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.zones.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.zones.index.title')">
            <AddResourceLink
                :href="create()"
                :label="t('store.zones.index.add')"
            />
        </PageHeader>
        <DataTable
            :columns="[
                t('store.zones.index.columnName'),
                t('store.reference.actions'),
            ]"
            :rows="zones.data"
            :empty-message="t('store.zones.index.empty')"
        >
            <template #row="{ row: zone }">
                <td class="px-4 py-2 font-medium">{{ zone.name }}</td>
                <td class="px-4 py-2">
                    <div class="flex gap-2">
                        <TableActionLink :href="edit(zone)">
                            <Pencil class="h-3.5 w-3.5" />
                            {{ t('common.edit') }}
                        </TableActionLink>
                        <TableActionLink
                            :href="destroy(zone, { mergeQuery: {} })"
                            method="delete"
                            as="button"
                            variant="danger"
                            :on-before="() => confirmDelete(zone.name)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('common.delete') }}
                        </TableActionLink>
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="zones.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
