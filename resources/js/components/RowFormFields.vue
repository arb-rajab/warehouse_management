<script setup lang="ts">
import { t } from '@/lib/i18n';
import FormField from './FormField.vue';

withDefaults(
    defineProps<{
        letter?: string;
        cellsCount?: number;
        flatsCount?: number;
        disableDimensions?: boolean;
        errors: Partial<
            Record<'letter' | 'cells_count' | 'flats_count', string>
        >;
    }>(),
    {
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
            id="letter"
            :label="t('rows.fields.letter')"
            :value="letter"
            :error="errors.letter"
            maxlength="2"
            input-class="uppercase"
            required
        />

        <FormField
            id="cells_count"
            :label="t('rows.fields.cellsPerFlat')"
            type="number"
            :value="cellsCount"
            :error="errors.cells_count"
            min="1"
            step="1"
            :readonly="disableDimensions"
            required
        />

        <FormField
            id="flats_count"
            :label="t('rows.fields.flats')"
            type="number"
            :value="flatsCount"
            :error="errors.flats_count"
            min="1"
            step="1"
            :readonly="disableDimensions"
            required
        />

        <p
            v-if="disableDimensions"
            class="text-sm text-amber-600 dark:text-amber-400"
        >
            {{ t('rows.fields.dimensionsLocked') }}
        </p>
    </div>
</template>
