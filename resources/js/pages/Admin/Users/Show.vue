<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed } from 'vue';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { show as showCellVerificationRound } from '@/actions/App/Http/Controllers/Admin/CellVerificationRoundController';
import { show as showRow } from '@/actions/App/Http/Controllers/Admin/RowController';
import {
    edit as editUser,
    show as showUser,
} from '@/actions/App/Http/Controllers/Admin/UserController';
import CellStatusLogRowCells from '@/components/CellStatusLogRowCells.vue';
import CellVerificationCorrectnessBadge from '@/components/CellVerificationCorrectnessBadge.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { cellStateLabel } from '@/lib/cellStateColor';
import { mergeTransferPairs } from '@/lib/cellStatusLogDisplay';
import { snapshotProductLabel } from '@/lib/cellVerificationReportDisplay';
import { formatDateTime } from '@/lib/date';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import type {
    CellStatusLog,
    CellVerificationReport,
    Paginated,
    User,
    UserShowFilters,
} from '@/types/admin';

const props = defineProps<{
    user: User;
    logs: Paginated<CellStatusLog>;
    reports: Paginated<CellVerificationReport>;
    filters: UserShowFilters;
}>();

const displayLogs = computed(() => mergeTransferPairs(props.logs.data));

const currentPerPage = computed(() => props.filters.per_page ?? 20);
const currentReportsPerPage = computed(
    () => props.filters.reports_per_page ?? 20,
);

function viewPalletHistory(palletId: number): void {
    router.get(cellLogsIndex().url, { pallet_id: palletId });
}

function onPerPageChange(perPage: number): void {
    router.get(
        showUser({ id: props.user.id }).url,
        { ...props.filters, per_page: perPage },
        { preserveState: true, replace: true },
    );
}

function onReportsPerPageChange(perPage: number): void {
    router.get(
        showUser({ id: props.user.id }).url,
        { ...props.filters, reports_per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('users.show.title', { name: user.name })" />

    <AdminLayout>
        <PageHeader :title="t('users.show.title', { name: user.name })">
            <Link
                :href="editUser(user)"
                class="inline-flex items-center gap-1 text-sm text-gray-600 hover:underline dark:text-neutral-400"
            >
                <Pencil class="h-3.5 w-3.5" />
                {{ t('users.show.editUser') }}
            </Link>
        </PageHeader>

        <section class="mb-8">
            <h2 :class="sectionHeadingClass">
                {{ t('users.show.actionsTitle') }}
            </h2>

            <DataTable
                :columns="[
                    t('cellLog.columns.cell'),
                    t('cellLog.columns.action'),
                    t('cellLog.columns.product'),
                    t('cellLog.columns.pallet'),
                    t('cellLog.columns.note'),
                    t('cellLog.columns.when'),
                ]"
                :rows="displayLogs"
                :empty-message="t('users.show.empty')"
            >
                <template #row="{ row: log }">
                    <CellStatusLogRowCells
                        :log="log"
                        return-to="user"
                        @view-pallet-history="viewPalletHistory"
                    />
                </template>
            </DataTable>

            <Pagination
                :links="logs.meta.links"
                :per-page="currentPerPage"
                @update:per-page="onPerPageChange"
            />
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('users.show.reportsTitle') }}
            </h2>

            <DataTable
                :columns="[
                    t('cellVerificationReport.columns.round'),
                    t('cellVerificationReport.columns.cell'),
                    t('cellVerificationReport.columns.correctness'),
                    t('cellVerificationReport.columns.expected'),
                    t('cellVerificationReport.columns.reported'),
                    t('cellVerificationReport.columns.note'),
                    t('cellVerificationReport.columns.when'),
                ]"
                :rows="reports.data"
                :empty-message="t('cellVerificationReport.empty')"
            >
                <template #row="{ row: report }">
                    <td class="px-4 py-2">
                        <TableLink
                            :href="
                                showCellVerificationRound({
                                    cellVerificationRound:
                                        report.cell_verification_round_id,
                                })
                            "
                        >
                            #{{ report.cell_verification_round_id }}
                        </TableLink>
                    </td>
                    <td class="px-4 py-2">
                        <TableLink
                            :href="showRow({ letter: report.cell.row_letter })"
                        >
                            {{
                                formatSlot(
                                    report.cell.row_letter,
                                    report.cell.cell_number,
                                    report.cell.flat_number,
                                )
                            }}
                        </TableLink>
                    </td>
                    <td class="px-4 py-2">
                        <CellVerificationCorrectnessBadge
                            :is-correct="report.is_correct"
                        />
                    </td>
                    <td class="px-4 py-2">
                        <div>
                            {{
                                report.expected.cell_state
                                    ? cellStateLabel(report.expected.cell_state)
                                    : '—'
                            }}
                        </div>
                        <div
                            v-if="snapshotProductLabel(report.expected)"
                            class="text-xs text-gray-500 dark:text-neutral-400"
                        >
                            {{ snapshotProductLabel(report.expected) }} ·
                            {{ report.expected.boxes_count }}
                        </div>
                    </td>
                    <td class="px-4 py-2">
                        <div>
                            {{
                                report.reported.cell_state
                                    ? cellStateLabel(report.reported.cell_state)
                                    : '—'
                            }}
                        </div>
                        <div
                            v-if="snapshotProductLabel(report.reported)"
                            class="text-xs text-gray-500 dark:text-neutral-400"
                        >
                            {{ snapshotProductLabel(report.reported) }} ·
                            {{ report.reported.boxes_count }}
                        </div>
                    </td>
                    <td class="px-4 py-2">{{ report.note ?? '—' }}</td>
                    <td class="px-4 py-2">
                        {{ formatDateTime(report.created_at) }}
                    </td>
                </template>
            </DataTable>

            <Pagination
                :links="reports.meta.links"
                :per-page="currentReportsPerPage"
                @update:per-page="onReportsPerPageChange"
            />
        </section>
    </AdminLayout>
</template>
