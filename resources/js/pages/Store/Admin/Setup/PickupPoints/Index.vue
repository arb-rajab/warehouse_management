<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import {
    create,
    destroy,
    edit,
    index as pickupPointsIndex,
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
import type { Paginated } from '@/types/admin';
import type { StorePickupPoint } from '@/types/store';

const props = defineProps<{
    pickupPoints: Paginated<StorePickupPoint>;
    filters: { search?: string; per_page: number };
}>();

function onPerPageChange(perPage: number): void {
    router.get(
        pickupPointsIndex().url,
        { search: props.filters.search ?? '', per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.pickupPoints.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.pickupPoints.index.title')">
            <AddResourceLink
                :href="create()"
                :label="t('store.pickupPoints.index.add')"
            />
        </PageHeader>
        <Form
            :action="pickupPointsIndex()"
            class="mb-4 flex max-w-xl items-end gap-2"
            #default="{ processing }"
        >
            <div class="flex-1">
                <FormField
                    id="search"
                    :label="t('store.reference.search')"
                    :value="filters.search"
                    maxlength="50"
                />
            </div>
            <input type="hidden" name="per_page" :value="filters.per_page" />
            <SubmitButton
                :label="t('store.reference.filter')"
                :processing-label="t('store.reference.filtering')"
                :processing="processing"
            />
        </Form>
        <DataTable
            :columns="[
                t('store.pickupPoints.index.columnName'),
                t('store.pickupPoints.index.columnManager'),
                t('store.pickupPoints.index.columnAddress'),
                t('store.pickupPoints.index.columnPhone'),
                t('store.pickupPoints.index.columnStatus'),
                t('store.reference.actions'),
            ]"
            :rows="pickupPoints.data"
            :empty-message="t('store.pickupPoints.index.empty')"
        >
            <template #row="{ row: pickupPoint }">
                <td class="px-4 py-2 font-medium">{{ pickupPoint.name }}</td>
                <td class="px-4 py-2">
                    {{
                        pickupPoint.manager?.name ??
                        t('store.pickupPoints.index.noManager')
                    }}
                </td>
                <td class="px-4 py-2">{{ pickupPoint.address }}</td>
                <td class="px-4 py-2">{{ pickupPoint.phone }}</td>
                <td class="px-4 py-2">
                    {{
                        t(
                            pickupPoint.active
                                ? 'store.pickupPoints.index.open'
                                : 'store.pickupPoints.index.closed',
                        )
                    }}
                </td>
                <td class="px-4 py-2">
                    <div class="flex gap-2">
                        <TableActionLink :href="edit(pickupPoint)">
                            <Pencil class="h-3.5 w-3.5" />
                            {{ t('common.edit') }}
                        </TableActionLink>
                        <TableActionLink
                            :href="destroy(pickupPoint, { mergeQuery: {} })"
                            method="delete"
                            as="button"
                            variant="danger"
                            :on-before="() => confirmDelete(pickupPoint.name)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('common.delete') }}
                        </TableActionLink>
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="pickupPoints.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
