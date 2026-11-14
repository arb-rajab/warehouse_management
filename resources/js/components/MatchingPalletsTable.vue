<script setup lang="ts">
import { Ban } from '@lucide/vue';
import { show as showReceipt } from '@/actions/App/Http/Controllers/Admin/ReceiptController';
import { show as showRow } from '@/actions/App/Http/Controllers/Admin/RowController';
import DataTable from '@/components/DataTable.vue';
import ProductActiveBadge from '@/components/ProductActiveBadge.vue';
import TableLink from '@/components/TableLink.vue';
import { cellStateLabel } from '@/lib/cellStateColor';
import { formatDate } from '@/lib/date';
import { t } from '@/lib/i18n';
import { formatSlot, rowLinkOptions } from '@/lib/location';
import { productName } from '@/lib/productName';
import type { MatchingPallet } from '@/types/admin';

defineProps<{
    pallets: MatchingPallet[];
    emptyMessage: string;
}>();
</script>

<template>
    <DataTable
        :columns="[
            t('pallets.columns.product'),
            t('pallets.columns.state'),
            t('pallets.columns.location'),
            t('pallets.columns.expiration'),
            t('pallets.columns.added'),
            t('pallets.columns.remainingBoxes'),
        ]"
        :rows="pallets"
        :empty-message="emptyMessage"
    >
        <template #row="{ row: pallet }">
            <td class="px-4 py-2">
                <div class="flex items-center gap-2 font-medium text-ink">
                    <img
                        v-if="pallet.product.image_url"
                        :src="pallet.product.image_url"
                        :alt="
                            productName(
                                pallet.product.name,
                                pallet.product.ar_name,
                            )
                        "
                        class="h-8 w-8 shrink-0 rounded object-cover"
                    />
                    {{
                        productName(pallet.product.name, pallet.product.ar_name)
                    }}
                    <ProductActiveBadge :active="pallet.product.active" />
                </div>
            </td>
            <td class="px-4 py-2">
                <span
                    v-if="pallet.state === null"
                    class="inline-flex items-center rounded border border-line bg-subtle px-1.5 py-0.5 text-xs font-semibold text-muted"
                    data-testid="unplaced-badge"
                >
                    {{ t('pallets.unplacedBadge') }}
                </span>
                <template v-else>{{ cellStateLabel(pallet.state) }}</template>
            </td>
            <td v-if="pallet.location === null" class="px-4 py-2">
                <div
                    v-if="pallet.warehouse"
                    class="text-ink"
                    data-testid="unplaced-warehouse"
                >
                    {{ pallet.warehouse.name }}
                </div>
                <TableLink
                    v-if="pallet.receipt_id !== null"
                    :href="showReceipt(pallet.receipt_id)"
                    data-testid="unplaced-receipt-link"
                >
                    {{ t('pallets.receiptLink', { id: pallet.receipt_id }) }}
                </TableLink>
                <span
                    v-if="!pallet.warehouse && pallet.receipt_id === null"
                    class="text-muted"
                    >—</span
                >
            </td>
            <td v-else class="px-4 py-2">
                <TableLink
                    class="font-mono"
                    :href="
                        showRow(
                            { letter: pallet.location.row_letter },
                            rowLinkOptions(pallet.location.warehouse, {
                                cell_number: pallet.location.cell_number,
                                flat_number: pallet.location.flat_number,
                            }),
                        )
                    "
                >
                    {{
                        formatSlot(
                            pallet.location.row_letter,
                            pallet.location.cell_number,
                            pallet.location.flat_number,
                        )
                    }}
                </TableLink>
                <span
                    v-if="!pallet.cell_active"
                    class="ms-2 inline-flex items-center gap-0.5 rounded border border-danger/30 bg-danger-soft px-1 py-0.5 align-middle text-[10px] font-semibold text-danger-strong"
                    data-testid="cell-inactive-badge"
                >
                    <Ban class="h-3 w-3 shrink-0" stroke-width="2.5" />
                    {{ t('cells.inactiveBadge') }}
                </span>
            </td>
            <td class="px-4 py-2">
                <span v-if="pallet.expiration_date">
                    {{ formatDate(pallet.expiration_date) }}
                </span>
                <span v-else class="text-muted">—</span>
            </td>
            <td class="px-4 py-2">
                <span v-if="pallet.added_at">
                    {{ formatDate(pallet.added_at) }}
                </span>
                <span v-else class="text-muted">—</span>
            </td>
            <td class="px-4 py-2">{{ pallet.remaining_boxes }}</td>
        </template>
    </DataTable>
</template>
