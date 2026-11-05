<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Eye, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import {
    bulkDestroy,
    destroy,
    index as visitsIndex,
    show,
    show_all_visits_in_a_day,
} from '@/actions/App/Http/Controllers/Store/Admin/VisitController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreListFilters from '@/components/StoreListFilters.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type { StoreFilterChip } from '@/types/store';
import type { RepresentativeVisit } from '@/types/storeRepresentatives';

const props = defineProps<{
    visits: Paginated<RepresentativeVisit>;
    filters: {
        per_page: number;
        search: string;
        date_from: string;
        date_to: string;
    };
}>();
const access = useStoreAccess();
/** The "to" date cannot precede the "from" date; both inputs stay uncontrolled. */
const dateFrom = ref(props.filters.date_from);
/** Dialog filters only: one chip per date bound. */
const appliedFilters = computed<StoreFilterChip[]>(() =>
    (
        [
            ['date_from', 'store.representativeVisits.dateFrom'],
            ['date_to', 'store.representativeVisits.dateTo'],
        ] as const
    )
        .filter(([key]) => Boolean(props.filters[key]))
        .map(([key, label]) => ({
            key,
            label: t(label),
            value: props.filters[key],
            keys: [key],
        })),
);
const selection = useForm({ id: [] as number[] });
/** Change the page size, keeping the applied search and dates. */
function changePerPage(perPage: number): void {
    router.get(
        visitsIndex().url,
        {
            search: props.filters.search,
            date_from: props.filters.date_from,
            date_to: props.filters.date_to,
            per_page: perPage,
        },
        { preserveState: true, replace: true },
    );
}
function deleteSelection(): void {
    if (
        selection.id.length &&
        confirmDelete(t('store.representativeVisits.selection'))
    ) {
        selection.submit(bulkDestroy({ mergeQuery: {} }), {
            preserveScroll: true,
            onSuccess: () => selection.reset(),
        });
    }
}
const columns = useTableColumns({
    listId: 'representatives.visits',
    columns: [
        {
            key: 'representative',
            label: 'store.representativeVisits.representative',
            required: true,
        },
        { key: 'customer', label: 'store.representativeVisits.customer' },
        { key: 'note', label: 'store.representativeVisits.note' },
        { key: 'date', label: 'store.representativeVisits.date' },
        { key: 'hasOrder', label: 'store.representativeVisits.hasOrder' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    t('store.representativeVisits.select'),
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.representativeVisits.actions'),
]);
</script>

<template>
    <Head :title="t('store.representativeVisits.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.representativeVisits.title')" />
        <StoreListFilters :action="visitsIndex()" :chips="appliedFilters">
            <template #default="{ errors }">
                <input
                    type="hidden"
                    name="per_page"
                    :value="filters.per_page"
                />
                <FormField
                    id="search"
                    :value="filters.search"
                    :error="errors.search"
                    :label="t('store.representativeVisits.search')"
                    maxlength="255"
                />
            </template>
            <template #filters="{ errors }">
                <FormField
                    id="date_from"
                    type="date"
                    :value="filters.date_from"
                    :error="errors.date_from"
                    :label="t('store.representativeVisits.dateFrom')"
                    input-class="dark:[color-scheme:dark]"
                    @input="
                        dateFrom = ($event.target as HTMLInputElement).value
                    "
                />
                <FormField
                    id="date_to"
                    type="date"
                    :value="filters.date_to"
                    :error="errors.date_to"
                    :label="t('store.representativeVisits.dateTo')"
                    :min="dateFrom"
                    input-class="dark:[color-scheme:dark]"
                />
            </template>
        </StoreListFilters>
        <ActionErrorBanner :message="firstError(selection.errors)" />
        <StoreColumnPicker :state="columns" />
        <form @submit.prevent="deleteSelection">
            <button
                v-if="access.can('delete_representative_visit')"
                type="submit"
                :disabled="selection.processing || !selection.id.length"
                class="mb-3 inline-flex cursor-pointer items-center gap-1 text-sm text-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:text-red-400"
            >
                <Trash2 class="h-3.5 w-3.5" />{{
                    t('store.representativeVisits.deleteSelection')
                }}
            </button>
            <DataTable
                :columns="tableColumns"
                :rows="visits.data"
                :empty-message="t('store.representativeVisits.empty')"
            >
                <template #row="{ row: visit }">
                    <td class="px-4 py-2">
                        <input
                            v-if="access.can('delete_representative_visit')"
                            v-model="selection.id"
                            type="checkbox"
                            :value="visit.id"
                            :aria-label="
                                t('store.representativeVisits.selectVisit', {
                                    id: visit.id,
                                })
                            "
                            class="cursor-pointer"
                        />
                    </td>
                    <td class="px-4 py-2">
                        <TableObjectLink
                            v-if="visit.representative"
                            :allowed="access.can('edit_customer')"
                            :href="customerEdit(visit.representative)"
                            >{{ visit.representative.name }}</TableObjectLink
                        >
                    </td>
                    <td v-if="shown.customer" class="px-4 py-2">
                        <TableObjectLink
                            v-if="visit.customer"
                            :allowed="access.can('edit_customer')"
                            :href="customerEdit(visit.customer)"
                            >{{ visit.customer.name }}</TableObjectLink
                        >
                    </td>
                    <td
                        v-if="shown.note"
                        class="max-w-xs truncate px-4 py-2"
                        :title="visit.note"
                    >
                        {{ visit.note }}
                    </td>
                    <td v-if="shown.date" class="px-4 py-2">
                        {{ visit.visit_date }}
                    </td>
                    <td v-if="shown.hasOrder" class="px-4 py-2">
                        {{
                            t(
                                visit.has_order
                                    ? 'store.representativeVisits.yes'
                                    : 'store.representativeVisits.no',
                            )
                        }}
                    </td>
                    <td class="px-4 py-2">
                        <div class="flex flex-nowrap items-center gap-2">
                            <Link
                                :href="show(visit)"
                                class="inline-flex items-center gap-1 text-sm underline"
                                ><Eye class="h-3.5 w-3.5" />{{
                                    t('store.representativeVisits.details')
                                }}</Link
                            >
                            <Link
                                v-if="visit.representative && visit.visit_date"
                                :href="
                                    show_all_visits_in_a_day(
                                        visit.representative,
                                        {
                                            query: {
                                                date: visit.visit_date.slice(
                                                    0,
                                                    10,
                                                ),
                                            },
                                        },
                                    )
                                "
                                class="inline-flex items-center gap-1 text-sm underline"
                                ><Eye class="h-3.5 w-3.5" />{{
                                    t('store.representativeVisits.day')
                                }}</Link
                            >
                            <TableActionLink
                                v-if="access.can('delete_representative_visit')"
                                :href="destroy(visit, { mergeQuery: {} })"
                                variant="danger"
                                method="delete"
                                as="button"
                                :on-before="
                                    () =>
                                        confirmDelete(
                                            t(
                                                'store.representativeVisits.visit',
                                                { id: visit.id },
                                            ),
                                        )
                                "
                                ><Trash2 class="h-3.5 w-3.5" />{{
                                    t('store.representativeVisits.delete')
                                }}</TableActionLink
                            >
                        </div>
                    </td>
                </template>
            </DataTable>
        </form>
        <Pagination
            :links="visits.meta.links"
            :per-page="filters.per_page"
            @update:per-page="changePerPage"
        />
    </StoreAdminLayout>
</template>
