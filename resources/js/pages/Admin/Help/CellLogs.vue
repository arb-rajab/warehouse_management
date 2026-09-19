<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import CellStateLegend from '@/components/CellStateLegend.vue';
import PageHeader from '@/components/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';

const actionKeys = [
    'stored',
    'opened',
    'boxesRemoved',
    'emptied',
    'transferred',
    'deactivated',
    'reactivated',
] as const;
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
            </section>

            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.cellLogs.actions.heading') }}
                </h2>
                <ul class="space-y-1.5">
                    <li
                        v-for="action in actionKeys"
                        :key="action"
                        class="text-sm text-gray-700 dark:text-neutral-300"
                    >
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
