<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import FormField from '@/components/FormField.vue';
import { fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { pxToCm } from '@/lib/qrCodeSize';

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
        /**
         * Every level a pallet can sit on (1..the highest row's level count) —
         * one checkbox each. Empty hides the open-levels field.
         */
        levels?: number[];
        allowedOpenLevels?: number[];
        errors: Partial<
            Record<
                'qr_code_width' | 'qr_code_height' | 'allowed_open_levels',
                string
            >
        >;
    }>(),
    {
        idPrefix: '',
        qrCodeWidth: undefined,
        qrCodeHeight: undefined,
        levels: () => [],
        allowedOpenLevels: () => [],
    },
);

/**
 * Local copy of the checked levels, so the `required` flag below can follow the
 * user's clicks while the inputs themselves stay uncontrolled like FormField's.
 */
const checkedLevels = ref<number[]>([...props.allowedOpenLevels]);

function toggleLevel(level: number, checked: boolean): void {
    checkedLevels.value = checked
        ? [...checkedLevels.value, level]
        : checkedLevels.value.filter((checkedLevel) => checkedLevel !== level);
}

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

        <fieldset v-if="levels.length > 0">
            <legend :class="fieldLabelClass">
                {{ t('settings.fields.allowedOpenLevels') }}
            </legend>
            <p class="mb-2 text-xs text-gray-500 dark:text-neutral-400">
                {{ t('settings.fields.allowedOpenLevelsHint') }}
            </p>
            <div class="space-y-1">
                <div
                    v-for="level in levels"
                    :key="level"
                    class="flex items-center gap-2"
                >
                    <!-- `required` only while nothing is checked, so the browser blocks an empty selection natively. -->
                    <input
                        :id="`${idPrefix}allowed_open_levels_${level}`"
                        name="allowed_open_levels[]"
                        type="checkbox"
                        :value="level"
                        :checked="checkedLevels.includes(level)"
                        :required="checkedLevels.length === 0"
                        class="h-4 w-4 rounded border-gray-300 dark:border-neutral-700"
                        @change="
                            toggleLevel(
                                level,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    <label
                        :for="`${idPrefix}allowed_open_levels_${level}`"
                        class="text-sm text-gray-700 dark:text-neutral-300"
                    >
                        {{ t('settings.fields.level', { level }) }}
                    </label>
                </div>
            </div>
            <p
                v-if="errors.allowed_open_levels"
                class="mt-1 flex items-center gap-1 text-sm text-red-600 dark:text-red-400"
            >
                <CircleAlert class="h-3.5 w-3.5 shrink-0" />
                {{ errors.allowed_open_levels }}
            </p>
        </fieldset>
    </div>
</template>
