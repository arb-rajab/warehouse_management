<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Eye, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import StoreListFilters from '@/components/StoreListFilters.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { countActive } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
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
/** Dialog filters only: the two dates. */
const activeFilterCount = computed(() =>
    countActive([
        Boolean(props.filters.date_from),
        Boolean(props.filters.date_to),
    ]),
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
</script>

<template>
    <Head :title="t('store.representativeVisits.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.representativeVisits.title')" />
        <StoreListFilters
            :action="visitsIndex()"
            :active-count="activeFilterCount"
        >
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
                :columns="[
                    t('store.representativeVisits.select'),
                    t('store.representativeVisits.representative'),
                    t('store.representativeVisits.customer'),
                    t('store.representativeVisits.note'),
                    t('store.representativeVisits.date'),
                    t('store.representativeVisits.hasOrder'),
                    t('store.representativeVisits.actions'),
                ]"
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
                    <td class="px-4 py-2">{{ visit.representative?.name }}</td>
                    <td class="px-4 py-2">{{ visit.customer?.name }}</td>
                    <td class="px-4 py-2">{{ visit.note }}</td>
                    <td class="px-4 py-2">{{ visit.visit_date }}</td>
                    <td class="px-4 py-2">
                        {{
                            t(
                                visit.has_order
                                    ? 'store.representativeVisits.yes'
                                    : 'store.representativeVisits.no',
                            )
                        }}
                    </td>
                    <td class="px-4 py-2">
                        <div class="flex flex-wrap items-center gap-2">
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
