<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import {
    destroy,
    edit,
    index as statesIndex,
    store,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/StateController';
import DataTable from '@/components/DataTable.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreReferenceStatusForm from '@/components/StoreReferenceStatusForm.vue';
import StoreStateFormFields from '@/components/StoreStateFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import type { Paginated } from '@/types/admin';
import type { StoreCountry, StoreState } from '@/types/store';

const props = defineProps<{
    states: Paginated<StoreState>;
    countries: StoreCountry[];
    filters: { search?: string; country_id?: number; per_page: number };
}>();

const countryFilter = ref(String(props.filters.country_id ?? ''));

function onPerPageChange(perPage: number): void {
    router.get(
        statesIndex().url,
        { ...props.filters, per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.states.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.states.index.title')" />

        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            <Form
                :action="statesIndex()"
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
                    id="country_id"
                    :label="t('store.states.fields.country')"
                    :all-label="t('store.reference.all')"
                    :options="
                        countries.map((country) => ({
                            value: country.id,
                            label: country.name,
                        }))
                    "
                    v-model="countryFilter"
                />
                <input type="hidden" name="country_id" :value="countryFilter" />
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
                <h2 class="font-semibold">{{ t('store.states.index.add') }}</h2>
                <StoreStateFormFields :countries="countries" :errors="errors" />
                <SubmitButton
                    :label="t('common.save')"
                    :processing-label="t('common.saving')"
                    :processing="processing"
                />
            </Form>
        </div>

        <DataTable
            :columns="[
                t('store.states.index.columnName'),
                t('store.states.index.columnCountry'),
                t('store.states.index.columnStatus'),
                t('store.reference.actions'),
            ]"
            :rows="states.data"
            :empty-message="t('store.states.index.empty')"
        >
            <template #row="{ row: state }">
                <td class="px-4 py-2 font-medium">{{ state.name }}</td>
                <td class="px-4 py-2">{{ state.country?.name }}</td>
                <td class="px-4 py-2">
                    <StoreReferenceStatusForm
                        :action="updateStatus(state, { mergeQuery: {} })"
                        :active="state.active"
                    />
                </td>
                <td class="px-4 py-2">
                    <div class="flex gap-2">
                        <TableActionLink :href="edit(state)">
                            <Pencil class="h-3.5 w-3.5" />
                            {{ t('common.edit') }}
                        </TableActionLink>
                        <TableActionLink
                            :href="destroy(state, { mergeQuery: {} })"
                            method="delete"
                            as="button"
                            variant="danger"
                            :on-before="() => confirmDelete(state.name)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('common.delete') }}
                        </TableActionLink>
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="states.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
