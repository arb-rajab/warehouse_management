<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Eye, Trash2, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import {
    destroy,
    index as conversationsIndex,
    show,
} from '@/actions/App/Http/Controllers/Store/Admin/ConversationController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreConversation } from '@/types/store';

defineProps<{
    conversations: Paginated<StoreConversation>;
    filters: PerPageFilters;
    enabled: boolean;
}>();
const access = useStoreAccess();
function onPerPageChange(perPage: number): void {
    router.get(
        conversationsIndex.url(),
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
const columns = useTableColumns({
    listId: 'support.conversations',
    columns: [
        { key: 'date', label: 'store.support.date' },
        { key: 'title', label: 'store.support.title', required: true },
        { key: 'sender', label: 'store.support.sender' },
        { key: 'receiver', label: 'store.support.receiver' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.support.actions'),
]);
</script>

<template>
    <Head :title="t('store.support.conversations')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.support.conversations')" />
        <p v-if="!enabled" class="mb-4 flex items-center gap-2">
            <TriangleAlert class="h-4 w-4" />{{ t('store.support.disabled') }}
        </p>
        <StoreColumnPicker :state="columns" />
        <DataTable
            :columns="tableColumns"
            :rows="conversations.data"
            :empty-message="t('store.support.emptyConversations')"
        >
            <template #row="{ row }">
                <td v-if="shown.date" class="px-4 py-2">
                    {{ row.created_at ? formatDateTime(row.created_at) : '' }}
                </td>
                <td
                    class="max-w-xs truncate px-4 py-2"
                    :title="row.title ?? undefined"
                >
                    {{ row.title }}
                </td>
                <td v-if="shown.sender" class="px-4 py-2">
                    {{ row.sender?.name ?? t('store.support.missingUser')
                    }}<span v-if="row.sender && !row.receiver_viewed">
                        · {{ t('store.support.new') }}</span
                    >
                </td>
                <td v-if="shown.receiver" class="px-4 py-2">
                    {{ row.receiver?.name ?? t('store.support.missingUser')
                    }}<span v-if="row.receiver && !row.sender_viewed">
                        · {{ t('store.support.new') }}</span
                    >
                </td>
                <td class="px-4 py-2">
                    <div class="flex gap-2">
                        <TableActionLink :href="show(row)"
                            ><Eye class="h-3.5 w-3.5" />{{
                                t('store.support.view')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('delete_product_conversations')"
                            :href="destroy(row, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(row.title ?? '')"
                            ><Trash2 class="h-3.5 w-3.5" />{{
                                t('store.support.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="conversations.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
