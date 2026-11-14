<script setup lang="ts">
import { computed, watch } from 'vue';
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { rowsInWarehouse } from '@/lib/palletTransferForm';
import type { CellMapRow, WarehouseSummary } from '@/types/admin';

/**
 * A pallet transfer's destination: warehouse, row, cell number and level
 * (Admin\TransferPalletRequest). The warehouse select shows only when there
 * is more than one warehouse; it has no empty choice and its rule is
 * nullable, so it carries no required mark. The row list follows the chosen
 * warehouse, and a row letter that warehouse does not have is cleared. Every
 * control has the request's field `name`, so the browser validation
 * (`lib/palletTransferForm.ts`) reads it. Used by the row page's pallet
 * dialog, the warehouse map's transfer dialog and the help page.
 */
const props = withDefaults(
    defineProps<{
        warehouses: WarehouseSummary[];
        rows: CellMapRow[];
        errors?: Record<string, string>;
        idPrefix?: string;
    }>(),
    {
        errors: () => ({}),
        idPrefix: 'pallet-transfer-',
    },
);

const warehouseId = defineModel<number | null>('warehouseId', {
    required: true,
});
const rowLetter = defineModel<string>('rowLetter', { required: true });
const cellNumber = defineModel<string>('cellNumber', { required: true });
const flatNumber = defineModel<string>('flatNumber', { required: true });

const rowOptions = computed(() =>
    rowsInWarehouse(props.rows, warehouseId.value),
);

watch(warehouseId, () => {
    if (!rowOptions.value.some((row) => row.letter === rowLetter.value)) {
        rowLetter.value = '';
    }
});
</script>

<template>
    <div v-if="warehouses.length > 1">
        <label :for="`${idPrefix}warehouse`" :class="fieldLabelClass">
            {{ t('cells.palletActions.transfer.warehouseLabel') }}
        </label>
        <select
            :id="`${idPrefix}warehouse`"
            v-model.number="warehouseId"
            name="warehouse_id"
            :class="[plainFieldInputClass, 'cursor-pointer']"
        >
            <option
                v-for="warehouse in warehouses"
                :key="warehouse.id"
                :value="warehouse.id"
            >
                {{ warehouse.name }}
            </option>
        </select>
        <FieldError :message="errors.warehouse_id" />
    </div>
    <div>
        <label :for="`${idPrefix}row`" :class="fieldLabelClass"
            >{{ t('cells.palletActions.transfer.rowLabel') }}<RequiredMark
        /></label>
        <select
            :id="`${idPrefix}row`"
            v-model="rowLetter"
            name="row_letter"
            required
            :class="[plainFieldInputClass, 'cursor-pointer']"
        >
            <option value="" disabled>—</option>
            <option v-for="row in rowOptions" :key="row.id" :value="row.letter">
                {{ row.letter }}
            </option>
        </select>
        <FieldError :message="errors.row_letter" />
    </div>
    <div>
        <label :for="`${idPrefix}cell`" :class="fieldLabelClass"
            >{{ t('cells.palletActions.transfer.cellNumberLabel')
            }}<RequiredMark
        /></label>
        <input
            :id="`${idPrefix}cell`"
            v-model="cellNumber"
            name="cell_number"
            type="number"
            min="1"
            step="1"
            required
            :class="plainFieldInputClass"
        />
        <FieldError :message="errors.cell_number" />
    </div>
    <div>
        <label :for="`${idPrefix}flat`" :class="fieldLabelClass"
            >{{ t('cells.palletActions.transfer.flatNumberLabel')
            }}<RequiredMark
        /></label>
        <input
            :id="`${idPrefix}flat`"
            v-model="flatNumber"
            name="flat_number"
            type="number"
            min="1"
            step="1"
            required
            :class="plainFieldInputClass"
        />
        <FieldError :message="errors.flat_number" />
    </div>
</template>
