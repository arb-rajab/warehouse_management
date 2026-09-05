<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/vue3';
import { Check, Download, SlidersHorizontal, X } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import {
    exportCsv,
    index as cellVerificationReportsIndex,
} from '@/actions/App/Http/Controllers/Admin/CellVerificationReportController';
import DataTable from '@/components/DataTable.vue';
import DateRangeFilterFields from '@/components/DateRangeFilterFields.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import FilterNumberField from '@/components/FilterNumberField.vue';
import FilterProductSelect from '@/components/FilterProductSelect.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import LocationFilterFields from '@/components/LocationFilterFields.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
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
import { formatSlot } from '@/lib/location';
import type {
    CellVerificationReport,
    CellVerificationReportFilterOptions,
    CellVerificationReportFilters,
    Paginated,
} from '@/types/admin';

const props = defineProps<{
    reports: Paginated<CellVerificationReport>;
    filters: CellVerificationReportFilters;
    filterOptions: CellVerificationReportFilterOptions;
}>();

const filters = reactive({
    cell_verification_round_id:
        props.filters.cell_verification_round_id?.toString() ?? '',
    row_id: props.filters.row_id?.toString() ?? '',
    column_number: props.filters.column_number?.toString() ?? '',
    user_id: (props.filters.user_id ?? []).map(String),
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
        filters.cell_verification_round_id !== '',
        filters.row_id !== '' || filters.column_number !== '',
        filters.user_id.length > 0,
        filters.product_id.length > 0,
        filters.is_correct !== '',
        filters.date_from !== '' ||
            filters.date_to !== '' ||
            filters.created_within_days !== '',
    ]),
);

function filterQuery(): Record<string, FormDataConvertible> {
    const { is_correct, ...rest } = filters;

    return is_correct === ''
        ? rest
        : { ...rest, is_correct: is_correct === 'true' };
}

function applyFilters(): void {
    router.get(cellVerificationReportsIndex().url, filterQuery(), {
        preserveState: true,
        replace: true,
    });
    filtersOpen.value = false;
}

function clearFilters(): void {
    filters.cell_verification_round_id = '';
    filters.row_id = '';
    filters.column_number = '';
    filters.user_id = [];
    filters.product_id = [];
    filters.is_correct = '';
    filters.date_from = '';
    filters.date_to = '';
    filters.created_within_days = '';
    filters.sort_direction = '';
    router.get(
        cellVerificationReportsIndex().url,
        { per_page: filters.per_page },
        { preserveState: true, replace: true },
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

function snapshotProductLabel(
    snapshot: CellVerificationReport['expected'],
): string | null {
    return snapshot.product?.name ?? null;
}
</script>

<template>
    <Head :title="t('cellVerificationReport.title')" />

    <AdminLayout>
        <PageHeader :title="t('cellVerificationReport.title')">
            <div class="flex items-center gap-2">
                <a
                    :href="exportCsv(filterQuery()).url"
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
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <FilterNumberField
                            id="filter-round-id"
                            v-model="filters.cell_verification_round_id"
                            :label="t('cellVerificationReport.filters.round')"
                        />

                        <FilterProductSelect
                            id="filter-product"
                            v-model="filters.product_id"
                            :label="t('cellVerificationReport.filters.product')"
                            :all-label="t('cellVerificationReport.filters.all')"
                            :selected-count-label="selectedCountLabel"
                            :selected="filterOptions.products"
                        />

                        <FilterMultiSelect
                            id="filter-user"
                            v-model="filters.user_id"
                            :label="
                                t('cellVerificationReport.filters.reportedBy')
                            "
                            :all-label="t('cellVerificationReport.filters.all')"
                            :selected-count-label="selectedCountLabel"
                            :options="
                                filterOptions.users.map((user) => ({
                                    value: user.id.toString(),
                                    label: user.name,
                                }))
                            "
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
                t('cellVerificationReport.columns.round'),
                t('cellVerificationReport.columns.cell'),
                t('cellVerificationReport.columns.correctness'),
                t('cellVerificationReport.columns.expected'),
                t('cellVerificationReport.columns.reported'),
                t('cellVerificationReport.columns.note'),
                t('cellVerificationReport.columns.reportedBy'),
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
                <td class="px-4 py-2">#{{ row.cell_verification_round_id }}</td>
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
                    <span
                        v-if="row.is_correct"
                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300"
                    >
                        {{ t('cellVerificationReport.correct') }}
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300"
                    >
                        {{ t('cellVerificationReport.incorrect') }}
                    </span>
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
                <td class="px-4 py-2">{{ row.user.name }}</td>
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
