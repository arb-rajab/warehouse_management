<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import DateRangeFilterFields from '@/components/DateRangeFilterFields.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterDialogFooter from '@/components/FilterDialogFooter.vue';
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import {
    createdDateRangeExclusivity,
    filterTriggerButtonClass,
    selectedCountLabel,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { PaginationLink } from '@/types/admin';
import type {
    StoreReportFilters,
    StoreReportOption,
    StoreReportSelection,
} from '@/types/storeReports';

const props = defineProps<{
    title: string;
    url: string;
    filters: StoreReportFilters;
    links: PaginationLink[];
    selection?: StoreReportSelection;
    selectionLabel?: string;
    options?: StoreReportOption[];
    dates?: boolean;
}>();

const open = ref(false);
const draft = reactive({
    selected: [] as string[],
    date_from: '',
    date_to: '',
    created_within_days: '',
    per_page: 20,
});

watch(
    () => props.filters,
    (filters) => {
        draft.selected = props.selection
            ? (filters[props.selection] ?? []).map(String)
            : [];
        draft.date_from = filters.date_from ?? '';
        draft.date_to = filters.date_to ?? '';
        draft.created_within_days =
            filters.created_within_days?.toString() ?? '';
        draft.per_page = filters.per_page ?? 20;
    },
    { immediate: true },
);

const { dateRangeDisabled, createdWithinDaysDisabled } =
    createdDateRangeExclusivity(draft);

function apply(): void {
    router.get(
        props.url,
        {
            per_page: draft.per_page,
            ...(props.selection ? { [props.selection]: draft.selected } : {}),
            ...(props.dates
                ? {
                      date_from: draft.date_from,
                      date_to: draft.date_to,
                      created_within_days: draft.created_within_days,
                  }
                : {}),
        },
        { preserveState: true, replace: true },
    );
    open.value = false;
}

function clear(): void {
    draft.selected = [];
    draft.date_from = '';
    draft.date_to = '';
    draft.created_within_days = '';
    apply();
}

function changePerPage(perPage: number): void {
    draft.per_page = perPage;
    apply();
}
</script>

<template>
    <Head :title="title" />
    <StoreAdminLayout>
        <PageHeader :title="title">
            <button
                v-if="selection || dates"
                type="button"
                :class="filterTriggerButtonClass"
                @click="open = true"
            >
                <SlidersHorizontal class="h-4 w-4" />
                {{ t('common.filters.title') }}
            </button>
        </PageHeader>
        <FilterDialog
            v-if="selection || dates"
            v-model:open="open"
            :title="t('common.filters.title')"
            :close-label="t('common.filters.close')"
        >
            <form
                id="store-report-filters"
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="apply"
            >
                <FilterMultiSelect
                    v-if="selection"
                    id="report-selection"
                    v-model="draft.selected"
                    :label="selectionLabel ?? ''"
                    :all-label="t('store.reports.all')"
                    :selected-count-label="selectedCountLabel"
                    :options="options ?? []"
                />
                <DateRangeFilterFields
                    v-if="dates"
                    v-model:from="draft.date_from"
                    v-model:to="draft.date_to"
                    v-model:within-days="draft.created_within_days"
                    from-id="report-date-from"
                    to-id="report-date-to"
                    within-days-id="report-within-days"
                    :from-label="t('store.reports.dateFrom')"
                    :to-label="t('store.reports.dateTo')"
                    :within-days-label="t('store.reports.withinDays')"
                    :range-disabled="dateRangeDisabled"
                    :days-disabled="createdWithinDaysDisabled"
                />
            </form>
            <template #footer>
                <FilterDialogFooter
                    form-id="store-report-filters"
                    :apply-label="t('common.filters.apply')"
                    :clear-label="t('common.filters.clear')"
                    @clear="clear"
                />
            </template>
        </FilterDialog>
        <slot />
        <Pagination
            :links="links"
            :per-page="draft.per_page"
            @update:per-page="changePerPage"
        />
    </StoreAdminLayout>
</template>
