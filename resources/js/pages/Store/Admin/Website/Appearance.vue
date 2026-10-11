<script setup lang="ts">
import { ref } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteAppearanceController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreWebsiteAppearanceFormFields from '@/components/StoreWebsiteAppearanceFormFields.vue';
import StoreWebsiteScriptsForm from '@/components/StoreWebsiteScriptsForm.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreWebsiteAppearanceSettings } from '@/types/store';
const props = defineProps<{
    settings: StoreWebsiteAppearanceSettings;
    scripts: { header_script: string; footer_script: string } | null;
}>();
const settings = ref<StoreWebsiteAppearanceSettings>({ ...props.settings });
function transform() {
    return { settings: { ...settings.value } };
}
</script>
<template>
    <ResourceFormPage
        :title="t('store.website.appearance.title')"
        :action="update()"
        :submit-label="t('store.website.save')"
        :submitting-label="t('store.website.saving')"
        :layout="StoreAdminLayout"
        :transform="transform"
        wide
    >
        <template #default="{ errors }"
            ><StoreWebsiteAppearanceFormFields
                v-model="settings"
                :errors="errors"
        /></template>
        <template #after-form
            ><StoreWebsiteScriptsForm v-if="scripts" :settings="scripts"
        /></template>
    </ResourceFormPage>
</template>
