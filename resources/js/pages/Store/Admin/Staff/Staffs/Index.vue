<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Pencil, ShieldCheck, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { edit as roleEdit } from '@/actions/App/Http/Controllers/Store/Admin/RoleController';
import {
    create,
    destroy,
    edit,
    index as staffIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/StaffController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StaffMember } from '@/types/store';

const props = defineProps<{
    staff: Paginated<StaffMember>;
    filters: PerPageFilters;
}>();

const page = usePage();
const access = useStoreAccess();

const currentPerPage = computed(() => props.filters.per_page ?? 20);

/** The server refuses both; the buttons just don't offer them. */
function canEdit(member: StaffMember): boolean {
    return access.value.can('edit_staff') && !member.is_super_admin;
}

function canDelete(member: StaffMember): boolean {
    return (
        access.value.can('delete_staff') &&
        !member.is_super_admin &&
        member.user_id !== page.props.auth.user?.id
    );
}

function onPerPageChange(perPage: number): void {
    router.get(
        staffIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
const columns = useTableColumns({
    listId: 'staff.staffs',
    columns: [
        { key: 'name', label: 'store.staff.index.columnName', required: true },
        { key: 'email', label: 'store.staff.index.columnEmail' },
        { key: 'phone', label: 'store.staff.index.columnPhone' },
        { key: 'role', label: 'store.staff.index.columnRole' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.staff.index.columnActions'),
]);
</script>

<template>
    <Head :title="t('store.staff.index.title')" />

    <StoreAdminLayout>
        <PageHeader :title="t('store.staff.index.title')">
            <AddResourceLink
                v-if="access.can('add_staff')"
                :href="create()"
                :label="t('store.staff.index.addStaff')"
            />
        </PageHeader>

        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="staff.data"
            :empty-message="t('store.staff.index.empty')"
        >
            <template #row="{ row: member }">
                <td class="px-4 py-2 font-medium text-ink">
                    {{ member.name }}
                </td>
                <td v-if="shown.email" class="px-4 py-2">{{ member.email }}</td>
                <td v-if="shown.phone" class="px-4 py-2">{{ member.phone }}</td>
                <td v-if="shown.role" class="px-4 py-2">
                    <span
                        v-if="member.is_super_admin"
                        class="inline-flex items-center gap-1"
                    >
                        <ShieldCheck class="h-3.5 w-3.5 shrink-0" />
                        {{ t('store.staff.index.superAdmin') }}
                    </span>
                    <TableObjectLink
                        v-else-if="member.role"
                        :allowed="access.can('edit_staff_role')"
                        :href="roleEdit(member.role)"
                        >{{ member.role.name }}</TableObjectLink
                    >
                    <span v-else class="text-muted">
                        {{ t('store.staff.index.noRole') }}
                    </span>
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="canEdit(member)"
                            :href="edit(member)"
                        >
                            <Pencil class="h-3.5 w-3.5" />
                            {{ t('store.staff.index.edit') }}
                        </TableActionLink>
                        <TableActionLink
                            v-if="canDelete(member)"
                            :href="destroy(member, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(member.name)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('store.staff.index.delete') }}
                        </TableActionLink>
                    </div>
                </td>
            </template>
        </DataTable>

        <Pagination
            :links="staff.meta.links"
            :per-page="currentPerPage"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
