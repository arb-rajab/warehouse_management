<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { show as showHelp } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { update } from '@/actions/App/Http/Controllers/Admin/SettingController';
import HelpLink from '@/components/HelpLink.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import SettingFormFields from '@/components/SettingFormFields.vue';
import { t } from '@/lib/i18n';
import { cmToPx } from '@/lib/qrCodeSize';
import type { Setting } from '@/types/admin';

const props = defineProps<{
    setting: Setting;
}>();

/**
 * SettingFormFields.vue is cm-facing, but its native inputs keep the
 * `qr_code_width`/`qr_code_height` names UpdateSettingRequest expects, so the
 * cm values typed there must be converted back to the pixel integers the
 * backend stores/validates before the form submits.
 */
function transformToPixels(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return {
        ...data,
        qr_code_width: cmToPx(Number(data.qr_code_width)),
        qr_code_height: cmToPx(Number(data.qr_code_height)),
    };
}
</script>

<template>
    <ResourceFormPage
        :title="t('settings.edit.title')"
        :action="update()"
        :submit-label="t('settings.edit.submit')"
        :submitting-label="t('settings.edit.submitting')"
        :transform="transformToPixels"
    >
        <template #header-actions>
            <HelpLink :href="showHelp('settings')" />
        </template>

        <template #default="{ errors }">
            <SettingFormFields
                :qr-code-width="props.setting.qr_code_width"
                :qr-code-height="props.setting.qr_code_height"
                :errors="errors"
            />
        </template>
    </ResourceFormPage>
</template>
