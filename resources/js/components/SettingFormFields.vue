<script setup lang="ts">
import { computed } from 'vue';
import { t } from '@/lib/i18n';
import { pxToCm } from '@/lib/qrCodeSize';
import FormField from './FormField.vue';

/**
 * Mirrors Setting::MIN_QR_CODE_SIZE / MAX_QR_CODE_SIZE
 * (app/Models/Setting.php), converted to the cm this form is entered in —
 * the backend rule (in pixels) stays authoritative, this is just a
 * client-side flood guard.
 */
const MIN_QR_CODE_SIZE_CM = pxToCm(100);
const MAX_QR_CODE_SIZE_CM = pxToCm(1000);

const props = withDefaults(
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

/**
 * `qrCodeWidth`/`qrCodeHeight` arrive in pixels (Setting::qr_code_width/height,
 * the value this whole app renders QR labels at) — this form is cm-facing, so
 * the props are converted for display here. The submitted value is converted
 * back to pixels by Edit.vue's `transform` on the enclosing Inertia `<Form>`.
 */
const widthCm = computed(() =>
    props.qrCodeWidth === undefined ? undefined : pxToCm(props.qrCodeWidth),
);
const heightCm = computed(() =>
    props.qrCodeHeight === undefined ? undefined : pxToCm(props.qrCodeHeight),
);
</script>

<template>
    <div class="space-y-4">
        <FormField
            :id="`${idPrefix}qr_code_width`"
            :label="t('settings.fields.qrCodeWidth')"
            type="number"
            :value="widthCm"
            :error="errors.qr_code_width"
            :min="MIN_QR_CODE_SIZE_CM"
            :max="MAX_QR_CODE_SIZE_CM"
            step="0.1"
            required
        />

        <FormField
            :id="`${idPrefix}qr_code_height`"
            :label="t('settings.fields.qrCodeHeight')"
            type="number"
            :value="heightCm"
            :error="errors.qr_code_height"
            :min="MIN_QR_CODE_SIZE_CM"
            :max="MAX_QR_CODE_SIZE_CM"
            step="0.1"
            required
        />
    </div>
</template>
