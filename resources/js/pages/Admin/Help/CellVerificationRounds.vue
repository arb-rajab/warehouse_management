<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { index as cellVerificationRoundsIndex } from '@/actions/App/Http/Controllers/Admin/CellVerificationRoundController';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import CellVerificationCorrectnessBadge from '@/components/CellVerificationCorrectnessBadge.vue';
import DataTable from '@/components/DataTable.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import PageHeader from '@/components/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { cellStateLabel } from '@/lib/cellStateColor';
import {
    filterClearButtonClass,
    filterSectionHeadingClass as sectionHeadingClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';

const sections = ['what', 'reports', 'exporting'];

/** Representative rows for the reports-table preview below — plain fixture data, never fetched. */
const previewReports = [
    {
        id: 1,
        cell: formatSlot('A', 3, 2),
        isCorrect: true,
        expected: cellStateLabel('full'),
        reported: cellStateLabel('full'),
        note: '—',
        when: '2026-01-01 10:00',
    },
    {
        id: 2,
        cell: formatSlot('B', 1, 4),
        isCorrect: false,
        expected: cellStateLabel('empty'),
        reported: cellStateLabel('full'),
        note: '—',
        when: '2026-01-01 10:05',
    },
];
</script>

<template>
    <Head :title="t('help.cellVerificationRounds.title')" />

    <AdminLayout>
        <PageHeader :title="t('help.cellVerificationRounds.title')" />

        <Link
            :href="helpIndex()"
            class="mb-6 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            {{ t('help.backToHelp') }}
        </Link>

        <div class="max-w-2xl space-y-6">
            <section v-for="section in sections" :key="section">
                <h2 :class="sectionHeadingClass">
                    {{ t(`help.cellVerificationRounds.${section}.heading`) }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t(`help.cellVerificationRounds.${section}.body`) }}
                </p>
                <HelpUiPreview v-if="section === 'reports'" class="mt-2">
                    <CellVerificationCorrectnessBadge :is-correct="true" />
                    <CellVerificationCorrectnessBadge :is-correct="false" />
                </HelpUiPreview>
                <HelpUiPreview
                    v-if="section === 'reports'"
                    inert
                    class="mt-2 block"
                >
                    <DataTable
                        :columns="[
                            t('cellVerificationReport.columns.cell'),
                            t('cellVerificationReport.columns.correctness'),
                            t('cellVerificationReport.columns.expected'),
                            t('cellVerificationReport.columns.reported'),
                            t('cellVerificationReport.columns.note'),
                            {
                                label: t('cellVerificationReport.columns.when'),
                                sortKey: 'created_at',
                            },
                        ]"
                        :rows="previewReports"
                        :empty-message="t('cellVerificationReport.empty')"
                    >
                        <template #row="{ row }">
                            <td class="px-4 py-2">{{ row.cell }}</td>
                            <td class="px-4 py-2">
                                <CellVerificationCorrectnessBadge
                                    :is-correct="row.isCorrect"
                                />
                            </td>
                            <td class="px-4 py-2">{{ row.expected }}</td>
                            <td class="px-4 py-2">{{ row.reported }}</td>
                            <td
                                class="px-4 py-2 text-gray-500 dark:text-neutral-400"
                            >
                                {{ row.note }}
                            </td>
                            <td class="px-4 py-2">{{ row.when }}</td>
                        </template>
                    </DataTable>
                </HelpUiPreview>

                <HelpUiPreview v-if="section === 'exporting'" class="mt-2">
                    <span tabindex="-1" :class="filterClearButtonClass">
                        <Download class="h-4 w-4" />
                        {{ t('cellVerificationReport.export') }}
                    </span>
                </HelpUiPreview>
            </section>

            <Link
                :href="cellVerificationRoundsIndex()"
                class="inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
            >
                {{ t('cellVerificationRound.title') }}
            </Link>
        </div>
    </AdminLayout>
</template>
