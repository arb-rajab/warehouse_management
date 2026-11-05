<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import { computed } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { index as ticketsIndex } from '@/actions/App/Http/Controllers/Store/Admin/SupportTicketController';
import { show } from '@/actions/App/Http/Controllers/Store/Admin/SupportTicketController';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { ticketStatusLabel } from '@/lib/storeSupportTicket';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreSupportTicket } from '@/types/store';
const props = defineProps<{
    tickets: Paginated<StoreSupportTicket>;
    filters: PerPageFilters & { search: string | null };
}>();
function onPerPageChange(perPage: number): void {
    router.get(
        ticketsIndex.url(),
        { search: props.filters.search, per_page: perPage },
        { preserveState: true, replace: true },
    );
}
function search(event: Event): void {
    const data = new FormData(event.target as HTMLFormElement);
    router.get(
        ticketsIndex.url(),
        {
            search: String(data.get('search') ?? ''),
            per_page: props.filters.per_page ?? 20,
        },
        { preserveState: true, replace: true },
    );
}
const access = useStoreAccess();
const columns = useTableColumns({
    listId: 'support.tickets',
    columns: [
        {
            key: 'ticketCode',
            label: 'store.support.ticketCode',
            required: true,
        },
        { key: 'date', label: 'store.support.date' },
        { key: 'subject', label: 'store.support.subject' },
        { key: 'customer', label: 'store.support.customer' },
        { key: 'status', label: 'store.support.status' },
        { key: 'lastReply', label: 'store.support.lastReply' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.support.actions'),
]);
</script>
<template>
    <Head :title="t('store.support.tickets')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.support.tickets')" />
        <form class="mb-4 flex items-end gap-3" @submit.prevent="search">
            <FormField
                id="search"
                :label="t('store.support.searchCode')"
                :value="filters.search ?? ''"
                maxlength="100"
            />
            <SubmitButton
                :label="t('store.support.search')"
                :processing-label="t('store.support.search')"
                :processing="false"
            />
        </form>
        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="tickets.data"
            :empty-message="t('store.support.emptyTickets')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">#{{ row.code }}</td>
                <td v-if="shown.date" class="px-4 py-2">
                    {{ row.created_at ? formatDateTime(row.created_at) : '' }}
                    <span v-if="!row.viewed"
                        >· {{ t('store.support.new') }}</span
                    >
                </td>
                <td
                    v-if="shown.subject"
                    class="max-w-xs truncate px-4 py-2"
                    :title="row.subject"
                >
                    {{ row.subject }}
                </td>
                <td v-if="shown.customer" class="px-4 py-2">
                    <TableObjectLink
                        v-if="row.user"
                        :allowed="access.can('edit_customer')"
                        :href="customerEdit(row.user)"
                        >{{ row.user.name }}</TableObjectLink
                    >
                    <template v-else>{{
                        t('store.support.missingUser')
                    }}</template>
                </td>
                <td v-if="shown.status" class="px-4 py-2">
                    {{ ticketStatusLabel(row.status) }}
                </td>
                <td v-if="shown.lastReply" class="px-4 py-2">
                    {{
                        row.last_reply_at
                            ? formatDateTime(row.last_reply_at)
                            : ''
                    }}
                </td>
                <td class="px-4 py-2">
                    <TableActionLink :href="show(row)"
                        ><Eye class="h-3.5 w-3.5" />{{
                            t('store.support.view')
                        }}</TableActionLink
                    >
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="tickets.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
