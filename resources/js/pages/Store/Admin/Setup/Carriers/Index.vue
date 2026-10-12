<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import {
    index as resourceIndex,
    edit,
    destroy,
    create,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/CarrierController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreCarrier } from '@/types/store';
const props = defineProps<{
    carriers: Paginated<StoreCarrier>;
    filters: { per_page: number; search: string };
}>();
const access = useStoreAccess();
function changePerPage(per_page: number): void {
    router.get(
        resourceIndex.url(),
        { ...props.filters, per_page },
        { preserveState: true, replace: true },
    );
}
function filter(event: Event): void {
    router.get(
        resourceIndex.url(),
        {
            ...Object.fromEntries(
                new FormData(event.target as HTMLFormElement),
            ),
            per_page: props.filters.per_page,
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <StoreAdminLayout>
        <Head :title="t('store.carriers.title')" />
        <PageHeader :title="t('store.carriers.title')">
            <AddResourceLink
                v-if="access.can('manage_carriers')"
                :href="create()"
                :label="t('store.carriers.add')"
            />
        </PageHeader>
        <form class="mb-4 flex items-end gap-3" @submit.prevent="filter">
            <FormField
                id="search"
                :label="t('store.carriers.search')"
                :value="filters.search"
                maxlength="255"
            />
            <SubmitButton
                :label="t('common.filters.apply')"
                :processing-label="t('common.filters.apply')"
                :processing="false"
            />
        </form>
        <DataTable
            :rows="carriers.data"
            :columns="[
                t('store.carriers.name'),
                t('store.carriers.transitTime'),
                t('store.carriers.freeShipping'),
                t('store.reference.status'),
                t('store.reference.actions'),
            ]"
            :empty-message="t('store.reference.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">{{ row.name }}</td>
                <td class="px-4 py-2">{{ row.transit_time }}</td>
                <td class="px-4 py-2">
                    {{
                        t(
                            row.free_shipping
                                ? 'store.reference.enabled'
                                : 'store.reference.disabled',
                        )
                    }}
                </td>
                <td class="px-4 py-2">
                    <StoreStatusButton
                        v-if="access.can('manage_carriers')"
                        :action="updateStatus({ mergeQuery: { id: row.id } })"
                        :active="row.status"
                        :label="row.name"
                    />
                    <span v-else>{{
                        t(
                            row.status
                                ? 'store.reference.enabled'
                                : 'store.reference.disabled',
                        )
                    }}</span>
                </td>
                <td class="px-4 py-2">
                    <div
                        v-if="access.can('manage_carriers')"
                        class="flex gap-2"
                    >
                        <TableActionLink :href="edit(row)">
                            <Pencil class="h-3.5 w-3.5" />{{
                                t('store.reference.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            :href="destroy(row, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(row.name)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />{{
                                t('store.reference.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="carriers.meta.links"
            :per-page="filters.per_page"
            @update:per-page="changePerPage"
        />
    </StoreAdminLayout>
</template>
