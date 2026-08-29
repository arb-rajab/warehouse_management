<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Clock,
    History,
    SlidersHorizontal,
    TriangleAlert,
    X,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import {
    acknowledgeFlags as acknowledgeFlagsAction,
    index as cellLogsIndex,
} from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { show as showRow } from '@/actions/App/Http/Controllers/Admin/RowController';
import { edit as editUser } from '@/actions/App/Http/Controllers/Admin/UserController';
import CellLogActivityFilterFields from '@/components/CellLogActivityFilterFields.vue';
import CellLogFlagBadges from '@/components/CellLogFlagBadges.vue';
import DataTable from '@/components/DataTable.vue';
import DateRangeFilterFields from '@/components/DateRangeFilterFields.vue';
import FilterCheckbox from '@/components/FilterCheckbox.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import FilterProductSelect from '@/components/FilterProductSelect.vue';
import LocationFilterFields from '@/components/LocationFilterFields.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { cellStateLabel } from '@/lib/cellStateColor';
import {
    cellLogActionLabel,
    mergeTransferPairs,
    transferPair,
} from '@/lib/cellStatusLogDisplay';
import { formatDate, formatDateTime, formatDuration } from '@/lib/date';
import {
    countActive,
    countBadgeClass,
    exclusivePair,
    filterApplyButtonClass,
    filterClearButtonClass,
    filterSectionHeadingClass as sectionHeadingClass,
    filterTriggerButtonClass,
    selectedCountLabel,
    toggleSort,
    useColumnFilterPopover,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import type {
    CellStatusLog,
    CellStatusLogFilterOptions,
    CellStatusLogFilters,
    Paginated,
} from '@/types/admin';

const props = defineProps<{
    logs: Paginated<CellStatusLog>;
    filters: CellStatusLogFilters;
    filterOptions: CellStatusLogFilterOptions;
}>();

function hasUnacknowledgedFlags(log: CellStatusLog): boolean {
    return log.flags.some((flag) => !flag.acknowledged);
}

function acknowledgeFlags(log: CellStatusLog): void {
    router.post(
        acknowledgeFlagsAction({ cellStatusLog: log.id }, { mergeQuery: {} })
            .url,
        {},
        {
            preserveScroll: true,
        },
    );
}

const filters = reactive({
    product_id: (props.filters.product_id ?? []).map(String),
    pallet_id: props.filters.pallet_id?.toString() ?? '',
    row_id: props.filters.row_id?.toString() ?? '',
    column_number: props.filters.column_number?.toString() ?? '',
    user_id: (props.filters.user_id ?? []).map(String),
    action: [...(props.filters.action ?? [])],
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    created_within_days: props.filters.created_within_days?.toString() ?? '',
    expiration_date_from: props.filters.expiration_date_from ?? '',
    expiration_date_to: props.filters.expiration_date_to ?? '',
    expires_within_days: props.filters.expires_within_days?.toString() ?? '',
    sort_by: props.filters.sort_by ?? '',
    sort_direction: props.filters.sort_direction ?? '',
    flagged: props.filters.flagged ?? false,
});

const {
    rangeDisabled: dateRangeDisabled,
    daysDisabled: createdWithinDaysDisabled,
} = exclusivePair(
    () => filters.date_from !== '' || filters.date_to !== '',
    () => filters.created_within_days !== '',
);

const {
    rangeDisabled: expirationRangeDisabled,
    daysDisabled: expiresWithinDaysDisabled,
} = exclusivePair(
    () =>
        filters.expiration_date_from !== '' ||
        filters.expiration_date_to !== '',
    () => filters.expires_within_days !== '',
);

const filtersOpen = ref(false);

const activeFilterCount = computed(() =>
    countActive([
        filters.product_id.length > 0,
        filters.row_id !== '',
        filters.column_number !== '',
        filters.user_id.length > 0,
        filters.action.length > 0,
        filters.date_from !== '' ||
            filters.date_to !== '' ||
            filters.created_within_days !== '',
        filters.expiration_date_from !== '' ||
            filters.expiration_date_to !== '' ||
            filters.expires_within_days !== '',
        filters.flagged,
    ]),
);

const cellColumnFiltered = computed(
    () => filters.row_id !== '' || filters.column_number !== '',
);
const productColumnFiltered = computed(() => filters.product_id.length > 0);
const actionColumnFiltered = computed(() => filters.action.length > 0);
const doneByColumnFiltered = computed(() => filters.user_id.length > 0);
const whenColumnFiltered = computed(
    () =>
        filters.date_from !== '' ||
        filters.date_to !== '' ||
        filters.created_within_days !== '',
);
const palletColumnFiltered = computed(
    () =>
        filters.pallet_id !== '' ||
        filters.expiration_date_from !== '' ||
        filters.expiration_date_to !== '' ||
        filters.expires_within_days !== '',
);

/**
 * `flagged` is only ever included when checked, mirroring the `expired`
 * filter on the products page — keeps the query string clean instead of
 * always carrying a literal `flagged=false`.
 */
function filterQuery(): Record<string, FormDataConvertible> {
    const { flagged, ...rest } = filters;

    return flagged ? { ...rest, flagged: true } : rest;
}

function applyFilters(): void {
    router.get(cellLogsIndex().url, filterQuery(), {
        preserveState: true,
        replace: true,
    });
    filtersOpen.value = false;
}

const { openFilterKey } = useColumnFilterPopover(
    filters,
    filtersOpen,
    applyFilters,
);

function clearFilters(): void {
    filters.product_id = [];
    filters.pallet_id = '';
    filters.row_id = '';
    filters.column_number = '';
    filters.user_id = [];
    filters.action = [];
    filters.date_from = '';
    filters.date_to = '';
    filters.created_within_days = '';
    filters.expiration_date_from = '';
    filters.expiration_date_to = '';
    filters.expires_within_days = '';
    filters.sort_by = '';
    filters.sort_direction = '';
    filters.flagged = false;
    router.get(cellLogsIndex().url, {}, { preserveState: true, replace: true });
}

function onSort(key: string): void {
    toggleSort(filters, key);
    applyFilters();
}

function viewPalletHistory(palletId: number): void {
    router.get(
        cellLogsIndex().url,
        { pallet_id: palletId },
        { preserveState: true, replace: true },
    );
}

const displayLogs = computed(() => mergeTransferPairs(props.logs.data));
</script>

<template>
    <Head :title="t('cellLog.title')" />

    <AdminLayout>
        <PageHeader :title="t('cellLog.title')">
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="filtersOpen = true"
            >
                <SlidersHorizontal class="h-4 w-4" />
                {{ t('cellLog.filters.title') }}
                <span v-if="activeFilterCount > 0" :class="countBadgeClass">
                    {{ activeFilterCount }}
                </span>
            </button>
        </PageHeader>

        <FilterDialog
            v-model:open="filtersOpen"
            :title="t('cellLog.filters.title')"
            :close-label="t('cellLog.filters.close')"
        >
            <form class="space-y-6" @submit.prevent="applyFilters">
                <div>
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellLog.filters.sections.location') }}
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
                        {{ t('cellLog.filters.sections.activity') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <FilterProductSelect
                            id="filter-product"
                            v-model="filters.product_id"
                            :label="t('cellLog.filters.product')"
                            :all-label="t('cellLog.filters.all')"
                            :selected-count-label="selectedCountLabel"
                            :selected="filterOptions.products"
                        />

                        <CellLogActivityFilterFields
                            id-prefix="filter"
                            v-model:action="filters.action"
                            v-model:user-id="filters.user_id"
                            :actions="filterOptions.actions"
                            :users="filterOptions.users"
                        />
                    </div>

                    <FilterCheckbox
                        id="filter-flagged"
                        v-model="filters.flagged"
                        class="mt-4"
                        :label="t('cellLog.filters.flaggedOnly')"
                    />
                </div>

                <div
                    class="border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellLog.filters.sections.date') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <DateRangeFilterFields
                            from-id="filter-date-from"
                            to-id="filter-date-to"
                            within-days-id="filter-created-within-days"
                            :from-label="t('cellLog.filters.from')"
                            :to-label="t('cellLog.filters.to')"
                            :within-days-label="t('cellLog.filters.withinDays')"
                            v-model:from="filters.date_from"
                            v-model:to="filters.date_to"
                            v-model:within-days="filters.created_within_days"
                            :range-disabled="dateRangeDisabled"
                            :days-disabled="createdWithinDaysDisabled"
                        />
                    </div>
                </div>

                <div
                    class="border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellLog.filters.sections.expiration') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <DateRangeFilterFields
                            from-id="filter-expiration-date-from"
                            to-id="filter-expiration-date-to"
                            within-days-id="filter-expires-within-days"
                            :from-label="t('cellLog.filters.expirationFrom')"
                            :to-label="t('cellLog.filters.expirationTo')"
                            :within-days-label="
                                t('cellLog.filters.expiresWithinDays')
                            "
                            v-model:from="filters.expiration_date_from"
                            v-model:to="filters.expiration_date_to"
                            v-model:within-days="filters.expires_within_days"
                            :range-disabled="expirationRangeDisabled"
                            :days-disabled="expiresWithinDaysDisabled"
                        />
                    </div>
                </div>

                <div
                    class="flex items-center gap-2 border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <button type="submit" :class="filterApplyButtonClass">
                        <Check class="h-4 w-4 shrink-0" />
                        {{ t('cellLog.filters.apply') }}
                    </button>
                    <button
                        type="button"
                        :class="filterClearButtonClass"
                        @click="clearFilters"
                    >
                        <X class="h-4 w-4 shrink-0" />
                        {{ t('cellLog.filters.clear') }}
                    </button>
                </div>
            </form>
        </FilterDialog>

        <div
            v-if="filters.pallet_id"
            class="mb-4 flex items-center justify-between rounded-md bg-blue-50 px-4 py-2 text-sm text-blue-800 dark:bg-blue-950 dark:text-blue-200"
        >
            <span>{{
                t('cellLog.filters.palletHistory', { id: filters.pallet_id })
            }}</span>
            <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-1 font-medium hover:underline"
                @click="clearFilters"
            >
                <X class="h-3.5 w-3.5 shrink-0" />
                {{ t('cellLog.filters.clear') }}
            </button>
        </div>

        <DataTable
            v-model:open-filter-key="openFilterKey"
            :columns="[
                {
                    label: t('cellLog.columns.cell'),
                    filtered: cellColumnFiltered,
                    filterKey: 'location',
                },
                {
                    label: t('cellLog.columns.action'),
                    filtered: actionColumnFiltered,
                    filterKey: 'action',
                },
                {
                    label: t('cellLog.columns.product'),
                    filtered: productColumnFiltered,
                    filterKey: 'product',
                },
                {
                    label: t('cellLog.columns.pallet'),
                    sortKey: 'expiration_date',
                    filtered: palletColumnFiltered,
                    filterKey: 'expiration',
                },
                t('cellLog.columns.note'),
                {
                    label: t('cellLog.columns.doneBy'),
                    filtered: doneByColumnFiltered,
                    filterKey: 'doneBy',
                },
                {
                    label: t('cellLog.columns.when'),
                    sortKey: 'created_at',
                    filtered: whenColumnFiltered,
                    filterKey: 'when',
                },
            ]"
            :rows="displayLogs"
            :empty-message="t('cellLog.empty')"
            :sort="
                filters.sort_by
                    ? {
                          by: filters.sort_by,
                          direction:
                              filters.sort_direction === 'asc' ? 'asc' : 'desc',
                      }
                    : undefined
            "
            @sort="onSort"
        >
            <template #column-filter="{ filterKey: key }">
                <LocationFilterFields
                    v-if="key === 'location'"
                    id-prefix="popover-filter"
                    class="space-y-3"
                    v-model:row-id="filters.row_id"
                    v-model:column-number="filters.column_number"
                    :rows="filterOptions.rows"
                    :max-column-number="filterOptions.maxColumnNumber"
                />

                <div v-else-if="key === 'product'">
                    <FilterProductSelect
                        id="popover-filter-product"
                        v-model="filters.product_id"
                        :label="t('cellLog.filters.product')"
                        :all-label="t('cellLog.filters.all')"
                        :selected-count-label="selectedCountLabel"
                        :selected="filterOptions.products"
                    />
                </div>

                <div v-else-if="key === 'action'">
                    <FilterMultiSelect
                        id="popover-filter-action"
                        v-model="filters.action"
                        :label="t('cellLog.filters.statusChange')"
                        :all-label="t('cellLog.filters.all')"
                        :selected-count-label="selectedCountLabel"
                        :options="
                            filterOptions.actions.map((action) => ({
                                value: action,
                                label: cellLogActionLabel(action),
                            }))
                        "
                    />
                </div>

                <div v-else-if="key === 'doneBy'">
                    <FilterMultiSelect
                        id="popover-filter-user"
                        v-model="filters.user_id"
                        :label="t('cellLog.filters.doneBy')"
                        :all-label="t('cellLog.filters.all')"
                        :selected-count-label="selectedCountLabel"
                        :options="
                            filterOptions.users.map((user) => ({
                                value: user.id.toString(),
                                label: user.name,
                            }))
                        "
                    />
                </div>

                <div v-else-if="key === 'when'" class="space-y-3">
                    <DateRangeFilterFields
                        from-id="popover-filter-date-from"
                        to-id="popover-filter-date-to"
                        within-days-id="popover-filter-created-within-days"
                        :from-label="t('cellLog.filters.from')"
                        :to-label="t('cellLog.filters.to')"
                        :within-days-label="t('cellLog.filters.withinDays')"
                        v-model:from="filters.date_from"
                        v-model:to="filters.date_to"
                        v-model:within-days="filters.created_within_days"
                        :range-disabled="dateRangeDisabled"
                        :days-disabled="createdWithinDaysDisabled"
                    />
                </div>

                <div v-else-if="key === 'expiration'" class="space-y-3">
                    <DateRangeFilterFields
                        from-id="popover-filter-expiration-date-from"
                        to-id="popover-filter-expiration-date-to"
                        within-days-id="popover-filter-expires-within-days"
                        :from-label="t('cellLog.filters.expirationFrom')"
                        :to-label="t('cellLog.filters.expirationTo')"
                        :within-days-label="
                            t('cellLog.filters.expiresWithinDays')
                        "
                        v-model:from="filters.expiration_date_from"
                        v-model:to="filters.expiration_date_to"
                        v-model:within-days="filters.expires_within_days"
                        :range-disabled="expirationRangeDisabled"
                        :days-disabled="expiresWithinDaysDisabled"
                    />
                </div>
            </template>

            <template #row="{ row: log }">
                <template
                    v-for="(pair, pairIndex) in [transferPair(log)]"
                    :key="pairIndex"
                >
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-1">
                            <TableLink
                                :href="
                                    showRow({ letter: pair.from.row_letter })
                                "
                            >
                                {{
                                    formatSlot(
                                        pair.from.row_letter,
                                        pair.from.cell_number,
                                        pair.from.flat_number,
                                    )
                                }}
                            </TableLink>
                            <template v-if="pair.to">
                                <ArrowRight
                                    class="h-3 w-3 shrink-0 text-gray-400 rtl:rotate-180"
                                />
                                <TableLink
                                    :href="
                                        showRow({ letter: pair.to.row_letter })
                                    "
                                >
                                    {{
                                        formatSlot(
                                            pair.to.row_letter,
                                            pair.to.cell_number,
                                            pair.to.flat_number,
                                        )
                                    }}
                                </TableLink>
                            </template>
                        </div>
                    </td>
                </template>
                <td class="px-4 py-2">
                    <div
                        class="flex items-center gap-1 font-medium text-gray-900 dark:text-neutral-100"
                    >
                        {{
                            log.pairedIn
                                ? t('cellLog.actions.transferred')
                                : cellLogActionLabel(log.action)
                        }}
                        <TriangleAlert
                            v-if="log.flagged"
                            class="h-3.5 w-3.5 shrink-0 text-amber-500"
                        />
                    </div>
                    <div class="text-xs text-gray-500 dark:text-neutral-400">
                        {{ cellStateLabel(log.from_state) }}
                        <template v-if="!log.pairedIn">
                            <span class="inline-block rtl:rotate-180">→</span>
                            {{ cellStateLabel(log.to_state) }}
                        </template>
                    </div>
                    <CellLogFlagBadges :flags="log.flags" />
                    <button
                        v-if="hasUnacknowledgedFlags(log)"
                        type="button"
                        class="mt-1 inline-flex cursor-pointer items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400"
                        @click="acknowledgeFlags(log)"
                    >
                        <Check class="h-3 w-3 shrink-0" />
                        {{ t('cellLog.flags.acknowledge') }}
                    </button>
                </td>
                <td class="px-4 py-2">
                    <div
                        v-if="log.product"
                        class="flex items-center gap-2 font-medium text-gray-900 dark:text-neutral-100"
                    >
                        <img
                            v-if="log.product.image_url"
                            :src="log.product.image_url"
                            :alt="log.product.name"
                            class="h-8 w-8 shrink-0 rounded object-cover"
                        />
                        {{ log.product.name }}
                    </div>
                    <span v-else class="text-gray-400 dark:text-neutral-600"
                        >—</span
                    >
                </td>
                <td class="px-4 py-2">
                    <template v-if="log.pallet">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 font-medium text-blue-600 hover:underline dark:text-blue-400"
                            :title="t('cellLog.columns.viewPalletHistory')"
                            @click="viewPalletHistory(log.pallet.id)"
                        >
                            #{{ log.pallet.id }}
                            <History class="h-3 w-3 shrink-0" />
                        </button>
                        <div
                            v-if="log.boxes_count !== null"
                            class="text-xs text-gray-500 dark:text-neutral-400"
                        >
                            {{ t('cellLog.columns.boxes') }}:
                            {{ log.boxes_count }}
                        </div>
                        <div
                            v-if="log.pallet.expiration_date"
                            class="text-xs text-gray-500 dark:text-neutral-400"
                        >
                            {{ t('cellLog.columns.expires') }}
                            {{ formatDate(log.pallet.expiration_date) }}
                        </div>
                    </template>
                    <span v-else class="text-gray-400 dark:text-neutral-600"
                        >—</span
                    >
                </td>
                <td
                    class="max-w-xs truncate px-4 py-2 text-gray-500 dark:text-neutral-400"
                    :title="log.note ?? undefined"
                >
                    {{ log.note ?? '—' }}
                </td>
                <td class="px-4 py-2">
                    <TableLink :href="editUser({ id: log.user.id })">
                        {{ log.user.name }}
                    </TableLink>
                </td>
                <td class="px-4 py-2">
                    <div
                        class="font-medium text-gray-900 dark:text-neutral-100"
                    >
                        {{ formatDateTime(log.created_at) }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-neutral-400">
                        {{ formatDuration(log.duration_seconds) }}
                    </div>
                    <div
                        v-if="!log.next_log_at"
                        class="flex items-center gap-1 text-xs text-gray-400 dark:text-neutral-600"
                    >
                        <Clock class="h-3 w-3 shrink-0" />
                        {{ t('cellLog.columns.ongoing') }}
                    </div>
                </td>
            </template>
        </DataTable>

        <Pagination :links="logs.meta.links" />
    </AdminLayout>
</template>
