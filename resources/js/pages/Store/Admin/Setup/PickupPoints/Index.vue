<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2, CircleCheck, CircleSlash } from '@lucide/vue';
import {
    index as resourceIndex,
    edit,
    destroy,
    create,
} from '@/actions/App/Http/Controllers/Store/Admin/PickupPointController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StorePickupPoint } from '@/types/store';
const props = defineProps<{
    pickupPoints: Paginated<StorePickupPoint>;
    filters: { per_page: number; search: string | number | null };
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
        <Head :title="t('store.pickupPoints.title')" />
        <PageHeader :title="t('store.pickupPoints.title')">
            <AddResourceLink
                v-if="access.can('pickup_point_setup')"
                :href="create()"
                :label="t('store.pickupPoints.add')"
            />
        </PageHeader>
        <form
            class="mb-6 flex flex-wrap items-end gap-3"
            @submit.prevent="filter"
        >
            <FormField
                id="search"
                :label="t('store.reference.search')"
                :value="filters.search ?? ''"
                maxlength="255"
            />
            <SubmitButton
                :label="t('store.reference.filter')"
                :processing-label="t('store.reference.filter')"
                :processing="false"
            />
        </form>
        <DataTable
            :rows="pickupPoints.data"
            :columns="[
                t('store.reference.name'),
                t('store.pickupPoints.address'),
                t('store.pickupPoints.phone'),
                t('store.pickupPoints.manager'),
                t('store.reference.status'),
                t('store.reference.actions'),
            ]"
            :empty-message="t('store.reference.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">{{ row.name }}</td>
                <td class="px-4 py-2">{{ row.address }}</td>
                <td class="px-4 py-2">{{ row.phone }}</td>
                <td class="px-4 py-2">{{ row.staff?.name }}</td>
                <td class="px-4 py-2">
                    <span class="inline-flex items-center gap-1">
                        <CircleCheck
                            v-if="row.pick_up_status"
                            class="h-4 w-4"
                        />
                        <CircleSlash v-else class="h-4 w-4" />{{
                            t(
                                row.pick_up_status
                                    ? 'store.reference.enabled'
                                    : 'store.reference.disabled',
                            )
                        }}</span
                    >
                </td>
                <td class="px-4 py-2">
                    <div
                        v-if="access.can('pickup_point_setup')"
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
            :links="pickupPoints.meta.links"
            :per-page="filters.per_page"
            @update:per-page="changePerPage"
        />
    </StoreAdminLayout>
</template>
