<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import {
    destroy,
    edit,
    index as citiesIndex,
    store,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/CityController';
import DataTable from '@/components/DataTable.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreCityFormFields from '@/components/StoreCityFormFields.vue';
import StoreReferenceStatusForm from '@/components/StoreReferenceStatusForm.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import type { Paginated } from '@/types/admin';
import type { StoreCity, StoreState } from '@/types/store';

const props = defineProps<{
    cities: Paginated<StoreCity>;
    states: StoreState[];
    filters: { search?: string; state_id?: number; per_page: number };
}>();

const stateFilter = ref(String(props.filters.state_id ?? ''));

function onPerPageChange(perPage: number): void {
    router.get(
        citiesIndex().url,
        { ...props.filters, per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.cities.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.cities.index.title')" />

        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            <Form
                :action="citiesIndex()"
                class="space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-800"
                #default="{ processing }"
            >
                <FormField
                    id="search"
                    :label="t('store.reference.search')"
                    :value="filters.search"
                    maxlength="255"
                />
                <FilterSelect
                    v-model="stateFilter"
                    id="state_id_filter"
                    :label="t('store.cities.fields.state')"
                    :all-label="t('store.reference.all')"
                    :options="
                        states.map((state) => ({
                            value: state.id,
                            label: state.name,
                        }))
                    "
                />
                <input type="hidden" name="state_id" :value="stateFilter" />
                <input
                    type="hidden"
                    name="per_page"
                    :value="filters.per_page"
                />
                <SubmitButton
                    :label="t('store.reference.filter')"
                    :processing-label="t('store.reference.filtering')"
                    :processing="processing"
                />
            </Form>

            <Form
                :action="store()"
                class="space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-800"
                #default="{ errors, processing }"
            >
                <h2 class="font-semibold">{{ t('store.cities.index.add') }}</h2>
                <StoreCityFormFields :states="states" :errors="errors" />
                <SubmitButton
                    :label="t('common.save')"
                    :processing-label="t('common.saving')"
                    :processing="processing"
                />
            </Form>
        </div>

        <DataTable
            :columns="[
                t('store.cities.index.columnName'),
                t('store.cities.index.columnState'),
                t('store.cities.index.columnCost'),
                t('store.cities.index.columnStatus'),
                t('store.reference.actions'),
            ]"
            :rows="cities.data"
            :empty-message="t('store.cities.index.empty')"
        >
            <template #row="{ row: city }">
                <td class="px-4 py-2 font-medium">{{ city.name }}</td>
                <td class="px-4 py-2">{{ city.state?.name }}</td>
                <td class="px-4 py-2">{{ city.cost }}</td>
                <td class="px-4 py-2">
                    <StoreReferenceStatusForm
                        :action="updateStatus(city, { mergeQuery: {} })"
                        :active="city.active"
                    />
                </td>
                <td class="px-4 py-2">
                    <div class="flex gap-2">
                        <TableActionLink :href="edit(city)">
                            <Pencil class="h-3.5 w-3.5" />
                            {{ t('common.edit') }}
                        </TableActionLink>
                        <TableActionLink
                            :href="destroy(city, { mergeQuery: {} })"
                            method="delete"
                            as="button"
                            variant="danger"
                            :on-before="() => confirmDelete(city.name)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('common.delete') }}
                        </TableActionLink>
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="cities.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
