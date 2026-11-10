<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    CalendarPlus,
    CalendarX,
    Check,
    CircleDashed,
    Clock,
    PackageOpen,
    PackageX,
    SlidersHorizontal,
    Truck,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { index as dashboardIndex } from '@/actions/App/Http/Controllers/Admin/DashboardController';
import { show as showHelp } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as palletsIndex } from '@/actions/App/Http/Controllers/Admin/PalletController';
import { index as productsIndex } from '@/actions/App/Http/Controllers/Admin/ProductController';
import DashboardStatTile from '@/components/DashboardStatTile.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterNumberField from '@/components/FilterNumberField.vue';
import FilterProductSelect from '@/components/FilterProductSelect.vue';
import FilterWarehouseSelect from '@/components/FilterWarehouseSelect.vue';
import HelpLink from '@/components/HelpLink.vue';
import MatchingPalletsTable from '@/components/MatchingPalletsTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { CELL_STATE_COLOR } from '@/lib/cellStateColor';
import {
    filterApplyButtonClass,
    filterSectionHeadingClass as sectionHeadingClass,
    filterTriggerButtonClass,
    selectedCountLabel,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    MatchingPallet,
    ProductFilterOptions,
    WarehouseFilterOptions,
} from '@/types/admin';

interface ExpiringMonthWindow {
    months: number;
    days: number;
    until: string;
    count: number;
}

interface ExpiringDayWindow {
    days: number;
    until: string;
    count: number;
}

interface StaleDayWindow {
    days: number;
    count: number;
}

interface LowStockStats {
    count: number;
}

interface InactiveInWarehouseStats {
    count: number;
}

interface UnplacedPalletsStats {
    count: number;
}

const props = defineProps<{
    stats: {
        occupancy: { empty: number; full: number; opened: number };
        expiring: {
            expired: number;
            windows: ExpiringMonthWindow[];
            custom: ExpiringDayWindow;
        };
        stale: StaleDayWindow;
        unplaced_pallets: UnplacedPalletsStats;
        low_stock: LowStockStats;
        inactive_in_warehouse: InactiveInWarehouseStats;
        activity_today: {
            stored: number;
            opened: number;
            emptied: number;
            transferred: number;
        };
        activity_week: {
            stored: number;
            opened: number;
            emptied: number;
            transferred: number;
        };
    };
    today: string;
    weekStart: string;
    productPallets: MatchingPallet[];
    filters: { product_id: number[] | null; warehouse_ids: number[] | null };
    filterOptions: ProductFilterOptions & WarehouseFilterOptions;
}>();

const tileGridClass = 'grid grid-cols-2 gap-4 sm:grid-cols-3';

const productIdStrings = computed(() =>
    (props.filters.product_id ?? []).map(String),
);

const warehouseIdStrings = computed(() =>
    (props.filters.warehouse_ids ?? []).map(String),
);

const productQuery = computed(() =>
    props.filters.product_id && props.filters.product_id.length > 0
        ? { product_id: props.filters.product_id }
        : {},
);

/**
 * The warehouse filter, carried by every tile link whose page filters by
 * warehouse (pallets, activity log). The products page has no warehouse
 * filter, so its links leave it out.
 */
const warehouseQuery = computed(() =>
    props.filters.warehouse_ids && props.filters.warehouse_ids.length > 0
        ? { warehouse_ids: props.filters.warehouse_ids }
        : {},
);

const ACTIVITY_ACTIONS: {
    key: 'stored' | 'opened' | 'emptied' | 'transferred';
    icon: Component;
    filterAction: string[];
}[] = [
    { key: 'stored', icon: CalendarPlus, filterAction: ['stored'] },
    { key: 'opened', icon: PackageOpen, filterAction: ['opened'] },
    { key: 'emptied', icon: CircleDashed, filterAction: ['emptied'] },
    {
        key: 'transferred',
        icon: ArrowLeftRight,
        filterAction: ['transferred_out', 'transferred_in'],
    },
];

const customExpiringDaysDialogOpen = ref(false);
const customExpiringDaysDraft = ref(String(props.stats.expiring.custom.days));

