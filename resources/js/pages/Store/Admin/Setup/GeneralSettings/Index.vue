<script setup lang="ts">
import { ref } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/GeneralSettingsController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    StoreGeneralSettings,
    StoreGeneralSettingsImage,
    StoreUpload,
} from '@/types/store';

const props = defineProps<{
    settings: StoreGeneralSettings;
    timezones: string[];
    initialFiles: Record<StoreGeneralSettingsImage, StoreUpload[]>;
}>();
const images: {
    key: StoreGeneralSettingsImage;
    label: string;
    hint?: string;
}[] = [
    { key: 'system_logo_white', label: 'logoWhite', hint: 'logoWhiteHint' },
    { key: 'system_logo_black', label: 'logoBlack', hint: 'logoBlackHint' },
    { key: 'admin_login_background', label: 'loginBackground' },
];
const uploads = ref<Record<StoreGeneralSettingsImage, string>>({
    system_logo_white: props.settings.system_logo_white,
    system_logo_black: props.settings.system_logo_black,
    admin_login_background: props.settings.admin_login_background,
});
</script>

<template>
    <ResourceFormPage
        :title="t('store.generalSettings.title')"
        :action="update()"
        :submit-label="t('store.settings.save')"
        :submitting-label="t('store.settings.saving')"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <FieldError :message="errors.settings" />
        <FormField
            id="site_name"
            name="settings[site_name]"
            :label="t('store.generalSettings.siteName')"
            :value="settings.site_name"
            :error="errors['settings.site_name']"
            maxlength="255"
        />
        <div v-for="image in images" :key="image.key" class="space-y-1">
            <StoreUploadField
                :id="image.key"
                v-model="uploads[image.key]"
                :name="`settings[${image.key}]`"
                :label="t(`store.generalSettings.${image.label}`)"
                type="image"
                :initial-files="initialFiles[image.key]"
                :error="errors[`settings.${image.key}`]"
            />
            <p
                v-if="image.hint"
                class="text-sm text-gray-600 dark:text-neutral-300"
            >
                {{ t(`store.generalSettings.${image.hint}`) }}
            </p>
        </div>
        <div>
            <label for="timezone" :class="fieldLabelClass">{{
                t('store.generalSettings.timezone')
            }}</label>
            <select
                id="timezone"
                name="settings[timezone]"
                :class="[plainFieldInputClass, 'cursor-pointer']"
                required
            >
                <option
                    v-for="timezone in timezones"
                    :key="timezone"
                    :value="timezone"
                    :selected="timezone === settings.timezone"
                >
                    {{ timezone }}
                </option>
            </select>
            <p class="mt-1 text-sm text-gray-600 dark:text-neutral-300">
                {{ t('store.generalSettings.timezoneHint') }}
            </p>
            <FieldError :message="errors['settings.timezone']" />
        </div>
    </ResourceFormPage>
</template>
