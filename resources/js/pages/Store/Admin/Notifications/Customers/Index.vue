<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import {
    create,
    destroy,
    index as notificationsIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerNotificationController';
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
import type { CustomerNotification } from '@/types/storeNotifications';

defineProps<{
    notifications: Paginated<CustomerNotification>;
    filters: PerPageFilters;
}>();
const access = useStoreAccess();

function onPerPageChange(perPage: number): void {
    router.get(
        notificationsIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
const columns = useTableColumns({
    listId: 'notifications.customers',
    columns: [
        {
            key: 'title',
            label: 'store.notifications.fields.title',
            required: true,
        },
        { key: 'status', label: 'store.notifications.index.status' },
        { key: 'customer', label: 'store.notifications.fields.customer' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.notifications.index.actions'),
]);
</script>

<template>
    <Head :title="t('store.notifications.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.notifications.index.title')">
            <AddResourceLink
                v-if="access.can('add_notification')"
                :href="create()"
                :label="t('store.notifications.index.add')"
            />
        </PageHeader>
        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="notifications.data"
            :empty-message="t('store.notifications.index.empty')"
        >
            <template #row="{ row: notification }">
                <td
                    class="max-w-xs truncate px-4 py-2"
                    :title="notification.title"
                >
                    {{ notification.title }}
                </td>
                <td v-if="shown.status" class="px-4 py-2">
                    {{
                        notification.status === 'sent'
                            ? t('store.notifications.index.sent')
                            : notification.status
                    }}
                </td>
                <td v-if="shown.customer" class="px-4 py-2">
                    <TableObjectLink
                        v-if="notification.customer"
                        :allowed="access.can('edit_customer')"
                        :href="customerEdit(notification.customer)"
                        >{{ notification.customer.name }}</TableObjectLink
                    >
                </td>
                <td class="px-4 py-2">
                    <TableActionLink
                        v-if="access.can('delete_notification')"
                        :href="destroy(notification, { mergeQuery: {} })"
                        variant="danger"
                        method="delete"
                        as="button"
                        :on-before="() => confirmDelete(notification.title)"
                    >
                        {{ t('store.notifications.index.delete') }}
                    </TableActionLink>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="notifications.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
