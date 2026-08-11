<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowRight, History } from '@lucide/vue';
import { reactive } from 'vue';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { show as showRow } from '@/actions/App/Http/Controllers/Admin/RowController';
import { edit as editUser } from '@/actions/App/Http/Controllers/Admin/UserController';
import DataTable from '@/components/DataTable.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import Pagination from '@/components/Pagination.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDate, formatDateTime } from '@/lib/date';
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

const selectClass =
    'w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';
const labelClass = 'mb-1 block text-sm text-gray-700 dark:text-neutral-300';

const columnNumbers = Array.from(
    { length: props.filterOptions.maxColumnNumber },
    (_, i) => i + 1,
);

const filters = reactive({
    product_id: props.filters.product_id?.toString() ?? '',
    pallet_id: props.filters.pallet_id?.toString() ?? '',
    row_id: props.filters.row_id?.toString() ?? '',
    column_number: props.filters.column_number?.toString() ?? '',
    user_id: props.filters.user_id?.toString() ?? '',
    action: props.filters.action ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

function applyFilters(): void {
    router.get(cellLogsIndex().url, filters, {
        preserveState: true,
        replace: true,
    });
}

function clearFilters(): void {
    (Object.keys(filters) as (keyof typeof filters)[]).forEach((key) => {
        filters[key] = '';
    });
    router.get(cellLogsIndex().url, {}, { preserveState: true, replace: true });
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
</script>

<template>
    <Head :title="t('cellLog.title')" />

    <AdminLayout>
        <h1 class="mb-6 text-xl font-semibold">{{ t('cellLog.title') }}</h1>

        <form
            class="mb-6 grid grid-cols-2 gap-4 rounded-lg border border-gray-200 p-4 sm:grid-cols-3 lg:grid-cols-7 dark:border-neutral-800"
            @submit.prevent="applyFilters"
        >
            <FilterSelect
                id="filter-product"
                v-model="filters.product_id"
                :label="t('cellLog.filters.product')"
                :all-label="t('cellLog.filters.all')"
                :options="
                    filterOptions.products.map((product) => ({
                        value: product.id,
                        label: product.name,
                    }))
                "
            />

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

            <FilterSelect
                id="filter-user"
                v-model="filters.user_id"
                :label="t('cellLog.filters.doneBy')"
                :all-label="t('cellLog.filters.all')"
                :options="
                    filterOptions.users.map((user) => ({
                        value: user.id,
                        label: user.name,
                    }))
                "
            />

            <FilterSelect
                id="filter-action"
                v-model="filters.action"
                :label="t('cellLog.filters.statusChange')"
                :all-label="t('cellLog.filters.all')"
                :options="
                    filterOptions.actions.map((action) => ({
                        value: action,
                        label: actionLabel(action),
                    }))
                "
            />

            <div>
                <label :class="labelClass" for="filter-date-from">{{
                    t('cellLog.filters.from')
                }}</label>
                <input
                    id="filter-date-from"
                    v-model="filters.date_from"
                    type="date"
                    :class="[selectClass, 'dark:[color-scheme:dark]']"
                />
            </div>

            <div>
                <label :class="labelClass" for="filter-date-to">{{
                    t('cellLog.filters.to')
                }}</label>
                <input
                    id="filter-date-to"
                    v-model="filters.date_to"
                    type="date"
                    :class="[selectClass, 'dark:[color-scheme:dark]']"
                />
            </div>

            <div class="col-span-full flex items-end gap-2">
                <button
                    type="submit"
                    class="rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-neutral-200"
                >
                    {{ t('cellLog.filters.apply') }}
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-800"
                    @click="clearFilters"
                >
                    {{ t('cellLog.filters.clear') }}
                </button>
            </div>
        </form>

        <div
            v-if="filters.pallet_id"
            class="mb-4 flex items-center justify-between rounded-md bg-blue-50 px-4 py-2 text-sm text-blue-800 dark:bg-blue-950 dark:text-blue-200"
        >
            <span>{{
                t('cellLog.filters.palletHistory', { id: filters.pallet_id })
            }}</span>
            <button
                type="button"
                class="font-medium hover:underline"
                @click="clearFilters"
            >
                {{ t('cellLog.filters.clear') }}
            </button>
        </div>

        <DataTable
            :columns="[
                t('cellLog.columns.cell'),
                t('cellLog.columns.action'),
                t('cellLog.columns.product'),
                t('cellLog.columns.pallet'),
                t('cellLog.columns.note'),
                t('cellLog.columns.doneBy'),
                t('cellLog.columns.when'),
            ]"
            :rows="logs.data"
            :empty-message="t('cellLog.empty')"
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
                        {{ actionLabel(log.action) }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-neutral-400">
                        {{ stateLabel(log.from_state) }}
                        <span class="inline-block rtl:rotate-180">→</span>
                        {{ stateLabel(log.to_state) }}
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
                            class="inline-flex items-center gap-1 font-medium text-blue-600 hover:underline dark:text-blue-400"
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
            </template>
        </DataTable>

        <Pagination :links="logs.meta.links" />
    </AdminLayout>
</template>
