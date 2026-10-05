<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    create,
    destroy,
    edit,
    index as rolesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/RoleController';
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
import type { StoreRole } from '@/types/store';

const props = defineProps<{
    roles: Paginated<StoreRole>;
    filters: PerPageFilters;
}>();

const access = useStoreAccess();

const currentPerPage = computed(() => props.filters.per_page ?? 20);

function onPerPageChange(perPage: number): void {
    router.get(
        rolesIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.roles.index.title')" />

    <StoreAdminLayout>
        <PageHeader :title="t('store.roles.index.title')">
            <AddResourceLink
                v-if="access.can('add_staff_role')"
                :href="create()"
                :label="t('store.roles.index.addRole')"
            />
        </PageHeader>

        <DataTable
            :columns="[
                t('store.roles.index.columnName'),
                t('store.roles.index.columnActions'),
            ]"
            :rows="roles.data"
            :empty-message="t('store.roles.index.empty')"
        >
            <template #row="{ row: role }">
                <td
                    class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                >
                    {{ role.name }}
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="access.can('edit_staff_role')"
                            :href="edit(role)"
                        >
                            <Pencil class="h-3.5 w-3.5" />
                            {{ t('store.roles.index.edit') }}
                        </TableActionLink>
                        <TableActionLink
                            v-if="access.can('delete_staff_role')"
                            :href="destroy(role, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(role.name)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('store.roles.index.delete') }}
                        </TableActionLink>
                    </div>
                </td>
            </template>
        </DataTable>

        <Pagination
            :links="roles.meta.links"
            :per-page="currentPerPage"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
