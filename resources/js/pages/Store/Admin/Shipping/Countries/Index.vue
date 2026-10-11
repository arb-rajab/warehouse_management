<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    index as countriesIndex,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/CountryController';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreReferenceStatusForm from '@/components/StoreReferenceStatusForm.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { Paginated } from '@/types/admin';
import type { StoreCountry } from '@/types/store';

const props = defineProps<{
    countries: Paginated<StoreCountry>;
    filters: { search?: string; per_page: number };
}>();

function onPerPageChange(perPage: number): void {
    router.get(
        countriesIndex().url,
        { search: props.filters.search ?? '', per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.countries.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.countries.index.title')" />

        <Form
            :action="countriesIndex()"
            class="mb-4 flex max-w-xl items-end gap-2"
            #default="{ processing }"
        >
            <div class="flex-1">
                <FormField
                    id="search"
                    :label="t('store.reference.search')"
                    :value="filters.search"
                    maxlength="100"
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
                t('store.countries.index.columnName'),
                t('store.countries.index.columnCode'),
                t('store.countries.index.columnStatus'),
            ]"
            :rows="countries.data"
            :empty-message="t('store.countries.index.empty')"
        >
            <template #row="{ row: country }">
                <td class="px-4 py-2 font-medium">{{ country.name }}</td>
                <td class="px-4 py-2">{{ country.code }}</td>
                <td class="px-4 py-2">
                    <StoreReferenceStatusForm
                        :action="updateStatus(country, { mergeQuery: {} })"
                        :active="country.active"
                    />
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="countries.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