function openCustomExpiringDaysDialog(): void {
    customExpiringDaysDraft.value = String(props.stats.expiring.custom.days);
    customExpiringDaysDialogOpen.value = true;
}

function submitCustomExpiringDays(): void {
    if (customExpiringDaysDraft.value === '') {
        return;
    }

    router.get(
        dashboardIndex().url,
        {
            product_id: props.filters.product_id ?? [],
            warehouse_ids: props.filters.warehouse_ids ?? [],
            expiring_days: Number(customExpiringDaysDraft.value),
            stale_days: props.stats.stale.days,
        },
        { preserveState: true, replace: true },
    );
    customExpiringDaysDialogOpen.value = false;
}

const customStaleDaysDialogOpen = ref(false);
const customStaleDaysDraft = ref(String(props.stats.stale.days));

function openCustomStaleDaysDialog(): void {
    customStaleDaysDraft.value = String(props.stats.stale.days);
    customStaleDaysDialogOpen.value = true;
}

function submitCustomStaleDays(): void {
    if (customStaleDaysDraft.value === '') {
        return;
    }

    router.get(
        dashboardIndex().url,
        {
            product_id: props.filters.product_id ?? [],
            warehouse_ids: props.filters.warehouse_ids ?? [],
            expiring_days: props.stats.expiring.custom.days,
            stale_days: Number(customStaleDaysDraft.value),
        },
        { preserveState: true, replace: true },
    );
    customStaleDaysDialogOpen.value = false;
}

function onProductIdsChange(ids: string[]): void {
    router.get(
        dashboardIndex().url,
        {
            product_id: ids,
            warehouse_ids: props.filters.warehouse_ids ?? [],
            expiring_days: props.stats.expiring.custom.days,
            stale_days: props.stats.stale.days,
        },
        { preserveState: true, replace: true },
    );
}

