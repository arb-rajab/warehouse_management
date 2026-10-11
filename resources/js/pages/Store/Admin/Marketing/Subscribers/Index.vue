<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    destroy,
    index as subscribersIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/SubscriberController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { formatDate } from '@/lib/date';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreSubscriber } from '@/types/store';

const props = defineProps<{
    subscribers: Paginated<StoreSubscriber>;
    filters: PerPageFilters;
}>();

const access = useStoreAccess();

const currentPerPage = computed(() => props.filters.per_page ?? 20);

function onPerPageChange(perPage: number): void {
    router.get(
        subscribersIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.subscribers.index.title')" />

    <StoreAdminLayout>
        <PageHeader :title="t('store.subscribers.index.title')" />

        <DataTable
            :columns="[
                t('store.subscribers.index.columnEmail'),
                t('store.subscribers.index.columnDate'),
                t('store.subscribers.index.columnActions'),
            ]"
            :rows="subscribers.data"
            :empty-message="t('store.subscribers.index.empty')"
        >
            <template #row="{ row: subscriber }">
                <td
                    class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                >
                    {{ subscriber.email }}
                </td>
                <td class="px-4 py-2">
                    {{
                        subscriber.created_at
                            ? formatDate(subscriber.created_at)
                            : '—'
                    }}
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink
                            v-if="access.can('delete_subscriber')"
                            :href="destroy(subscriber, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(subscriber.email)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('store.subscribers.index.delete') }}
                        </TableActionLink>
                    </div>
                </td>
            </template>
        </DataTable>

        <Pagination
            :links="subscribers.meta.links"
            :per-page="currentPerPage"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
