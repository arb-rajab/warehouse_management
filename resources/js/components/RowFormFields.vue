<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { t } from '@/lib/i18n';
import FormField from './FormField.vue';

/**
 * Mirrors Row::MAX_DIMENSION (app/Models/Row.php) — the backend rule stays
 * authoritative, this is just a client-side flood guard.
 */
const MAX_DIMENSION = 500;

const props = withDefaults(
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
        disableDimensions?: boolean;
        errors: Partial<
            Record<'letter' | 'cells_count' | 'flats_count', string>
        >;
    }>(),
    {
        idPrefix: '',
        letter: '',
        cellsCount: undefined,
        flatsCount: undefined,
        disableDimensions: false,
    },
);
</script>

<template>
    <div class="space-y-4">
        <FormField
            :id="`${props.idPrefix}letter`"
            :label="t('rows.fields.letter')"
            :value="letter"
            :error="errors.letter"
            maxlength="2"
            input-class="uppercase"
            required
        />

        <FormField
            :id="`${props.idPrefix}cells_count`"
            :label="t('rows.fields.cellsPerFlat')"
            type="number"
            :value="cellsCount"
            :error="errors.cells_count"
            min="1"
            :max="MAX_DIMENSION"
            step="1"
            :readonly="disableDimensions"
            required
        />

        <FormField
            :id="`${props.idPrefix}flats_count`"
            :label="t('rows.fields.flats')"
            type="number"
            :value="flatsCount"
            :error="errors.flats_count"
            min="1"
            :max="MAX_DIMENSION"
            step="1"
            :readonly="disableDimensions"
            required
        />

        <p
            v-if="disableDimensions"
            class="flex items-center gap-1 text-sm text-amber-600 dark:text-amber-400"
        >
            <TriangleAlert class="h-3.5 w-3.5 shrink-0" />
            {{ t('rows.fields.dimensionsLocked') }}
        </p>
    </div>
</template>
