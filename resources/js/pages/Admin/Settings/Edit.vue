<script setup lang="ts">
import { show as showHelp } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { update } from '@/actions/App/Http/Controllers/Admin/SettingController';
import HelpLink from '@/components/HelpLink.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import SettingFormFields from '@/components/SettingFormFields.vue';
import { t } from '@/lib/i18n';
import type { Setting } from '@/types/admin';

const props = defineProps<{
    setting: Setting;
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('settings.edit.title')"
        :action="update()"
        :submit-label="t('settings.edit.submit')"
        :submitting-label="t('settings.edit.submitting')"
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
