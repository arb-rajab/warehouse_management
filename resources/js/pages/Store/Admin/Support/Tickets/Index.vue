<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import { index as ticketsIndex } from '@/actions/App/Http/Controllers/Store/Admin/SupportTicketController';
import { show } from '@/actions/App/Http/Controllers/Store/Admin/SupportTicketController';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
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
function statusLabel(status: string): string {
    return t(
        `store.support.${status === 'pending' || status === 'open' ? status : 'solved'}`,
    );
}
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
        <DataTable
            :columns="
                [
                    'ticketCode',
                    'date',
                    'subject',
                    'customer',
                    'status',
                    'lastReply',
                    'actions',
                ].map((key) => t(`store.support.${key}`))
            "
            :rows="tickets.data"
            :empty-message="t('store.support.emptyTickets')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">#{{ row.code }}</td>
                <td class="px-4 py-2">
                    {{ row.created_at ? formatDateTime(row.created_at) : '' }}
                    <span v-if="!row.viewed"
                        >· {{ t('store.support.new') }}</span
                    >
                </td>
                <td class="px-4 py-2">{{ row.subject }}</td>
                <td class="px-4 py-2">
                    {{ row.user?.name ?? t('store.support.missingUser') }}
                </td>
                <td class="px-4 py-2">{{ statusLabel(row.status) }}</td>
                <td class="px-4 py-2">
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
