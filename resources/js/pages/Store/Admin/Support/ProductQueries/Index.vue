<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import {
    index as queriesIndex,
    show,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductQueryController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreProductQuery } from '@/types/store';

defineProps<{
    queries: Paginated<StoreProductQuery>;
    filters: PerPageFilters;
}>();
function onPerPageChange(perPage: number): void {
    router.get(
        queriesIndex.url(),
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.support.queries')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.support.queries')" />
        <DataTable
            :columns="[
                t('store.support.customer'),
                t('store.support.product'),
                t('store.support.question'),
                t('store.support.reply'),
                t('store.support.status'),
                t('store.support.actions'),
            ]"
            :rows="queries.data"
            :empty-message="t('store.support.emptyQueries')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    {{ row.user?.name ?? t('store.support.missingUser') }}
                </td>
                <td class="px-4 py-2">
                    {{ row.product?.name ?? t('store.support.missingProduct') }}
                </td>
                <td class="px-4 py-2">{{ row.question }}</td>
                <td class="px-4 py-2">{{ row.reply }}</td>
                <td class="px-4 py-2">
                    {{
                        row.reply === null
                            ? t('store.support.unanswered')
                            : t('store.support.answered')
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
            :links="queries.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
