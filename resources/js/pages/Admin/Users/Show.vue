<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Clock, History, Pencil, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { show as showRow } from '@/actions/App/Http/Controllers/Admin/RowController';
import {
    edit as editUser,
    show as showUser,
} from '@/actions/App/Http/Controllers/Admin/UserController';
import CellLogFlagBadges from '@/components/CellLogFlagBadges.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableLink from '@/components/TableLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { cellStateLabel } from '@/lib/cellStateColor';
import {
    cellLogActionLabel,
    mergeTransferPairs,
    transferPair,
} from '@/lib/cellStatusLogDisplay';
import { formatDate, formatDateTime, formatDuration } from '@/lib/date';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import type {
    CellStatusLog,
    Paginated,
    PerPageFilters,
    User,
} from '@/types/admin';

const props = defineProps<{
    user: User;
    logs: Paginated<CellStatusLog>;
    filters: PerPageFilters;
}>();

const displayLogs = computed(() => mergeTransferPairs(props.logs.data));

const currentPerPage = computed(() => props.filters.per_page ?? 20);

function viewPalletHistory(palletId: number): void {
    router.get(cellLogsIndex().url, { pallet_id: palletId });
}

function onPerPageChange(perPage: number): void {
    router.get(
        showUser({ id: props.user.id }).url,
        { per_page: perPage },
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
                <template
                    v-for="(pair, pairIndex) in [transferPair(log)]"
                    :key="pairIndex"
                >
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-1">
                            <TableLink
                                :href="
                                    showRow({ letter: pair.from.row_letter })
                                "
                            >
                                {{
                                    formatSlot(
                                        pair.from.row_letter,
                                        pair.from.cell_number,
                                        pair.from.flat_number,
                                    )
                                }}
                            </TableLink>
                            <template v-if="pair.to">
                                <ArrowRight
                                    class="h-3 w-3 shrink-0 text-gray-400 rtl:rotate-180"
                                />
                                <TableLink
                                    :href="
                                        showRow({ letter: pair.to.row_letter })
                                    "
                                >
                                    {{
                                        formatSlot(
                                            pair.to.row_letter,
                                            pair.to.cell_number,
                                            pair.to.flat_number,
                                        )
                                    }}
                                </TableLink>
                            </template>
                        </div>
                    </td>
                </template>
                <td class="px-4 py-2">
                    <div
                        class="flex items-center gap-1 font-medium text-gray-900 dark:text-neutral-100"
                    >
                        {{
                            log.pairedIn
                                ? t('cellLog.actions.transferred')
                                : cellLogActionLabel(log.action)
                        }}
                        <TriangleAlert
                            v-if="log.flagged"
                            class="h-3.5 w-3.5 shrink-0 text-amber-500"
                        />
                    </div>
                    <div class="text-xs text-gray-500 dark:text-neutral-400">
                        {{ cellStateLabel(log.from_state) }}
                        <template v-if="!log.pairedIn">
                            <span class="inline-block rtl:rotate-180">→</span>
                            {{ cellStateLabel(log.to_state) }}
                        </template>
                    </div>
                    <CellLogFlagBadges :flags="log.flags" />
                </td>
                <td class="px-4 py-2">
                    <div
                        v-if="log.product"
                        class="flex items-center gap-2 font-medium text-gray-900 dark:text-neutral-100"
                    >
                        <img
                            v-if="log.product.image_url"
                            :src="log.product.image_url"
                            :alt="log.product.name"
                            class="h-8 w-8 shrink-0 rounded object-cover"
                        />
                        {{ log.product.name }}
                    </div>
                    <span v-else class="text-gray-400 dark:text-neutral-600"
                        >—</span
                    >
                </td>
                <td class="px-4 py-2">
                    <template v-if="log.pallet">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 font-medium text-blue-600 hover:underline dark:text-blue-400"
                            :title="t('cellLog.columns.viewPalletHistory')"
                            @click="viewPalletHistory(log.pallet.id)"
                        >
                            #{{ log.pallet.id }}
                            <History class="h-3 w-3 shrink-0" />
                        </button>
                        <div
                            v-if="log.boxes_count !== null"
                            class="text-xs text-gray-500 dark:text-neutral-400"
                        >
                            {{ t('cellLog.columns.boxes') }}:
                            {{ log.boxes_count }}
                        </div>
                        <div
                            v-if="log.pallet.expiration_date"
                            class="text-xs text-gray-500 dark:text-neutral-400"
                        >
                            {{ t('cellLog.columns.expires') }}
                            {{ formatDate(log.pallet.expiration_date) }}
                        </div>
                    </template>
                    <span v-else class="text-gray-400 dark:text-neutral-600"
                        >—</span
                    >
                </td>
                <td
                    class="max-w-xs truncate px-4 py-2 text-gray-500 dark:text-neutral-400"
                    :title="log.note ?? undefined"
                >
                    {{ log.note ?? '—' }}
                </td>
                <td class="px-4 py-2">
                    <div
                        class="font-medium text-gray-900 dark:text-neutral-100"
                    >
                        {{ formatDateTime(log.created_at) }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-neutral-400">
                        {{ formatDuration(log.duration_seconds) }}
                    </div>
                    <div
                        v-if="!log.next_log_at"
                        class="flex items-center gap-1 text-xs text-gray-400 dark:text-neutral-600"
                    >
                        <Clock class="h-3 w-3 shrink-0" />
                        {{ t('cellLog.columns.ongoing') }}
                    </div>
                </td>
            </template>
        </DataTable>

        <Pagination
            :links="logs.meta.links"
            :per-page="currentPerPage"
            @update:per-page="onPerPageChange"
        />
    </AdminLayout>
</template>
