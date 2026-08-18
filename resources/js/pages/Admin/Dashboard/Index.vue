<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    CalendarPlus,
    CalendarX,
    CircleDashed,
    Inbox,
    PackageOpen,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { index as dashboardIndex } from '@/actions/App/Http/Controllers/Admin/DashboardController';
import DashboardStatTile from '@/components/DashboardStatTile.vue';
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
    fieldLabelClass,
    filterSectionHeadingClass as sectionHeadingClass,
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

const customExpiringDays = ref(String(props.stats.expiring.custom.days));

function onCustomExpiringDaysChange(): void {
    if (customExpiringDays.value === '') {
        return;
    }

    router.get(
        dashboardIndex().url,
        {
            product_id: props.filters.product_id ?? [],
            expiring_days: Number(customExpiringDays.value),
        },
        { preserveState: true, replace: true },
    );
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
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('dashboard.title') }}</h1>
            <div class="flex items-center gap-4">
                <FilterMultiSelect
                    id="dashboard-product"
                    :model-value="productIdStrings"
                    :label="t('cellLog.filters.product')"
                    :all-label="t('cellLog.filters.all')"
                    :selected-count-label="selectedCountLabel"
                    :options="
                        filterOptions.products.map((product) => ({
                            value: product.id.toString(),
                            label: product.name,
                        }))
                    "
                    @update:model-value="onProductIdsChange"
                />
            </div>
        </div>

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
                    :icon="CircleDashed"
                />
                <DashboardStatTile
                    :label="t('dashboard.occupancy.full')"
                    :value="props.stats.occupancy.full"
                    :href="cellsIndex().url"
                    :query="{ state: 'full', ...productQuery }"
                    :icon="Inbox"
                />
                <DashboardStatTile
                    :label="t('dashboard.occupancy.opened')"
                    :value="props.stats.occupancy.opened"
                    :href="cellsIndex().url"
                    :query="{ state: 'opened', ...productQuery }"
                    :icon="PackageOpen"
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
                    <label
                        for="dashboard-custom-expiring-days"
                        :class="[fieldLabelClass, 'mt-2']"
                        >{{ t('expiringWindow.label') }}</label
                    >
                    <input
                        id="dashboard-custom-expiring-days"
                        v-model="customExpiringDays"
                        type="number"
                        min="1"
                        step="1"
                        :placeholder="t('expiringWindow.customPlaceholder')"
                        class="w-full rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        @change="onCustomExpiringDaysChange"
                    />
                </div>
            </div>
        </section>

        <section class="mb-8">
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.activityToday.title') }}
            </h2>
            <div :class="tileGridClass">
                <DashboardStatTile
                    :label="t('dashboard.activityToday.stored')"
                    :value="props.stats.activity_today.stored"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: ['stored'],
                        date_from: props.today,
                        date_to: props.today,
                        ...productQuery,
                    }"
                    :icon="CalendarPlus"
                />
                <DashboardStatTile
                    :label="t('dashboard.activityToday.opened')"
                    :value="props.stats.activity_today.opened"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: ['opened'],
                        date_from: props.today,
                        date_to: props.today,
                        ...productQuery,
                    }"
                    :icon="PackageOpen"
                />
                <DashboardStatTile
                    :label="t('dashboard.activityToday.emptied')"
                    :value="props.stats.activity_today.emptied"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: ['emptied'],
                        date_from: props.today,
                        date_to: props.today,
                        ...productQuery,
                    }"
                    :icon="CircleDashed"
                />
                <DashboardStatTile
                    :label="t('dashboard.activityToday.transferred')"
                    :value="props.stats.activity_today.transferred"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: ['transferred_out', 'transferred_in'],
                        date_from: props.today,
                        date_to: props.today,
                        ...productQuery,
                    }"
                    :icon="ArrowLeftRight"
                />
            </div>
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.activityWeek.title') }}
            </h2>
            <div :class="tileGridClass">
                <DashboardStatTile
                    :label="t('dashboard.activityWeek.stored')"
                    :value="props.stats.activity_week.stored"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: ['stored'],
                        date_from: props.weekStart,
                        date_to: props.today,
                        ...productQuery,
                    }"
                    :icon="CalendarPlus"
                />
                <DashboardStatTile
                    :label="t('dashboard.activityWeek.opened')"
                    :value="props.stats.activity_week.opened"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: ['opened'],
                        date_from: props.weekStart,
                        date_to: props.today,
                        ...productQuery,
                    }"
                    :icon="PackageOpen"
                />
                <DashboardStatTile
                    :label="t('dashboard.activityWeek.emptied')"
                    :value="props.stats.activity_week.emptied"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: ['emptied'],
                        date_from: props.weekStart,
                        date_to: props.today,
                        ...productQuery,
                    }"
                    :icon="CircleDashed"
                />
                <DashboardStatTile
                    :label="t('dashboard.activityWeek.transferred')"
                    :value="props.stats.activity_week.transferred"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: ['transferred_out', 'transferred_in'],
                        date_from: props.weekStart,
                        date_to: props.today,
                        ...productQuery,
                    }"
                    :icon="ArrowLeftRight"
                />
            </div>
        </section>
    </AdminLayout>
</template>
