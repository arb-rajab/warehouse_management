<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import DashboardStatTile from '@/components/DashboardStatTile.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';

const props = defineProps<{
    stats: {
        occupancy: { empty: number; full: number; opened: number };
        expiring: { expired: number; soon: number };
        activity_today: {
            stored: number;
            opened: number;
            emptied: number;
            transferred: number;
        };
    };
    today: string;
    expiringSoonUntil: string;
}>();

const tileGridClass = 'grid grid-cols-2 gap-4 sm:grid-cols-3';
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AdminLayout>
        <h1 class="mb-6 text-xl font-semibold">{{ t('dashboard.title') }}</h1>

        <section class="mb-8">
            <h2 :class="sectionHeadingClass">
                {{ t('dashboard.occupancy.title') }}
            </h2>
            <div :class="tileGridClass">
                <DashboardStatTile
                    :label="t('dashboard.occupancy.empty')"
                    :value="props.stats.occupancy.empty"
                    :href="cellsIndex().url"
                    :query="{ state: 'empty' }"
                />
                <DashboardStatTile
                    :label="t('dashboard.occupancy.full')"
                    :value="props.stats.occupancy.full"
                    :href="cellsIndex().url"
                    :query="{ state: 'full' }"
                />
                <DashboardStatTile
                    :label="t('dashboard.occupancy.opened')"
                    :value="props.stats.occupancy.opened"
                    :href="cellsIndex().url"
                    :query="{ state: 'opened' }"
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
                    :query="{ expiration_date_to: props.today }"
                    tone="danger"
                />
                <DashboardStatTile
                    :label="t('dashboard.expiring.soon')"
                    :value="props.stats.expiring.soon"
                    :href="cellsIndex().url"
                    :query="{
                        expiration_date_from: props.today,
                        expiration_date_to: props.expiringSoonUntil,
                    }"
                    tone="warning"
                />
            </div>
        </section>

        <section>
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
                    }"
                />
                <DashboardStatTile
                    :label="t('dashboard.activityToday.opened')"
                    :value="props.stats.activity_today.opened"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: ['opened'],
                        date_from: props.today,
                        date_to: props.today,
                    }"
                />
                <DashboardStatTile
                    :label="t('dashboard.activityToday.emptied')"
                    :value="props.stats.activity_today.emptied"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: ['emptied'],
                        date_from: props.today,
                        date_to: props.today,
                    }"
                />
                <DashboardStatTile
                    :label="t('dashboard.activityToday.transferred')"
                    :value="props.stats.activity_today.transferred"
                    :href="cellLogsIndex().url"
                    :query="{
                        action: ['transferred_out', 'transferred_in'],
                        date_from: props.today,
                        date_to: props.today,
                    }"
                />
            </div>
        </section>
    </AdminLayout>
</template>
