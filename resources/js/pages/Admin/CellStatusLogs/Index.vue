<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Clock,
    History,
    SlidersHorizontal,
    X,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { show as showRow } from '@/actions/App/Http/Controllers/Admin/RowController';
import { edit as editUser } from '@/actions/App/Http/Controllers/Admin/UserController';
import DataTable from '@/components/DataTable.vue';
import FilterDateField from '@/components/FilterDateField.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import FilterNumberField from '@/components/FilterNumberField.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import Pagination from '@/components/Pagination.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDate, formatDateTime, formatDuration } from '@/lib/date';
import {
    columnNumberOptions,
    countBadgeClass,
    filterClearButtonClass,
    filterSectionHeadingClass as sectionHeadingClass,
    filterTriggerButtonClass,
    selectedCountLabel,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import type {
    CellSlotLocation,
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

function actionLabel(action: CellStatusLog['action']): string {
    return t(`cellLog.actions.${action}`);
}

function stateLabel(state: CellStatusLog['from_state']): string {
    return t(`cellLog.states.${state}`);
}

const columnNumbers = columnNumberOptions(props.filterOptions.maxColumnNumber);

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
});

const dateRangeDisabled = computed(() => filters.created_within_days !== '');
const createdWithinDaysDisabled = computed(
    () => filters.date_from !== '' || filters.date_to !== '',
);

const expirationRangeDisabled = computed(
    () => filters.expires_within_days !== '',
);
const expiresWithinDaysDisabled = computed(
    () =>
        filters.expiration_date_from !== '' ||
        filters.expiration_date_to !== '',
);

const filtersOpen = ref(false);

const activeFilterCount = computed(
    () =>
        [
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
        ].filter(Boolean).length,
);

function applyFilters(): void {
    router.get(cellLogsIndex().url, filters, {
        preserveState: true,
        replace: true,
    });
    filtersOpen.value = false;
}

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
    router.get(cellLogsIndex().url, {}, { preserveState: true, replace: true });
}

function toggleSort(key: string): void {
    filters.sort_direction =
        filters.sort_by === key && filters.sort_direction === 'asc'
            ? 'desc'
            : 'asc';
    filters.sort_by = key;
    applyFilters();
}

function viewPalletHistory(palletId: number): void {
    router.get(
        cellLogsIndex().url,
        { pallet_id: palletId },
        { preserveState: true, replace: true },
    );
}

function transferPair(log: CellStatusLog): {
    from: CellSlotLocation;
    to: CellSlotLocation | null;
} {
    if (log.action === 'transferred_in' && log.related_cell) {
        return { from: log.related_cell, to: log.cell };
    }

    return { from: log.cell, to: log.related_cell };
}

type DisplayCellStatusLog = CellStatusLog & { pairedIn?: CellStatusLog };

function sameCellLocation(
    a: CellSlotLocation | null,
    b: CellSlotLocation | null,
): boolean {
    return (
        a !== null &&
        b !== null &&
        a.row_letter === b.row_letter &&
        a.cell_number === b.cell_number &&
        a.flat_number === b.flat_number
    );
}

function isTransferPair(out: CellStatusLog, incoming: CellStatusLog): boolean {
    return (
        incoming.action === 'transferred_in' &&
        out.pallet !== null &&
        incoming.pallet !== null &&
        out.pallet.id === incoming.pallet.id &&
        out.created_at === incoming.created_at &&
        sameCellLocation(out.cell, incoming.related_cell) &&
        sameCellLocation(out.related_cell, incoming.cell)
    );
}

/**
 * A transfer writes a `transferred_out` row (on the source cell) and a
 * `transferred_in` row (on the destination cell) in the same transaction.
 * Every column except the action label ends up identical between the two, so
 * when both sides land on the current page, merge them into a single row
 * keyed on the `transferred_out` side. Filtering to one cell, one pallet's
 * history split across a page boundary, or a single action naturally yields
 * only one side — those fall through unmerged.
 */
const displayLogs = computed<DisplayCellStatusLog[]>(() => {
    const logs = props.logs.data;
    const pairedInByOutId = new Map<number, CellStatusLog>();
    const pairedInIds = new Set<number>();

    for (const log of logs) {
        if (log.action !== 'transferred_out') {
            continue;
        }

        const incoming = logs.find(
            (candidate) =>
                !pairedInIds.has(candidate.id) &&
                isTransferPair(log, candidate),
        );

        if (incoming) {
            pairedInByOutId.set(log.id, incoming);
            pairedInIds.add(incoming.id);
        }
    }

    return logs
        .filter((log) => !pairedInIds.has(log.id))
        .map((log) => {
            const pairedIn = pairedInByOutId.get(log.id);

            return pairedIn ? { ...log, pairedIn } : log;
        });
});
</script>

