<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import { update as updateScripts } from '@/actions/App/Http/Controllers/Store/Admin/LegacyWebsiteScriptsController';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteAppearanceController';
import FieldError from '@/components/FieldError.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreWebsiteAppearanceFormFields from '@/components/StoreWebsiteAppearanceFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    StoreUpload,
    StoreWebsiteAppearanceImage,
    StoreWebsiteAppearanceSettings,
    StoreWebsiteScripts,
} from '@/types/store';

const props = defineProps<{
    settings: StoreWebsiteAppearanceSettings;
    scripts: StoreWebsiteScripts;
    initialFiles: Partial<Record<StoreWebsiteAppearanceImage, StoreUpload[]>>;
}>();
const settings = ref<StoreWebsiteAppearanceSettings>({ ...props.settings });
// The scripts are raw code for the compatibility endpoint: plain text areas,
// never the rich-text editor, posted exactly as typed.
const scripts = ref<StoreWebsiteScripts>({ ...props.scripts });
const scriptFields = [
    { key: 'header_script', label: 'header' },
    { key: 'footer_script', label: 'footer' },
] as const;
function transform() {
    return { settings: { ...settings.value } };
}
</script>

<template>
    <ResourceFormPage
        :title="t('store.website.appearance.title')"
        :action="update()"
        :submit-label="t('store.website.appearance.save')"
        :submitting-label="t('store.website.saving')"
        :layout="StoreAdminLayout"
        :transform="transform"
        wide
    >
        <template #default="{ errors }">
            <StoreWebsiteAppearanceFormFields
                v-model="settings"
                :initial-files="initialFiles"
                :errors="errors"
            />
        </template>
        <template #after-form>
            <Form
                :action="updateScripts()"
                #default="{ errors, processing }"
                class="mt-8 space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-700"
            >
                <h2 class="font-medium">
                    {{ t('store.website.scripts.title') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-neutral-300">
                    {{ t('store.website.scripts.hint') }}
                </p>
                <FieldError :message="errors.types" />
                <input type="hidden" name="types[]" value="header_script" />
                <input type="hidden" name="types[]" value="footer_script" />
                <div v-for="field in scriptFields" :key="field.key">
                    <label :for="field.key" :class="fieldLabelClass">{{
                        t(`store.website.scripts.${field.label}`)
                    }}</label>
                    <textarea
                        :id="field.key"
                        v-model="scripts[field.key]"
                        :name="field.key"
                        :class="[plainFieldInputClass, 'font-mono']"
                        dir="ltr"
                        rows="4"
                        maxlength="100000"
                        spellcheck="false"
                        autocomplete="off"
                        placeholder="<script>…</script>"
                    />
                    <FieldError :message="errors[field.key]" />
                </div>
                <SubmitButton
                    :label="t('store.website.scripts.save')"
                    :processing-label="t('store.website.saving')"
                    :processing="processing"
                />
            </Form>
        </template>
    </ResourceFormPage>
</template>
