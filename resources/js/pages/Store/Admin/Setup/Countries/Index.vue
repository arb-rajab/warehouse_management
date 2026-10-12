<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    index as resourceIndex,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/CountryController';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreCountry } from '@/types/store';
const props = defineProps<{
    countries: Paginated<StoreCountry>;
    filters: { per_page: number; sort_country: string | number | null };
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
        <Head :title="t('store.countries.title')" />
        <PageHeader :title="t('store.countries.title')"> </PageHeader>
        <form
            class="mb-6 flex flex-wrap items-end gap-3"
            @submit.prevent="filter"
        >
            <FormField
                id="sort_country"
                :label="t('store.reference.search')"
                :value="filters.sort_country ?? ''"
                maxlength="100"
            />
            <SubmitButton
                :label="t('store.reference.filter')"
                :processing-label="t('store.reference.filter')"
                :processing="false"
            />
        </form>
        <DataTable
            :rows="countries.data"
            :columns="[
                t('store.reference.name'),
                t('store.countries.code'),
                t('store.reference.status'),
            ]"
            :empty-message="t('store.reference.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">{{ row.name }}</td>
                <td class="px-4 py-2">{{ row.code }}</td>
                <td class="px-4 py-2">
                    <StoreStatusButton
                        v-if="access.can('shipping_country_setting')"
                        :action="updateStatus(row, { mergeQuery: {} })"
                        :active="row.status"
                        :label="row.name"
                    />
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="countries.meta.links"
            :per-page="filters.per_page"
            @update:per-page="changePerPage"
        />
    </StoreAdminLayout>
</template>
