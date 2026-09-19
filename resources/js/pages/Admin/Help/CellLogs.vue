<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Ban,
    CalendarPlus,
    CircleDashed,
    PackageOpen,
    SlidersHorizontal,
} from '@lucide/vue';
import { reactive } from 'vue';
import type { Component } from 'vue';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import CellLogActivityFilterFields from '@/components/CellLogActivityFilterFields.vue';
import CellStateLegend from '@/components/CellStateLegend.vue';
import DateRangeFilterFields from '@/components/DateRangeFilterFields.vue';
import FilterProductSelect from '@/components/FilterProductSelect.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import LocationFilterFields from '@/components/LocationFilterFields.vue';
import PageHeader from '@/components/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
    filterSectionHeadingClass as sectionHeadingClass,
    filterTriggerButtonClass,
    selectedCountLabel,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { CellLogAction } from '@/types/admin';

const previewFilters = reactive({
    product_id: [] as string[],
    row_id: '',
    column_number: '',
    action: [] as CellLogAction[],
    user_id: [] as string[],
    date_from: '',
    date_to: '',
    created_within_days: '',
});

type ActionKey =
    | 'stored'
    | 'opened'
    | 'boxesRemoved'
    | 'emptied'
    | 'transferred'
    | 'deactivated'
    | 'reactivated';

const actionKeys: ActionKey[] = [
    'stored',
    'opened',
    'boxesRemoved',
    'emptied',
    'transferred',
    'deactivated',
    'reactivated',
];

/**
 * Only actions with an icon already established elsewhere in the app get one
 * here (stored=CalendarPlus, opened/emptied reuse the matching cell-state
 * icon, transferred=ArrowLeftRight, deactivated=Ban — see js.md's icon
 * conventions and CellSlot.vue's own deactivate button) — boxesRemoved and
 * reactivated have no established icon, so they stay text-only rather than
 * inventing one.
 */
const actionIcons: Partial<Record<ActionKey, Component>> = {
    stored: CalendarPlus,
    opened: PackageOpen,
    emptied: CircleDashed,
    transferred: ArrowLeftRight,
    deactivated: Ban,
};
</script>

<template>
    <Head :title="t('help.cellLogs.title')" />

    <AdminLayout>
        <PageHeader :title="t('help.cellLogs.title')" />

        <Link
            :href="helpIndex()"
            class="mb-6 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            {{ t('help.backToHelp') }}
        </Link>

        <div class="max-w-2xl space-y-6">
            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.cellLogs.filtering.heading') }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t('help.cellLogs.filtering.body') }}
                </p>
                <HelpUiPreview class="mt-2">
                    <button
                        type="button"
                        tabindex="-1"
                        :class="filterTriggerButtonClass"
                    >
                        <SlidersHorizontal class="h-4 w-4" />
                        {{ t('cellLog.filters.title') }}
                    </button>
                </HelpUiPreview>
                <HelpUiPreview inert class="mt-2 block">
                    <p class="mb-3 text-lg font-semibold">
                        {{ t('cellLog.filters.title') }}
                    </p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FilterProductSelect
                            id="help-cell-log-filter-product"
                            v-model="previewFilters.product_id"
                            :label="t('cellLog.filters.product')"
                            :all-label="t('cellLog.filters.all')"
                            :selected-count-label="selectedCountLabel"
                            :selected="[]"
                        />
                        <LocationFilterFields
                            id-prefix="help-cell-log-filter"
                            v-model:row-id="previewFilters.row_id"
                            v-model:column-number="previewFilters.column_number"
                            :rows="[]"
                            :max-column-number="1"
                        />
                        <CellLogActivityFilterFields
                            id-prefix="help-cell-log-filter"
                            v-model:action="previewFilters.action"
                            v-model:user-id="previewFilters.user_id"
                            :actions="[]"
                            :users="[]"
                        />
                        <DateRangeFilterFields
                            from-id="help-cell-log-filter-date-from"
                            to-id="help-cell-log-filter-date-to"
                            within-days-id="help-cell-log-filter-within-days"
                            :from-label="t('cellLog.filters.from')"
                            :to-label="t('cellLog.filters.to')"
                            :within-days-label="t('cellLog.filters.withinDays')"
                            v-model:from="previewFilters.date_from"
                            v-model:to="previewFilters.date_to"
                            v-model:within-days="
                                previewFilters.created_within_days
                            "
                            :range-disabled="false"
                            :days-disabled="false"
                        />
                    </div>
                </HelpUiPreview>
            </section>

            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.cellLogs.actions.heading') }}
                </h2>
                <ul class="space-y-1.5">
                    <li
                        v-for="action in actionKeys"
                        :key="action"
                        class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-neutral-300"
                    >
                        <component
                            :is="actionIcons[action]"
                            v-if="actionIcons[action]"
                            class="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-neutral-500"
                        />
                        {{ t(`help.cellLogs.actions.${action}`) }}
                    </li>
                </ul>
            </section>

            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.cellLogs.legend.heading') }}
                </h2>
                <p class="mb-3 text-sm text-gray-700 dark:text-neutral-300">
                    {{ t('help.cellLogs.legend.body') }}
                </p>
                <CellStateLegend />
            </section>

            <Link
                :href="cellLogsIndex()"
                class="inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
            >
                {{ t('cellLog.title') }}
            </Link>
        </div>
    </AdminLayout>
</template>
