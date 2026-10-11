<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Eye, Trash2, TriangleAlert } from '@lucide/vue';
import {
    destroy,
    index as conversationsIndex,
    show,
} from '@/actions/App/Http/Controllers/Store/Admin/ConversationController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
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
</script>

<template>
    <Head :title="t('store.support.conversations')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.support.conversations')" />
        <p v-if="!enabled" class="mb-4 flex items-center gap-2">
            <TriangleAlert class="h-4 w-4" />{{ t('store.support.disabled') }}
        </p>
        <DataTable
            :columns="[
                t('store.support.date'),
                t('store.support.title'),
                t('store.support.sender'),
                t('store.support.receiver'),
                t('store.support.actions'),
            ]"
            :rows="conversations.data"
            :empty-message="t('store.support.emptyConversations')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    {{ row.created_at ? formatDateTime(row.created_at) : '' }}
                </td>
                <td class="px-4 py-2">{{ row.title }}</td>
                <td class="px-4 py-2">
                    {{ row.sender?.name ?? t('store.support.missingUser')
                    }}<span v-if="row.sender && !row.receiver_viewed">
                        · {{ t('store.support.new') }}</span
                    >
                </td>
                <td class="px-4 py-2">
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
