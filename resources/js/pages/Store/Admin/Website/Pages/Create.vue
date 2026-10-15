<script setup lang="ts">
import {
    index as pagesIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreWebsitePageFormFields from '@/components/StoreWebsitePageFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{ homeUrl: string }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.website.pages.createTitle')"
        :action="store()"
        :submit-label="t('store.website.pages.create')"
        :submitting-label="t('store.website.saving')"
        :cancel-href="pagesIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <StoreWebsitePageFormFields :home-url="homeUrl" :errors="errors" />
    </ResourceFormPage>
</template>
