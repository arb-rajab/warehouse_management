<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/vue3';
import { Check, SlidersHorizontal, X } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import {
    index as cellVerificationRoundsIndex,
    show as showCellVerificationRound,
} from '@/actions/App/Http/Controllers/Admin/CellVerificationRoundController';
import { show as showUser } from '@/actions/App/Http/Controllers/Admin/UserController';
import DataTable from '@/components/DataTable.vue';
import DateRangeFilterFields from '@/components/DateRangeFilterFields.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import {
    countActive,
    exclusivePair,
    filterApplyButtonClass,
    filterClearButtonClass,
    filterSectionHeadingClass as sectionHeadingClass,
    filterTriggerButtonClass,
    selectedCountLabel,
    toggleSort,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    CellVerificationRound,
    CellVerificationRoundFilterOptions,
    CellVerificationRoundFilters,
    Paginated,
} from '@/types/admin';

const props = defineProps<{
    rounds: Paginated<CellVerificationRound>;
    filters: CellVerificationRoundFilters;
    filterOptions: CellVerificationRoundFilterOptions;
}>();

const filters = reactive({
    user_id: (props.filters.user_id ?? []).map(String),
    completed:
        props.filters.completed === undefined
            ? ''
            : props.filters.completed
              ? 'true'
              : 'false',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    created_within_days: props.filters.created_within_days?.toString() ?? '',
    sort_direction: props.filters.sort_direction ?? '',
    per_page: props.filters.per_page ?? 20,
});

const {
    rangeDisabled: dateRangeDisabled,
    daysDisabled: createdWithinDaysDisabled,
} = exclusivePair(
    () => filters.date_from !== '' || filters.date_to !== '',
    () => filters.created_within_days !== '',
);

const filtersOpen = ref(false);

const activeFilterCount = computed(() =>
    countActive([
        filters.user_id.length > 0,
        filters.completed !== '',
        filters.date_from !== '' ||
            filters.date_to !== '' ||
            filters.created_within_days !== '',
    ]),
);

function filterQuery(): Record<string, FormDataConvertible> {
    const { completed, ...rest } = filters;

    return completed === ''
        ? rest
        : { ...rest, completed: completed === 'true' };
}

function applyFilters(): void {
    router.get(cellVerificationRoundsIndex().url, filterQuery(), {
        preserveState: true,
        replace: true,
    });
    filtersOpen.value = false;
}

function clearFilters(): void {
    filters.user_id = [];
    filters.completed = '';
    filters.date_from = '';
    filters.date_to = '';
    filters.created_within_days = '';
    filters.sort_direction = '';
    router.get(
        cellVerificationRoundsIndex().url,
        { per_page: filters.per_page },
        { preserveState: true, replace: true },
    );
}

function onSort(): void {
    const sort = {
        sort_by: 'created_at',
        sort_direction: filters.sort_direction,
    };
    toggleSort(sort, 'created_at');
    filters.sort_direction = sort.sort_direction;
    applyFilters();
}

function onPerPageChange(perPage: number): void {
    filters.per_page = perPage;
    applyFilters();
}
</script>

<template>
    <Head :title="t('cellVerificationRound.title')" />

    <AdminLayout>
        <PageHeader :title="t('cellVerificationRound.title')">
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="filtersOpen = true"
            >
                <SlidersHorizontal class="h-4 w-4" />
                {{ t('cellVerificationRound.filters.title') }}
                <span
                    v-if="activeFilterCount > 0"
                    class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1 text-xs font-medium text-white dark:bg-blue-500"
                >
                    {{ activeFilterCount }}
                </span>
            </button>
        </PageHeader>

        <FilterDialog
            v-model:open="filtersOpen"
            :title="t('cellVerificationRound.filters.title')"
            :close-label="t('cellVerificationRound.filters.close')"
        >
            <form class="space-y-6" @submit.prevent="applyFilters">
                <div>
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellVerificationRound.filters.title') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FilterMultiSelect
                            id="filter-user"
                            v-model="filters.user_id"
                            :label="t('cellVerificationRound.filters.user')"
                            :all-label="t('cellVerificationRound.filters.all')"
                            :selected-count-label="selectedCountLabel"
                            :options="
                                filterOptions.users.map((user) => ({
                                    value: user.id.toString(),
                                    label: user.name,
                                }))
                            "
                        />

                        <FilterSelect
                            id="filter-completed"
                            v-model="filters.completed"
                            :label="
                                t('cellVerificationRound.filters.completed')
                            "
                            :all-label="
                                t('cellVerificationRound.filters.completedAll')
                            "
                            :options="[
                                {
                                    value: 'true',
                                    label: t(
                                        'cellVerificationRound.filters.completedYes',
                                    ),
                                },
                                {
                                    value: 'false',
                                    label: t(
                                        'cellVerificationRound.filters.completedNo',
                                    ),
                                },
                            ]"
                        />
                    </div>
                </div>

                <div
                    class="border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <DateRangeFilterFields
                        from-id="filter-date-from"
                        to-id="filter-date-to"
                        within-days-id="filter-created-within-days"
                        :from-label="t('cellVerificationRound.filters.from')"
                        :to-label="t('cellVerificationRound.filters.to')"
                        :within-days-label="
                            t('cellVerificationRound.filters.withinDays')
                        "
                        class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                        v-model:from="filters.date_from"
                        v-model:to="filters.date_to"
                        v-model:within-days="filters.created_within_days"
                        :range-disabled="dateRangeDisabled"
                        :days-disabled="createdWithinDaysDisabled"
                    />
                </div>

                <div
                    class="flex items-center gap-2 border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <button type="submit" :class="filterApplyButtonClass">
                        <Check class="h-4 w-4 shrink-0" />
                        {{ t('cellVerificationRound.filters.apply') }}
                    </button>
                    <button
                        type="button"
                        :class="filterClearButtonClass"
                        @click="clearFilters"
                    >
                        <X class="h-4 w-4 shrink-0" />
                        {{ t('cellVerificationRound.filters.clear') }}
                    </button>
                </div>
            </form>
        </FilterDialog>

        <DataTable
            :columns="[
                t('cellVerificationRound.columns.id'),
                t('cellVerificationRound.columns.user'),
                {
                    label: t('cellVerificationRound.columns.startedAt'),
                    sortKey: 'created_at',
                },
                t('cellVerificationRound.columns.completedAt'),
                t('cellVerificationRound.columns.reportsCount'),
            ]"
            :rows="rounds.data"
            :empty-message="t('cellVerificationRound.empty')"
            :sort="{
                by: 'created_at',
                direction: filters.sort_direction === 'asc' ? 'asc' : 'desc',
            }"
            @sort="onSort"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    <TableLink
                        :href="
                            showCellVerificationRound({
                                cellVerificationRound: row.id,
                            }).url
                        "
                    >
                        #{{ row.id }}
                    </TableLink>
                </td>
                <td class="px-4 py-2">
                    <TableLink
                        v-if="row.user"
                        :href="showUser({ id: row.user.id })"
                    >
                        {{ row.user.name }}
                    </TableLink>
                </td>
                <td class="px-4 py-2">{{ formatDateTime(row.started_at) }}</td>
                <td class="px-4 py-2">
                    <span
                        v-if="row.completed_at"
                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300"
                    >
                        {{ t('cellVerificationRound.status.completed') }}
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                    >
                        {{ t('cellVerificationRound.status.inProgress') }}
                    </span>
                </td>
                <td class="px-4 py-2">{{ row.reports_count }}</td>
            </template>
        </DataTable>

        <Pagination
            :links="rounds.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </AdminLayout>
</template>
