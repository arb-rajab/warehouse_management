<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Check, Download, SlidersHorizontal, X } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import {
    exportReports,
    index as cellVerificationRoundsIndex,
    show as showCellVerificationRound,
} from '@/actions/App/Http/Controllers/Admin/CellVerificationRoundController';
import { show as showUser } from '@/actions/App/Http/Controllers/Admin/UserController';
import CellVerificationCorrectnessBadge from '@/components/CellVerificationCorrectnessBadge.vue';
import DataTable from '@/components/DataTable.vue';
import DateRangeFilterFields from '@/components/DateRangeFilterFields.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterProductSelect from '@/components/FilterProductSelect.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import LocationFilterFields from '@/components/LocationFilterFields.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { snapshotProductLabel } from '@/lib/cellVerificationReportDisplay';
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
import { formatSlot } from '@/lib/location';
import type {
    CellVerificationReport,
    CellVerificationReportFilterOptions,
    CellVerificationReportFilters,
    CellVerificationRound,
    Paginated,
} from '@/types/admin';
import type { QueryParams } from '@/wayfinder';

const props = defineProps<{
    round: CellVerificationRound;
    reports: Paginated<CellVerificationReport>;
    filters: CellVerificationReportFilters;
    filterOptions: CellVerificationReportFilterOptions;
}>();

const filters = reactive({
    cell_id: props.filters.cell_id?.toString() ?? '',
    row_id: props.filters.row_id?.toString() ?? '',
    column_number: props.filters.column_number?.toString() ?? '',
    product_id: (props.filters.product_id ?? []).map(String),
    is_correct:
        props.filters.is_correct === undefined
            ? ''
            : props.filters.is_correct
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
        filters.row_id !== '' || filters.column_number !== '',
        filters.product_id.length > 0,
        filters.is_correct !== '',
        filters.date_from !== '' ||
            filters.date_to !== '' ||
            filters.created_within_days !== '',
    ]),
);

function filterQuery(): QueryParams {
    const { is_correct, ...rest } = filters;

    return is_correct === ''
        ? rest
        : { ...rest, is_correct: is_correct === 'true' };
}

const showUrl = () =>
    showCellVerificationRound({ cellVerificationRound: props.round.id }).url;

function applyFilters(): void {
    router.get(showUrl(), filterQuery(), {
        preserveState: true,
        replace: true,
    });
    filtersOpen.value = false;
}

