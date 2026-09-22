<script setup lang="ts">
import { ArrowRight, Check, Clock, History, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { show as showRow } from '@/actions/App/Http/Controllers/Admin/RowController';
import { show as showUser } from '@/actions/App/Http/Controllers/Admin/UserController';
import CellLogFlagBadges from '@/components/CellLogFlagBadges.vue';
import TableLink from '@/components/TableLink.vue';
import { cellStateLabel } from '@/lib/cellStateColor';
import {
    acknowledgeFlags,
    cellLogActionLabel,
    hasUnacknowledgedFlags,
    transferPair,
} from '@/lib/cellStatusLogDisplay';
import type { DisplayCellStatusLog } from '@/lib/cellStatusLogDisplay';
import { formatDate, formatDateTime, formatDuration } from '@/lib/date';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import { productName } from '@/lib/productName';

/**
 * One cell-status-log row's cells, shared by the cell-log listing
 * (CellStatusLogs/Index.vue) and a single user's action history
 * (Users/Show.vue). The two render the same row and differ only in whether a
 * "done by" column exists and where acknowledging a flag returns to.
 *
 * Deliberately multi-root: these are sibling `<td>`s inside DataTable's own
 * `<tr>`, so neither a wrapper element nor the `class="contents"` trick the
 * filter-field groups use would be valid HTML here. Vue 3 fragments make this
 * work; the one cost is that a multi-root component has no attribute
 * fallthrough target, so callers must not pass `class`/`style` or any other
 * non-prop attribute to this tag — style the `<td>`s here instead.
 */
const props = defineProps<{
    log: DisplayCellStatusLog;
    /** Render the "done by" column — omit on a page already scoped to one user. */
    showUserColumn?: boolean;
    /** Where acknowledging a flag returns to; omit for the log listing itself. */
    returnTo?: 'user';
}>();

defineEmits<{
    'view-pallet-history': [palletId: number];
}>();

const pair = computed(() => transferPair(props.log));
</script>

<template>
    <td class="px-4 py-2">
        <div class="flex items-center gap-1">
            <TableLink :href="showRow({ letter: pair.from.row_letter })">
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
                    class="h-3 w-3 shrink-0 text-gray-400 rtl:rotate-180 dark:text-neutral-500"
                />
                <TableLink :href="showRow({ letter: pair.to.row_letter })">
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
        <button
            v-if="hasUnacknowledgedFlags(log)"
            type="button"
            class="mt-1 inline-flex cursor-pointer items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400"
            @click="acknowledgeFlags(log, returnTo)"
        >
            <Check class="h-3 w-3 shrink-0" />
            {{ t('cellLog.flags.acknowledge') }}
        </button>
    </td>
    <td class="px-4 py-2">
        <div
            v-if="log.product"
            class="flex items-center gap-2 font-medium text-gray-900 dark:text-neutral-100"
        >
            <img
                v-if="log.product.image_url"
                :src="log.product.image_url"
                :alt="productName(log.product.name, log.product.ar_name)"
                class="h-8 w-8 shrink-0 rounded object-cover"
            />
            {{ productName(log.product.name, log.product.ar_name) }}
        </div>
        <span v-else class="text-gray-400 dark:text-neutral-600">—</span>
    </td>
    <td class="px-4 py-2">
        <template v-if="log.pallet">
            <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-1 font-medium text-blue-600 hover:underline dark:text-blue-400"
                :title="t('cellLog.columns.viewPalletHistory')"
                @click="$emit('view-pallet-history', log.pallet.id)"
            >
                #{{ log.pallet.id }}
                <History class="h-3 w-3 shrink-0" />
            </button>
            <div
                v-if="log.boxes_count !== null"
                class="text-xs text-gray-500 dark:text-neutral-400"
            >
                {{ t('cellLog.columns.boxes') }}: {{ log.boxes_count }}
            </div>
            <div
                v-if="log.pallet.expiration_date"
                class="text-xs text-gray-500 dark:text-neutral-400"
            >
                {{ t('cellLog.columns.expires') }}
                {{ formatDate(log.pallet.expiration_date) }}
            </div>
        </template>
        <span v-else class="text-gray-400 dark:text-neutral-600">—</span>
    </td>
    <td
        class="max-w-xs truncate px-4 py-2 text-gray-500 dark:text-neutral-400"
        :title="log.note ?? undefined"
    >
        {{ log.note ?? '—' }}
    </td>
    <td v-if="showUserColumn" class="px-4 py-2">
        <TableLink :href="showUser({ id: log.user.id })">
            {{ log.user.name }}
        </TableLink>
    </td>
    <td class="px-4 py-2">
        <div class="font-medium text-gray-900 dark:text-neutral-100">
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
