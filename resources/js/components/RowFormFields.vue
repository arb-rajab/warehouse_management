<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import StoreSelectField from '@/components/StoreSelectField.vue';
import { t } from '@/lib/i18n';
import type { WarehouseSummary } from '@/types/admin';

/**
 * Mirrors Row::MAX_DIMENSION (app/Models/Row.php) — the backend rule stays
 * authoritative, this is just a client-side flood guard.
 */
const MAX_DIMENSION = 500;

withDefaults(
    defineProps<{
        /**
         * Prefixes every id this renders (`${idPrefix}letter`/etc.) so a page
         * can render this component more than once without a DOM id
         * collision — see LocationFilterFields.vue for the same pattern.
         */
        idPrefix?: string;
        letter?: string;
        cellsCount?: number;
        flatsCount?: number;
        /**
         * The warehouses a new row can be created in: given, the form shows
         * the required warehouse select (the create form). A row stays in the
         * warehouse it was created in, so the edit form instead shows its
         * `warehouseName` read-only.
         */
        warehouses?: WarehouseSummary[];
        warehouseId?: number;
        warehouseName?: string;
        errors: Partial<
            Record<
                'warehouse_id' | 'letter' | 'cells_count' | 'flats_count',
                string
            >
        >;
    }>(),
    {
        idPrefix: '',
        letter: '',
    },
);
</script>

<template>
    <div class="space-y-4">
        <StoreSelectField
            v-if="warehouses"
            :id="`${idPrefix}warehouse_id`"
            :label="t('rows.fields.warehouse')"
            :options="warehouses"
            :value="warehouseId"
            :error="errors.warehouse_id"
            required
        />
        <FormField
            v-else-if="warehouseName !== undefined"
            :id="`${idPrefix}warehouse_name`"
            :label="t('rows.fields.warehouse')"
            :value="warehouseName"
            readonly
        />

        <FormField
            :id="`${idPrefix}letter`"
            :label="t('rows.fields.letter')"
            :value="letter"
            :error="errors.letter"
            maxlength="2"
            input-class="uppercase"
            required
        />

        <FormField
            :id="`${idPrefix}cells_count`"
            :label="t('rows.fields.cellsPerFlat')"
            type="number"
            :value="cellsCount"
            :error="errors.cells_count"
            min="1"
            :max="MAX_DIMENSION"
            step="1"
            required
        />

        <FormField
            :id="`${idPrefix}flats_count`"
            :label="t('rows.fields.flats')"
            type="number"
            :value="flatsCount"
            :error="errors.flats_count"
            min="1"
            :max="MAX_DIMENSION"
            step="1"
            required
        />
    </div>
</template>
