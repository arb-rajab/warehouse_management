<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Map } from '@lucide/vue';
import { computed } from 'vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { index as palletsIndex } from '@/actions/App/Http/Controllers/Admin/PalletController';
import { show as showRow } from '@/actions/App/Http/Controllers/Admin/RowController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { cellStateLabel } from '@/lib/cellStateColor';
import { formatDate } from '@/lib/date';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import { productName } from '@/lib/productName';
import type {
    MatchingPallet,
    MatchingPalletFilters,
    Paginated,
    ProductFilterOptions,
} from '@/types/admin';
import type { QueryParams } from '@/wayfinder';

const props = defineProps<{
    pallets: Paginated<MatchingPallet>;
    filters: MatchingPalletFilters;
    filterOptions: ProductFilterOptions;
}>();

/**
 * The active filter, minus `per_page` (a page-size choice the cell map
 * doesn't understand) — reused both for the "view on map" button's query and
 * for the human-readable chips above the table.
 */
const filterQuery = computed<QueryParams>(() => {
    const query: QueryParams = { ...props.filters };
    delete query.per_page;

    return query;
});

const filterChips = computed<string[]>(() => {
    const chips: string[] = [];

    if (props.filters.state) {
        chips.push(cellStateLabel(props.filters.state));
    }

    if (props.filters.expired) {
        chips.push(t('dashboard.expiring.expired'));
    }

    if (props.filters.expires_within_days !== undefined) {
        chips.push(
            t('dashboard.expiring.soon', {
                days: props.filters.expires_within_days,
            }),
        );
    }

    if (props.filters.stale_after_days !== undefined) {
        chips.push(
            t('dashboard.stale.soon', { days: props.filters.stale_after_days }),
        );
    }

    if (props.filterOptions.products.length > 0) {
        chips.push(
            props.filterOptions.products
                .map((product) => productName(product.name, product.ar_name))
                .join(', '),
        );
    }

    return chips;
});

function onPerPageChange(perPage: number): void {
    router.get(
        palletsIndex().url,
        { ...filterQuery.value, per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('pallets.title')" />

    <AdminLayout>
        <PageHeader :title="t('pallets.title')">
            <Link
                :href="cellsIndex({ query: filterQuery })"
                class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
            >
                <Map class="h-4 w-4" />
                {{ t('pallets.viewOnMap') }}
            </Link>
        </PageHeader>

        <div
            v-if="filterChips.length > 0"
            class="mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-neutral-400"
        >
            <span>{{ t('pallets.filteredBy') }}</span>
            <span
                v-for="chip in filterChips"
                :key="chip"
                class="rounded-full bg-gray-100 px-2 py-0.5 dark:bg-neutral-800"
            >
                {{ chip }}
            </span>
        </div>

        <DataTable
            :columns="[
                t('pallets.columns.product'),
                t('pallets.columns.state'),
                t('pallets.columns.location'),
                t('pallets.columns.expiration'),
                t('pallets.columns.added'),
                t('pallets.columns.remainingBoxes'),
            ]"
            :rows="pallets.data"
            :empty-message="t('pallets.empty')"
        >
            <template #row="{ row: pallet }">
                <td class="px-4 py-2">
                    <div
                        class="flex items-center gap-2 font-medium text-gray-900 dark:text-neutral-100"
                    >
                        <img
                            v-if="pallet.product.image_url"
                            :src="pallet.product.image_url"
                            :alt="
                                productName(
                                    pallet.product.name,
                                    pallet.product.ar_name,
                                )
                            "
                            class="h-8 w-8 shrink-0 rounded object-cover"
                        />
                        {{
                            productName(
                                pallet.product.name,
                                pallet.product.ar_name,
                            )
                        }}
                    </div>
                </td>
                <td class="px-4 py-2">
                    {{ cellStateLabel(pallet.state) }}
                </td>
                <td class="px-4 py-2">
                    <TableLink
                        :href="
                            showRow(
                                { letter: pallet.location.row_letter },
                                {
                                    query: {
                                        cell_number:
                                            pallet.location.cell_number,
                                        flat_number:
                                            pallet.location.flat_number,
                                    },
                                },
                            )
                        "
                    >
                        {{
                            formatSlot(
                                pallet.location.row_letter,
                                pallet.location.cell_number,
                                pallet.location.flat_number,
                            )
                        }}
                    </TableLink>
                </td>
                <td class="px-4 py-2">
                    <span v-if="pallet.expiration_date">
                        {{ formatDate(pallet.expiration_date) }}
                    </span>
                    <span v-else class="text-gray-400 dark:text-neutral-600"
                        >—</span
                    >
                </td>
                <td class="px-4 py-2">
                    <span v-if="pallet.added_at">
                        {{ formatDate(pallet.added_at) }}
                    </span>
                    <span v-else class="text-gray-400 dark:text-neutral-600"
                        >—</span
                    >
                </td>
                <td class="px-4 py-2">{{ pallet.remaining_boxes }}</td>
            </template>
        </DataTable>

        <Pagination
            :links="pallets.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </AdminLayout>
</template>
