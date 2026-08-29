<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    CalendarPlus,
    CalendarX,
    Check,
    CircleDashed,
    PackageOpen,
    SlidersHorizontal,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { index as dashboardIndex } from '@/actions/App/Http/Controllers/Admin/DashboardController';
import DashboardStatTile from '@/components/DashboardStatTile.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterNumberField from '@/components/FilterNumberField.vue';
import FilterProductSelect from '@/components/FilterProductSelect.vue';
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
import type { ProductFilterOptions } from '@/types/admin';

interface ExpiringWindow {
    days: number;
    until: string;
    count: number;
}

const props = defineProps<{
    stats: {
        occupancy: { empty: number; full: number; opened: number };
        expiring: {
            expired: number;
            windows: ExpiringWindow[];
            custom: ExpiringWindow;
        };
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
    filters: { product_id: number[] | null };
    filterOptions: ProductFilterOptions;
}>();

const tileGridClass = 'grid grid-cols-2 gap-4 sm:grid-cols-3';

const productIdStrings = computed(() =>
    (props.filters.product_id ?? []).map(String),
);

const productQuery = computed(() =>
    props.filters.product_id && props.filters.product_id.length > 0
        ? { product_id: props.filters.product_id }
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
            expiring_days: Number(customExpiringDaysDraft.value),
        },
        { preserveState: true, replace: true },
    );
    customExpiringDaysDialogOpen.value = false;
}

function onProductIdsChange(ids: string[]): void {
    router.get(
        dashboardIndex().url,
        { product_id: ids, expiring_days: props.stats.expiring.custom.days },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AdminLayout>
        <PageHeader :title="t('dashboard.title')">
            <div class="flex items-center gap-4">
                <FilterProductSelect
                    id="dashboard-product"
                    :model-value="productIdStrings"
                    :label="t('cellLog.filters.product')"
                    :all-label="t('cellLog.filters.all')"
                    :selected-count-label="selectedCountLabel"
                    :selected="filterOptions.products"
                    @update:model-value="onProductIdsChange"
                />
            </div>
        </PageHeader>

        <section class="mb-8">
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.occupancy.title') }}
            </h2>
            <div :class="tileGridClass">
                <DashboardStatTile
                    :label="t('dashboard.occupancy.empty')"
                    :value="props.stats.occupancy.empty"
                    :href="cellsIndex().url"
                    :query="{ state: 'empty', ...productQuery }"
                    :icon="CELL_STATE_COLOR.empty.icon"
                />
                <DashboardStatTile
                    :label="t('dashboard.occupancy.full')"
                    :value="props.stats.occupancy.full"
                    :href="cellsIndex().url"
                    :query="{ state: 'full', ...productQuery }"
                    :icon="CELL_STATE_COLOR.full.icon"
                />
                <DashboardStatTile
                    :label="t('dashboard.occupancy.opened')"
                    :value="props.stats.occupancy.opened"
                    :href="cellsIndex().url"
                    :query="{ state: 'opened', ...productQuery }"
                    :icon="CELL_STATE_COLOR.opened.icon"
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
                    :value="props.stats.expiring.expired"
                    :href="cellsIndex().url"
                    :query="{
                        expired: true,
                        ...productQuery,
                    }"
                    tone="danger"
                    :icon="CalendarX"
                />
                <DashboardStatTile
                    v-for="window in props.stats.expiring.windows"
                    :key="window.days"
                    :label="t('dashboard.expiring.soon', { days: window.days })"
                    :value="window.count"
                    :href="cellsIndex().url"
                    :query="{
                        expires_within_days: window.days,
                        ...productQuery,
                    }"
                    tone="warning"
                    :icon="CalendarX"
                />
                <div
                    class="rounded-lg border border-gray-200 p-4 dark:border-neutral-800"
                >
                    <Link
                        :href="cellsIndex().url"
                        :data="{
                            expires_within_days:
                                props.stats.expiring.custom.days,
                            ...productQuery,
                        }"
                        method="get"
                        class="block"
                    >
                        <div
                            class="flex items-center gap-2 text-amber-600 dark:text-amber-400"
                        >
                            <CalendarX class="h-5 w-5 shrink-0" />
                            <div class="text-2xl font-semibold">
                                {{ props.stats.expiring.custom.count }}
                            </div>
                        </div>
                        <div
                            class="mt-1 text-sm text-gray-500 dark:text-neutral-400"
                        >
                            {{
                                t('dashboard.expiring.soon', {
                                    days: props.stats.expiring.custom.days,
                                })
                            }}
                        </div>
                    </Link>
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
                        :close-label="t('cellLog.filters.close')"
                    >
                        <form
                            class="space-y-4"
                            @submit.prevent="submitCustomExpiringDays"
                        >
                            <FilterNumberField
                                id="dashboard-custom-expiring-days"
                                v-model="customExpiringDaysDraft"
                                :label="t('cellHighlight.expiresWithinDays')"
                                :placeholder="
                                    t('expiringWindow.customPlaceholder')
                                "
                            />
                            <button
                                type="submit"
                                :class="filterApplyButtonClass"
                            >
                                <Check class="h-4 w-4 shrink-0" />
                                {{ t('expiringWindow.apply') }}
                            </button>
                        </form>
                    </FilterDialog>
                </div>
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
                    :value="props.stats.activity_today[action.key]"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: action.filterAction,
                        date_from: props.today,
                        date_to: props.today,
                        ...productQuery,
                    }"
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
                    :value="props.stats.activity_week[action.key]"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: action.filterAction,
                        date_from: props.weekStart,
                        date_to: props.today,
                        ...productQuery,
                    }"
                    :icon="action.icon"
                />
            </div>
        </section>
    </AdminLayout>
</template>
