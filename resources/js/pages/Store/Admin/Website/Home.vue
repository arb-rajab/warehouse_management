<script setup lang="ts">
import { ref } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteHomeController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreWebsiteHomeFormFields from '@/components/StoreWebsiteHomeFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreWebsiteHomeSettings } from '@/types/store';
const props = defineProps<{
    settings: StoreWebsiteHomeSettings;
    categories: { id: number; name: string }[];
    couponEnabled: boolean;
}>();
const settings = ref({
    ...props.settings,
    home_slider_images: [...props.settings.home_slider_images],
    home_slider_links: [...props.settings.home_slider_links],
    home_categories: [...props.settings.home_categories],
});
function transform() {
    return { settings: { ...settings.value } };
}
</script>
<template>
    <ResourceFormPage
        :title="t('store.website.home.title')"
        :action="update()"
        :submit-label="t('store.website.home.save')"
        :submitting-label="t('store.website.header.saving')"
        :layout="StoreAdminLayout"
        :transform="transform"
        wide
        #default="{ errors }"
    >
        <StoreWebsiteHomeFormFields
            v-model="settings"
            :categories="categories"
            :coupon-enabled="couponEnabled"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
