<script setup lang="ts">
import {
    ArrowLeftRight,
    CalendarPlus,
    CalendarX,
    ChevronDown,
    CircleDashed,
    PackageOpen,
} from '@lucide/vue';
import type { Component } from 'vue';
import { index as dashboardIndex } from '@/actions/App/Http/Controllers/Admin/DashboardController';
import CellStateLegend from '@/components/CellStateLegend.vue';
import DashboardStatTile from '@/components/DashboardStatTile.vue';
import HelpTopicPage from '@/components/HelpTopicPage.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import {
    fieldLabelClass,
    filterSectionHeadingClass as sectionHeadingClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';

const sections = ['overview', 'productFilter'];

/**
 * Same action/icon pairing as Dashboard/Index.vue's ACTIVITY_ACTIONS
 * (stored=CalendarPlus, opened=PackageOpen, emptied=CircleDashed,
 * transferred=ArrowLeftRight) — representative activity tiles for the
 * help preview, labelled from the same `cellLog.actions.*` keys the real
 * tiles use.
 */
const activityActions: {
    key: 'stored' | 'opened' | 'emptied' | 'transferred';
    icon: Component;
}[] = [
    { key: 'stored', icon: CalendarPlus },
    { key: 'opened', icon: PackageOpen },
    { key: 'emptied', icon: CircleDashed },
    { key: 'transferred', icon: ArrowLeftRight },
];
</script>

<template>
    <HelpTopicPage
        :title="t('help.dashboard.title')"
        :feature-href="dashboardIndex()"
        :feature-label="t('dashboard.title')"
    >
        <section v-for="section in sections" :key="section">
            <h2 :class="sectionHeadingClass">
                {{ t(`help.dashboard.${section}.heading`) }}
            </h2>
            <p class="text-sm text-gray-700 dark:text-neutral-300">
                {{ t(`help.dashboard.${section}.body`) }}
            </p>

            <template v-if="section === 'overview'">
                <div class="mt-2">
                    <CellStateLegend />
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <HelpUiPreview>
                        <DashboardStatTile
                            tabindex="-1"
                            href="#"
                            :value="0"
                            tone="danger"
                            :icon="CalendarX"
                            :label="t('dashboard.expiring.expired')"
                        />
                    </HelpUiPreview>
                    <HelpUiPreview
                        v-for="action in activityActions"
                        :key="action.key"
                    >
                        <DashboardStatTile
                            tabindex="-1"
                            href="#"
                            :value="0"
                            :icon="action.icon"
                            :label="t(`cellLog.actions.${action.key}`)"
                        />
                    </HelpUiPreview>
                </div>
            </template>

            <HelpUiPreview v-if="section === 'productFilter'" class="mt-2">
                <div>
                    <span :class="fieldLabelClass">{{
                        t('cellLog.filters.product')
                    }}</span>
                    <span
                        tabindex="-1"
                        class="flex w-48 items-center justify-between gap-2 rounded-md border border-gray-300 px-3 py-2 text-start text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        <span class="truncate">{{
                            t('cellLog.filters.all')
                        }}</span>
                        <ChevronDown class="h-4 w-4 shrink-0 text-gray-400" />
                    </span>
                </div>
            </HelpUiPreview>
        </section>
    </HelpTopicPage>
</template>
