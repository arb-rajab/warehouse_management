<script setup lang="ts">
import { Head, Form, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import {
    index as resourceIndex,
    updateStatus,
    edit,
    destroy,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/CityController';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreCityFormFields from '@/components/StoreCityFormFields.vue';
import StoreSelectField from '@/components/StoreSelectField.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreCity, StoreReferenceOption } from '@/types/store';
const props = defineProps<{
    cities: Paginated<StoreCity>;
    filters: {
        per_page: number;
        sort_city: string | number | null;
        sort_state: string | number | null;
    };
    states: StoreReferenceOption[];
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
        <Head :title="t('store.cities.title')" />
        <PageHeader :title="t('store.cities.title')"> </PageHeader>
        <form
            class="mb-6 flex flex-wrap items-end gap-3"
            @submit.prevent="filter"
        >
            <FormField
                id="sort_city"
                :label="t('store.reference.search')"
                :value="filters.sort_city ?? ''"
                maxlength="255"
            />
            <StoreSelectField
                id="sort_state"
                :label="t('store.reference.state')"
                :options="states"
                :value="filters.sort_state"
            />
            <SubmitButton
                :label="t('store.reference.filter')"
                :processing-label="t('store.reference.filter')"
                :processing="false"
            />
        </form>
        <DataTable
            :rows="cities.data"
            :columns="[
                t('store.reference.name'),
                t('store.reference.state'),
                t('store.cities.cost'),
                t('store.reference.status'),
                t('store.reference.actions'),
            ]"
            :empty-message="t('store.reference.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">{{ row.name }}</td>
                <td class="px-4 py-2">{{ row.state?.name }}</td>
                <td class="px-4 py-2">{{ row.cost }}</td>
                <td class="px-4 py-2">
                    <StoreStatusButton
                        v-if="access.can('manage_shipping_cities')"
                        :action="updateStatus(row, { mergeQuery: {} })"
                        :active="row.status"
                        :label="row.name"
                    />
                </td>
                <td class="px-4 py-2">
                    <div
                        v-if="access.can('manage_shipping_cities')"
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
            :links="cities.meta.links"
            :per-page="filters.per_page"
            @update:per-page="changePerPage"
        />
        <section
            v-if="access.can('manage_shipping_cities')"
            class="mt-8 max-w-sm"
        >
            <h2 class="mb-4 text-lg font-semibold">
                {{ t('store.cities.add') }}
            </h2>
            <Form
                :action="store()"
                class="space-y-4"
                #default="{ errors, processing }"
                reset-on-success
            >
                <StoreCityFormFields :errors="errors" :states="states" />
                <SubmitButton
                    :label="t('store.cities.add')"
                    :processing-label="t('store.reference.saving')"
                    :processing="processing"
                />
            </Form>
        </section>
    </StoreAdminLayout>
</template>
