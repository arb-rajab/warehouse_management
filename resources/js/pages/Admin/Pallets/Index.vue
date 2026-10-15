<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Map } from '@lucide/vue';
import { computed } from 'vue';
import { index as palletsIndex } from '@/actions/App/Http/Controllers/Admin/PalletController';
import { index as warehouseMapIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import MatchingPalletsTable from '@/components/MatchingPalletsTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { cellStateLabel } from '@/lib/cellStateColor';
import { filterTriggerButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
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
 * The active filter, minus `per_page` — kept across a page-size change.
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
            <Link :href="warehouseMapIndex()" :class="filterTriggerButtonClass">
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

        <MatchingPalletsTable
            :pallets="pallets.data"
            :empty-message="t('pallets.empty')"
        />

        <Pagination
            :links="pallets.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </AdminLayout>
</template>