function clearFilters(): void {
    filters.cell_id = '';
    filters.row_id = '';
    filters.column_number = '';
    filters.product_id = [];
    filters.is_correct = '';
    filters.date_from = '';
    filters.date_to = '';
    filters.created_within_days = '';
    filters.sort_direction = '';
    router.get(
        showUrl(),
        { per_page: filters.per_page },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function onSort(): void {
    toggleSort(
        { sort_by: 'created_at', sort_direction: filters.sort_direction },
        'created_at',
    );
    applyFilters();
}

function onPerPageChange(perPage: number): void {
    filters.per_page = perPage;
    applyFilters();
}
</script>

<template>
    <Head :title="`${t('cellVerificationRound.title')} #${round.id}`" />

    <AdminLayout>
        <PageHeader :title="`${t('cellVerificationRound.title')} #${round.id}`">
            <div class="flex items-center gap-2">
                <a
                    :href="
                        exportReports(
                            { cellVerificationRound: round.id },
                            { query: filterQuery() },
                        ).url
                    "
                    :class="filterClearButtonClass"
                >
                    <Download class="h-4 w-4" />
                    {{ t('cellVerificationReport.export') }}
                </a>
                <button
                    type="button"
                    :class="filterTriggerButtonClass"
                    @click="filtersOpen = true"
                >
                    <SlidersHorizontal class="h-4 w-4" />
                    {{ t('cellVerificationReport.filters.title') }}
                    <span
                        v-if="activeFilterCount > 0"
                        class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1 text-xs font-medium text-white dark:bg-blue-500"
                    >
                        {{ activeFilterCount }}
                    </span>
                </button>
            </div>
        </PageHeader>

        <TableLink
            :href="cellVerificationRoundsIndex().url"
            class="mb-4 inline-flex"
        >
            <ArrowLeft class="h-3.5 w-3.5 shrink-0 rtl:rotate-180" />
            {{ t('cellVerificationRound.show.backToList') }}
        </TableLink>

        <div
            class="mb-6 grid grid-cols-1 gap-4 rounded-lg border border-gray-200 p-4 text-sm sm:grid-cols-3 dark:border-neutral-800"
        >
            <div>
                <div class="text-gray-500 dark:text-neutral-400">
                    {{ t('cellVerificationRound.columns.user') }}
                </div>
                <div class="font-medium">
                    <TableLink
                        v-if="round.user"
                        :href="showUser({ id: round.user.id })"
                    >
                        {{ round.user.name }}
                    </TableLink>
                </div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-neutral-400">
                    {{ t('cellVerificationRound.show.startedAt') }}
                </div>
                <div class="font-medium">
                    {{ formatDateTime(round.started_at) }}
                </div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-neutral-400">
                    {{ t('cellVerificationRound.show.completedAt') }}
                </div>
                <div class="font-medium">
                    <span
                        v-if="round.completed_at"
                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300"
                    >
                        {{ formatDateTime(round.completed_at) }}
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                    >
                        {{ t('cellVerificationRound.status.inProgress') }}
                    </span>
                </div>
            </div>
        </div>

        <FilterDialog
            v-model:open="filtersOpen"
            :title="t('cellVerificationReport.filters.title')"
            :close-label="t('cellVerificationReport.filters.close')"
        >
            <form class="space-y-6" @submit.prevent="applyFilters">
                <div>
                    <h3 :class="sectionHeadingClass">
                        {{
                            t(
                                'cellVerificationReport.filters.sections.location',
                            )
                        }}
                    </h3>
                    <LocationFilterFields
                        id-prefix="filter"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        v-model:row-id="filters.row_id"
                        v-model:column-number="filters.column_number"
                        :rows="filterOptions.rows"
                        :max-column-number="filterOptions.maxColumnNumber"
                    />
                </div>

                <div
                    class="border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <h3 :class="sectionHeadingClass">
                        {{
                            t(
                                'cellVerificationReport.filters.sections.activity',
                            )
                        }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FilterProductSelect
                            id="filter-product"
                            v-model="filters.product_id"
                            :label="t('cellVerificationReport.filters.product')"
                            :all-label="t('cellVerificationReport.filters.all')"
                            :selected-count-label="selectedCountLabel"
                            :selected="filterOptions.products"
                        />

                        <FilterSelect
                            id="filter-correctness"
                            v-model="filters.is_correct"
                            :label="
                                t('cellVerificationReport.filters.correctness')
                            "
                            :all-label="
                                t(
                                    'cellVerificationReport.filters.correctnessAll',
                                )
                            "
                            :options="[
                                {
                                    value: 'true',
                                    label: t(
                                        'cellVerificationReport.filters.correctnessCorrect',
                                    ),
                                },
                                {
                                    value: 'false',
                                    label: t(
                                        'cellVerificationReport.filters.correctnessIncorrect',
                                    ),
                                },
                            ]"
                        />
                    </div>
                </div>

                <div
                    class="border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellVerificationReport.filters.sections.date') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <DateRangeFilterFields
                            from-id="filter-date-from"
                            to-id="filter-date-to"
                            within-days-id="filter-created-within-days"
                            :from-label="
                                t('cellVerificationReport.filters.from')
                            "
                            :to-label="t('cellVerificationReport.filters.to')"
                            :within-days-label="
                                t('cellVerificationReport.filters.withinDays')
                            "
                            v-model:from="filters.date_from"
                            v-model:to="filters.date_to"
                            v-model:within-days="filters.created_within_days"
                            :range-disabled="dateRangeDisabled"
                            :days-disabled="createdWithinDaysDisabled"
                        />
                    </div>
                </div>

                <div
                    class="flex items-center gap-2 border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <button type="submit" :class="filterApplyButtonClass">
                        <Check class="h-4 w-4 shrink-0" />
                        {{ t('cellVerificationReport.filters.apply') }}
                    </button>
                    <button
                        type="button"
                        :class="filterClearButtonClass"
                        @click="clearFilters"
                    >
                        <X class="h-4 w-4 shrink-0" />
                        {{ t('cellVerificationReport.filters.clear') }}
                    </button>
                </div>
            </form>
        </FilterDialog>

        <DataTable
            :columns="[
                t('cellVerificationReport.columns.cell'),
                t('cellVerificationReport.columns.correctness'),
                t('cellVerificationReport.columns.expected'),
                t('cellVerificationReport.columns.reported'),
                t('cellVerificationReport.columns.note'),
                {
                    label: t('cellVerificationReport.columns.when'),
                    sortKey: 'created_at',
                },
            ]"
            :rows="reports.data"
            :empty-message="t('cellVerificationReport.empty')"
            :sort="{
                by: 'created_at',
                direction: filters.sort_direction === 'asc' ? 'asc' : 'desc',
            }"
            @sort="onSort"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">
                    {{
                        formatSlot(
                            row.cell.row_letter,
                            row.cell.cell_number,
                            row.cell.flat_number,
                        )
                    }}
                </td>
                <td class="px-4 py-2">
                    <CellVerificationCorrectnessBadge
                        :is-correct="row.is_correct"
                    />
                </td>
                <td class="px-4 py-2">
                    <div>{{ row.expected.cell_state }}</div>
                    <div
                        v-if="snapshotProductLabel(row.expected)"
                        class="text-xs text-gray-500 dark:text-neutral-400"
                    >
                        {{ snapshotProductLabel(row.expected) }} ·
                        {{ row.expected.boxes_count }}
                    </div>
                </td>
                <td class="px-4 py-2">
                    <div>{{ row.reported.cell_state ?? '—' }}</div>
                    <div
                        v-if="snapshotProductLabel(row.reported)"
                        class="text-xs text-gray-500 dark:text-neutral-400"
                    >
                        {{ snapshotProductLabel(row.reported) }} ·
                        {{ row.reported.boxes_count }}
                    </div>
                </td>
                <td class="px-4 py-2">{{ row.note ?? '—' }}</td>
                <td class="px-4 py-2">{{ formatDateTime(row.created_at) }}</td>
            </template>
        </DataTable>

        <Pagination
            :links="reports.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />
    </AdminLayout>
</template>
