<script setup lang="ts">
import { show as showRow } from '@/actions/App/Http/Controllers/Admin/RowController';
import DataTable from '@/components/DataTable.vue';
import TableLink from '@/components/TableLink.vue';
import { cellStateLabel } from '@/lib/cellStateColor';
import { formatDate } from '@/lib/date';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
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
                <div
                    class="flex items-center gap-2 font-medium text-gray-900 dark:text-neutral-100"
                >
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
                </div>
            </td>
            <td class="px-4 py-2">
                {{ cellStateLabel(pallet.state) }}
            </td>
            <td class="px-4 py-2">
                <TableLink
                    :href="
                        showRow(
                            { letter: pallet.location.row_letter },
                            {
                                query: {
                                    cell_number: pallet.location.cell_number,
                                    flat_number: pallet.location.flat_number,
                                },
                            },
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
            </td>
            <td class="px-4 py-2">
                <span v-if="pallet.expiration_date">
                    {{ formatDate(pallet.expiration_date) }}
                </span>
                <span v-else class="text-gray-400 dark:text-neutral-600"
                    >—</span
                >
            </td>
            <td class="px-4 py-2">
                <span v-if="pallet.added_at">
                    {{ formatDate(pallet.added_at) }}
                </span>
                <span v-else class="text-gray-400 dark:text-neutral-600"
                    >—</span
                >
            </td>
            <td class="px-4 py-2">{{ pallet.remaining_boxes }}</td>
        </template>
    </DataTable>
</template>
