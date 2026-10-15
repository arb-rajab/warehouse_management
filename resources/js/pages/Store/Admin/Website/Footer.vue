<script setup lang="ts">
import { ref } from 'vue';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/WebsiteFooterController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreWebsiteFooterFormFields from '@/components/StoreWebsiteFooterFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type {
    StoreLanguage,
    StoreUpload,
    StoreWebsiteFooterSettings,
} from '@/types/store';

const props = defineProps<{
    settings: StoreWebsiteFooterSettings;
    lang: string;
    languages: StoreLanguage[];
    initialFiles: Record<
        'footer_logo' | 'payment_method_images',
        StoreUpload[]
    >;
}>();
const settings = ref<StoreWebsiteFooterSettings>({
    ...props.settings,
    widget_one_labels: [...props.settings.widget_one_labels],
    widget_one_links: [...props.settings.widget_one_links],
});
function transform() {
    return { settings: { ...settings.value }, lang: props.lang };
}
</script>

<template>
    <ResourceFormPage
        :title="t('store.website.footer.title')"
        :action="update()"
        :submit-label="t('store.website.footer.save')"
        :submitting-label="t('store.website.saving')"
        :layout="StoreAdminLayout"
        :transform="transform"
        wide
        #default="{ errors }"
    >
        <StoreLanguageTabs
            :languages="languages"
            :lang="lang"
            :href="(code) => edit({ query: { lang: code } })"
        />
        <StoreWebsiteFooterFormFields
            v-model="settings"
            :initial-files="initialFiles"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