<template>
    <Head :title="t('cellLog.title')" />

    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('cellLog.title') }}</h1>
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
        </div>

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
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FilterSelect
                            id="filter-row"
                            v-model="filters.row_id"
                            :label="t('cellLog.filters.row')"
                            :all-label="t('cellLog.filters.all')"
                            :options="
                                filterOptions.rows.map((row) => ({
                                    value: row.id,
                                    label: row.letter,
                                }))
                            "
                        />

                        <FilterSelect
                            id="filter-column"
                            v-model="filters.column_number"
                            :label="t('cellLog.filters.column')"
                            :all-label="t('cellLog.filters.all')"
                            :options="
                                columnNumbers.map((columnNumber) => ({
                                    value: columnNumber,
                                    label: String(columnNumber),
                                }))
                            "
                        />
                    </div>
                </div>

                <div
                    class="border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellLog.filters.sections.activity') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <FilterMultiSelect
                            id="filter-product"
                            v-model="filters.product_id"
                            :label="t('cellLog.filters.product')"
                            :all-label="t('cellLog.filters.all')"
                            :selected-count-label="selectedCountLabel"
                            :options="
                                filterOptions.products.map((product) => ({
                                    value: product.id.toString(),
                                    label: product.name,
                                }))
                            "
                        />

                        <FilterMultiSelect
                            id="filter-action"
                            v-model="filters.action"
                            :label="t('cellLog.filters.statusChange')"
                            :all-label="t('cellLog.filters.all')"
                            :selected-count-label="selectedCountLabel"
                            :options="
                                filterOptions.actions.map((action) => ({
                                    value: action,
                                    label: actionLabel(action),
                                }))
                            "
                        />

                        <FilterMultiSelect
                            id="filter-user"
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
                </div>

                <div
                    class="border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <h3 :class="sectionHeadingClass">
                        {{ t('cellLog.filters.sections.date') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <FilterDateField
                            id="filter-date-from"
                            v-model="filters.date_from"
                            :label="t('cellLog.filters.from')"
                            :disabled="dateRangeDisabled"
                        />

                        <FilterDateField
                            id="filter-date-to"
                            v-model="filters.date_to"
                            :label="t('cellLog.filters.to')"
                            :disabled="dateRangeDisabled"
                        />

                        <FilterNumberField
                            id="filter-created-within-days"
                            v-model="filters.created_within_days"
                            :label="t('cellLog.filters.withinDays')"
                            :disabled="createdWithinDaysDisabled"
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
                        <FilterDateField
                            id="filter-expiration-date-from"
                            v-model="filters.expiration_date_from"
                            :label="t('cellLog.filters.expirationFrom')"
                            :disabled="expirationRangeDisabled"
                        />

                        <FilterDateField
                            id="filter-expiration-date-to"
                            v-model="filters.expiration_date_to"
                            :label="t('cellLog.filters.expirationTo')"
                            :disabled="expirationRangeDisabled"
                        />

                        <FilterNumberField
                            id="filter-expires-within-days"
                            v-model="filters.expires_within_days"
                            :label="t('cellLog.filters.expiresWithinDays')"
                            :disabled="expiresWithinDaysDisabled"
                        />
                    </div>
                </div>

                <div
                    class="flex items-center gap-2 border-t border-gray-200 pt-6 dark:border-neutral-800"
                >
                    <button
                        type="submit"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-neutral-200"
                    >
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
            :columns="[
                t('cellLog.columns.cell'),
                t('cellLog.columns.action'),
                t('cellLog.columns.product'),
                {
                    label: t('cellLog.columns.pallet'),
                    sortKey: 'expiration_date',
                },
                t('cellLog.columns.note'),
                t('cellLog.columns.doneBy'),
                { label: t('cellLog.columns.when'), sortKey: 'created_at' },
                t('cellLog.columns.duration'),
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
            @sort="toggleSort"
        >
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
                        class="font-medium text-gray-900 dark:text-neutral-100"
                    >
                        {{
                            log.pairedIn
                                ? t('cellLog.actions.transferred')
                                : actionLabel(log.action)
                        }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-neutral-400">
                        {{ stateLabel(log.from_state) }}
                        <template v-if="!log.pairedIn">
                            <span class="inline-block rtl:rotate-180">→</span>
                            {{ stateLabel(log.to_state) }}
                        </template>
                    </div>
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
                <td class="px-4 py-2 text-gray-500 dark:text-neutral-400">
                    {{ formatDateTime(log.created_at) }}
                </td>
                <td class="px-4 py-2">
                    <div
                        class="font-medium text-gray-900 dark:text-neutral-100"
                    >
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
