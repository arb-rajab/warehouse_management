<script setup lang="ts">
import { t } from '@/lib/i18n';
import FormField from './FormField.vue';

/**
 * Mirrors Setting::MIN_QR_CODE_SIZE / MAX_QR_CODE_SIZE (app/Models/Setting.php)
 * — the backend rule stays authoritative, this is just a client-side flood guard.
 */
const MIN_QR_CODE_SIZE = 100;
const MAX_QR_CODE_SIZE = 1000;

withDefaults(
    defineProps<{
        idPrefix?: string;
        qrCodeWidth?: number;
        qrCodeHeight?: number;
        errors: Partial<Record<'qr_code_width' | 'qr_code_height', string>>;
    }>(),
    {
        idPrefix: '',
        qrCodeWidth: undefined,
        qrCodeHeight: undefined,
    },
);
</script>

<template>
    <div class="space-y-4">
        <FormField
            :id="`${idPrefix}qr_code_width`"
            :label="t('settings.fields.qrCodeWidth')"
            type="number"
            :value="qrCodeWidth"
            :error="errors.qr_code_width"
            :min="MIN_QR_CODE_SIZE"
            :max="MAX_QR_CODE_SIZE"
            step="1"
            required
        />

        <FormField
            :id="`${idPrefix}qr_code_height`"
            :label="t('settings.fields.qrCodeHeight')"
            type="number"
            :value="qrCodeHeight"
            :error="errors.qr_code_height"
            :min="MIN_QR_CODE_SIZE"
            :max="MAX_QR_CODE_SIZE"
            step="1"
            required
        />
    </div>
</template>
