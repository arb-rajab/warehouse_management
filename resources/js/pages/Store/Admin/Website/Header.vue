<script setup lang="ts">
import { ref } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteHeaderController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreWebsiteHeaderFormFields from '@/components/StoreWebsiteHeaderFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreWebsiteHeaderSettings } from '@/types/store';

const props = defineProps<{ settings: StoreWebsiteHeaderSettings }>();
const settings = ref<StoreWebsiteHeaderSettings>({
    ...props.settings,
    header_menu_labels: [...props.settings.header_menu_labels],
    header_menu_links: [...props.settings.header_menu_links],
});
function transform() {
    return { settings: { ...settings.value } };
}
</script>

<template>
    <ResourceFormPage
        :title="t('store.website.header.title')"
        :action="update()"
        :submit-label="t('store.website.header.save')"
        :submitting-label="t('store.website.header.saving')"
        :layout="StoreAdminLayout"
        :transform="transform"
        wide
        #default="{ errors }"
    >
        <StoreWebsiteHeaderFormFields v-model="settings" :errors="errors" />
    </ResourceFormPage>
</template>