function onWarehouseIdsChange(ids: string[]): void {
    router.get(
        dashboardIndex().url,
        {
            product_id: props.filters.product_id ?? [],
            warehouse_ids: ids,
            expiring_days: props.stats.expiring.custom.days,
            stale_days: props.stats.stale.days,
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AdminLayout>
        <PageHeader :title="t('dashboard.title')">
            <div class="flex flex-wrap items-center gap-4">
                <HelpLink :href="showHelp('dashboard')" />
                <FilterProductSelect
                    id="dashboard-product"
                    :model-value="productIdStrings"
                    :label="t('cellLog.filters.product')"
                    :all-label="t('cellLog.filters.all')"
                    :selected-count-label="selectedCountLabel"
                    :selected="filterOptions.products"
                    @update:model-value="onProductIdsChange"
                />
                <FilterWarehouseSelect
                    id="dashboard-warehouse"
                    :model-value="warehouseIdStrings"
                    :label="t('warehouses.filters.label')"
                    :all-label="t('cellLog.filters.all')"
                    :selected-count-label="selectedCountLabel"
                    :selected="filterOptions.warehouses"
                    @update:model-value="onWarehouseIdsChange"
                />
            </div>
        </PageHeader>

        <section
            v-if="filters.product_id"
            id="dashboard-product-pallets"
            class="mb-8"
        >
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.productPallets.title') }}
            </h2>
            <MatchingPalletsTable
                :pallets="productPallets"
                :empty-message="t('dashboard.productPallets.empty')"
            />
            <Link
                :href="
                    palletsIndex({
                        query: { ...productQuery, ...warehouseQuery },
                    })
                "
                :class="['mt-3', filterTriggerButtonClass]"
            >
                {{ t('dashboard.productPallets.viewAll') }}
            </Link>
        </section>

        <section class="mb-8">
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.occupancy.title') }}
            </h2>
            <div :class="tileGridClass">
                <DashboardStatTile
                    :label="t('dashboard.occupancy.empty')"
                    :value="stats.occupancy.empty"
                    :href="
                        palletsIndex({
                            query: {
                                state: 'empty',
                                ...productQuery,
                                ...warehouseQuery,
                            },
                        })
                    "
                    :icon="CELL_STATE_COLOR.empty.icon"
                />
                <DashboardStatTile
                    :label="t('dashboard.occupancy.full')"
                    :value="stats.occupancy.full"
                    :href="
                        palletsIndex({
                            query: {
                                state: 'full',
                                ...productQuery,
                                ...warehouseQuery,
                            },
                        })
                    "
                    :icon="CELL_STATE_COLOR.full.icon"
                />
                <DashboardStatTile
                    :label="t('dashboard.occupancy.opened')"
                    :value="stats.occupancy.opened"
                    :href="
                        palletsIndex({
                            query: {
                                state: 'opened',
                                ...productQuery,
                                ...warehouseQuery,
                            },
                        })
                    "
                    :icon="CELL_STATE_COLOR.opened.icon"
                />
            </div>
        </section>

        <section class="mb-8">
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.unplaced.title') }}
            </h2>
            <div :class="tileGridClass">
                <DashboardStatTile
                    :label="t('dashboard.unplaced.count')"
                    :value="stats.unplaced_pallets.count"
                    :href="
                        palletsIndex({
                            query: {
                                unplaced: true,
                                ...productQuery,
                                ...warehouseQuery,
                            },
                        })
                    "
                    tone="warning"
                    :icon="Truck"
                />
            </div>
        </section>

        <section class="mb-8">
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.expiring.title') }}
            </h2>
            <div :class="tileGridClass">
                <DashboardStatTile
                    :label="t('dashboard.expiring.expired')"
                    :value="stats.expiring.expired"
                    :href="
                        palletsIndex({
                            query: {
                                expired: true,
                                ...productQuery,
                                ...warehouseQuery,
                            },
                        })
                    "
                    tone="danger"
                    :icon="CalendarX"
                />
                <DashboardStatTile
                    v-for="window in stats.expiring.windows"
                    :key="window.months"
                    :label="
                        t('dashboard.expiring.soonMonths', {
                            months: window.months,
                        })
                    "
                    :value="window.count"
                    :href="
                        palletsIndex({
                            query: {
                                expires_within_days: window.days,
                                ...productQuery,
                                ...warehouseQuery,
                            },
                        })
                    "
                    tone="warning"
                    :icon="CalendarX"
                />
                <DashboardStatTile
                    :label="
                        t('dashboard.expiring.soon', {
                            days: stats.expiring.custom.days,
                        })
                    "
                    :value="stats.expiring.custom.count"
                    :href="
                        palletsIndex({
                            query: {
                                expires_within_days: stats.expiring.custom.days,
                                ...productQuery,
                                ...warehouseQuery,
                            },
                        })
                    "
                    tone="warning"
                    :icon="CalendarX"
                >
                    <template #footer>
                        <button
                            type="button"
                            :class="['mt-2', filterTriggerButtonClass]"
                            @click="openCustomExpiringDaysDialog"
                        >
                            <SlidersHorizontal class="h-4 w-4" />
                            {{ t('expiringWindow.label') }}
                        </button>

                        <FilterDialog
                            v-model:open="customExpiringDaysDialogOpen"
                            :title="t('expiringWindow.label')"
                            :close-label="t('common.filters.close')"
                        >
                            <form
                                id="dashboard-custom-expiring-days-form"
                                class="space-y-4"
                                @submit.prevent="submitCustomExpiringDays"
                            >
                                <FilterNumberField
                                    id="dashboard-custom-expiring-days"
                                    v-model="customExpiringDaysDraft"
                                    :label="
                                        t('cellHighlight.expiresWithinDays')
                                    "
                                    :placeholder="
                                        t('expiringWindow.customPlaceholder')
                                    "
                                />
                            </form>

                            <template #footer>
                                <button
                                    type="submit"
                                    form="dashboard-custom-expiring-days-form"
                                    :class="filterApplyButtonClass"
                                >
                                    <Check class="h-4 w-4 shrink-0" />
                                    {{ t('expiringWindow.apply') }}
                                </button>
                            </template>
                        </FilterDialog>
                    </template>
                </DashboardStatTile>
            </div>
        </section>

        <section class="mb-8">
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.stale.title') }}
            </h2>
            <div :class="tileGridClass">
                <DashboardStatTile
                    :label="
                        t('dashboard.stale.soon', {
                            days: stats.stale.days,
                        })
                    "
                    :value="stats.stale.count"
                    :href="
                        palletsIndex({
                            query: {
                                stale_after_days: stats.stale.days,
                                ...productQuery,
                                ...warehouseQuery,
                            },
                        })
                    "
                    tone="warning"
                    :icon="Clock"
                >
                    <template #footer>
                        <button
                            type="button"
                            :class="['mt-2', filterTriggerButtonClass]"
                            @click="openCustomStaleDaysDialog"
                        >
                            <SlidersHorizontal class="h-4 w-4" />
                            {{ t('staleWindow.label') }}
                        </button>

                        <FilterDialog
                            v-model:open="customStaleDaysDialogOpen"
                            :title="t('staleWindow.label')"
                            :close-label="t('common.filters.close')"
                        >
                            <form
                                id="dashboard-custom-stale-days-form"
                                class="space-y-4"
                                @submit.prevent="submitCustomStaleDays"
                            >
                                <FilterNumberField
                                    id="dashboard-custom-stale-days"
                                    v-model="customStaleDaysDraft"
                                    :label="t('cellHighlight.staleAfterDays')"
                                    :placeholder="
                                        t('staleWindow.customPlaceholder')
                                    "
                                />
                            </form>

                            <template #footer>
                                <button
                                    type="submit"
                                    form="dashboard-custom-stale-days-form"
                                    :class="filterApplyButtonClass"
                                >
                                    <Check class="h-4 w-4 shrink-0" />
                                    {{ t('staleWindow.apply') }}
                                </button>
                            </template>
                        </FilterDialog>
                    </template>
                </DashboardStatTile>
            </div>
        </section>

        <section class="mb-8">
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.lowStock.title') }}
            </h2>
            <div :class="tileGridClass">
                <DashboardStatTile
                    :label="t('dashboard.lowStock.count')"
                    :value="stats.low_stock.count"
                    :href="
                        productsIndex({
                            query: { low_stock: true, ...productQuery },
                        })
                    "
                    tone="danger"
                    :icon="TriangleAlert"
                />
            </div>
        </section>

        <section class="mb-8">
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.inactiveInWarehouse.title') }}
            </h2>
            <div :class="tileGridClass">
                <DashboardStatTile
                    :label="t('dashboard.inactiveInWarehouse.count')"
                    :value="stats.inactive_in_warehouse.count"
                    :href="
                        productsIndex({
                            query: { inactive: true, ...productQuery },
                        })
                    "
                    tone="warning"
                    :icon="PackageX"
                />
            </div>
        </section>

        <section class="mb-8">
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.activityToday.title') }}
            </h2>
            <div :class="tileGridClass">
                <DashboardStatTile
                    v-for="action in ACTIVITY_ACTIONS"
                    :key="action.key"
                    :label="t(`cellLog.actions.${action.key}`)"
                    :value="stats.activity_today[action.key]"
                    :href="
                        cellLogsIndex({
                            query: {
                                action: action.filterAction,
                                date_from: today,
                                date_to: today,
                                ...productQuery,
                                ...warehouseQuery,
                            },
                        })
                    "
                    :icon="action.icon"
                />
            </div>
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.activityWeek.title') }}
            </h2>
            <div :class="tileGridClass">
                <DashboardStatTile
                    v-for="action in ACTIVITY_ACTIONS"
                    :key="action.key"
                    :label="t(`cellLog.actions.${action.key}`)"
                    :value="stats.activity_week[action.key]"
                    :href="
                        cellLogsIndex({
                            query: {
                                action: action.filterAction,
                                date_from: weekStart,
                                date_to: today,
                                ...productQuery,
                                ...warehouseQuery,
                            },
                        })
                    "
                    :icon="action.icon"
                />
            </div>
        </section>
    </AdminLayout>
</template>
